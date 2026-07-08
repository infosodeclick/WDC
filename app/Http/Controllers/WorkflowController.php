<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkflowAuthorization;
use App\Models\WorkflowRequest;
use App\Models\WorkflowRequestAttachment;
use App\Models\WorkflowRequestEvent;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use App\Services\PortalNotificationService;
use App\Services\SmartflowCsvImporter;
use App\Services\SmartflowWorkflowCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');
        $canManage = $this->canManageWorkflows($user);
        $canManageSystem = $user->canAccess('admin.system.manage');
        $status = $request->string('status')->toString();
        $templateId = $request->integer('template');
        $search = trim($request->string('q')->toString());
        $activeView = $this->activeSmartflowView($request);
        $advancedFilters = $this->workflowFilterData($request);
        $favoriteTemplateIds = $user->favoriteWorkflowTemplates()->pluck('workflow_templates.id');
        $delegatedAuthorizerIds = $this->activeDelegatedAuthorizerIds($user);
        $statisticsData = $activeView === 'statistics' ? $this->workflowStatisticsData($user) : [
            'summary' => [],
            'users' => collect(),
            'workflows' => collect(),
        ];
        $dynamicFieldsData = $activeView === 'dynamic_fields' ? $this->dynamicFieldsData() : collect();

        $query = WorkflowRequest::with('template', 'requester.employee.department', 'assignee', 'currentStep', 'events.user', 'attachments')->latest();

        if (! $canManage) {
            $query->where(function ($query) use ($user, $delegatedAuthorizerIds) {
                $query->where('requester_id', $user->id);

                if ($delegatedAuthorizerIds->isNotEmpty()) {
                    $query->orWhereIn('assigned_to', $delegatedAuthorizerIds->all());
                }
            });
        } elseif (! $user->canSeeAllData()) {
            if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
                $query->whereHas('requester.employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $user->employee->department_id));
            } else {
                $query->where('requester_id', $user->id);
            }
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($templateId > 0) {
            $query->where('workflow_template_id', $templateId);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('document_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhere('legacy_reference', 'like', "%{$search}%")
                    ->orWhere('assigned_group', 'like', "%{$search}%")
                    ->orWhereHas('template', fn ($templateQuery) => $templateQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('requester', fn ($requesterQuery) => $requesterQuery->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%"));
            });
        }

        $this->applyWorkflowFilters($query, $advancedFilters, $canManage);
        $this->applySmartflowView($query, $activeView, $user, $favoriteTemplateIds);

        $templates = WorkflowTemplate::with('steps')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('workflows.index', [
            'templates' => $activeView === 'favorites'
                ? $templates->whereIn('id', $favoriteTemplateIds)
                : $templates,
            'templateCatalog' => $templates,
            'requests' => $query->paginate(12)->withQueryString(),
            'statusLabels' => WorkflowRequest::statusLabels(),
            'canManage' => $canManage,
            'canManageSystem' => $canManageSystem,
            'canCreate' => $user->canAccess('workflows.create'),
            'manageableUsers' => $canManage ? User::with('employee.department')->where('is_active', true)->orderBy('name')->get() : collect(),
            'authorizationUsers' => User::with('employee.department')
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(),
            'authorizationsGiven' => WorkflowAuthorization::with('authorizedUser.employee.department')
                ->where('authorizer_id', $user->id)
                ->latest()
                ->get(),
            'authorizationsReceived' => WorkflowAuthorization::with('authorizer.employee.department')
                ->where('authorized_user_id', $user->id)
                ->latest()
                ->get(),
            'statisticsData' => $statisticsData,
            'dynamicFieldsData' => $dynamicFieldsData,
            'menuTabs' => $this->smartflowMenuTabs(),
            'activeView' => $activeView,
            'activeStatus' => $status,
            'activeTemplateId' => $templateId,
            'activeSearch' => $search,
            'activeDateFrom' => $advancedFilters['date_from'] ?? '',
            'activeDateTo' => $advancedFilters['date_to'] ?? '',
            'activeRequesterId' => (int) ($advancedFilters['requester'] ?? 0),
            'activeAssigneeId' => (int) ($advancedFilters['assignee'] ?? 0),
            'favoriteTemplateIds' => $favoriteTemplateIds,
            'importHeaders' => $this->smartflowImportHeaders(),
            'metrics' => [
                'submitted' => (clone $this->scopedWorkflowQuery($user))->where('status', 'submitted')->count(),
                'in_review' => (clone $this->scopedWorkflowQuery($user))->whereIn('status', ['in_review', 'accepted', 'in_progress', 'waiting_requester'])->count(),
                'completed' => (clone $this->scopedWorkflowQuery($user))->whereIn('status', ['approved', 'completed'])->count(),
                'overdue' => (clone $this->scopedWorkflowQuery($user))->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled'])->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('workflows.create'), 403);

        $data = $request->validate([
            'workflow_template_id' => ['required', 'exists:workflow_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:5000'],
            'form_payload' => ['nullable', 'array'],
            'form_payload.*' => ['nullable', 'string', 'max:5000'],
            'attachment_links' => ['nullable', 'string', 'max:5000'],
            'workflow_files' => ['nullable', 'array', 'max:5'],
            'workflow_files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
            'priority' => ['required', 'in:low,normal,high,critical'],
            'legacy_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $attachmentLinks = $data['attachment_links'] ?? '';
        $uploadedFiles = $data['workflow_files'] ?? [];
        unset($data['attachment_links'], $data['workflow_files']);

        $template = WorkflowTemplate::with('steps')->findOrFail($data['workflow_template_id']);
        $firstStep = $template->steps->first();

        $workflowRequest = WorkflowRequest::create([
            ...$data,
            'requester_id' => $request->user()->id,
            'current_step_id' => $firstStep?->id,
            'smartflow_menu' => $template->smartflow_menu ?: 'All Documents',
            'form_payload' => $this->cleanPayload($data['form_payload'] ?? []),
            'assigned_group' => $firstStep?->approver_group ?: $template->service_team,
            'status' => 'submitted',
            'due_at' => $template->sla_hours ? now()->addHours($template->sla_hours) : null,
            'submitted_at' => now(),
        ]);

        $this->storeAttachmentLinks($workflowRequest, $attachmentLinks, $request->user(), 'wdc');
        $this->storeUploadedAttachments($workflowRequest, $uploadedFiles, $request->user(), 'wdc-upload');

        $workflowRequest->update([
            'document_number' => $this->generateDocumentNumber($workflowRequest),
        ]);

        WorkflowRequestEvent::create([
            'workflow_request_id' => $workflowRequest->id,
            'user_id' => $request->user()->id,
            'action' => 'create',
            'to_status' => 'submitted',
            'comment' => "Created {$template->name} request {$workflowRequest->document_number}",
        ]);

        $this->log($request, 'create_workflow_request', WorkflowRequest::class, $workflowRequest->id, "Created {$workflowRequest->title}");

        User::with('role.permissions', 'permissionOverrides')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccess('workflows.manage'))
            ->each(fn (User $user) => $this->createWorkflowNotification([
                'user_id' => $user->id,
                'type' => 'workflow',
                'title' => 'มีคำขออนุมัติใหม่',
                'body' => "{$template->name}: {$workflowRequest->title}",
                'url' => route('workflows.index'),
            ]));

        return redirect()->route('workflows.index')->with('status', 'ส่งคำขอเข้าศูนย์อนุมัติเรียบร้อยแล้ว');
    }

    public function updateStatus(WorkflowRequest $workflowRequest, Request $request): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canActOnWorkflow($user, $workflowRequest->load('requester.employee.department')), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WorkflowRequest::statusLabels()))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $fromStatus = $workflowRequest->status;
        $nextStep = $this->stepForStatus($workflowRequest, $data['status']);

        $workflowRequest->update([
            'status' => $data['status'],
            'current_step_id' => $nextStep?->id,
            'assigned_to' => $request->filled('assigned_to') ? $data['assigned_to'] : ($workflowRequest->assigned_to ?: $request->user()->id),
            'assigned_group' => $nextStep?->approver_group ?: $workflowRequest->assigned_group,
            'due_at' => $request->filled('due_at') ? $data['due_at'] : $workflowRequest->due_at,
            'completed_at' => in_array($data['status'], ['approved', 'rejected', 'completed', 'cancelled'], true) ? now() : null,
        ]);

        WorkflowRequestEvent::create([
            'workflow_request_id' => $workflowRequest->id,
            'user_id' => $request->user()->id,
            'action' => 'status_change',
            'from_status' => $fromStatus,
            'to_status' => $data['status'],
            'comment' => $data['comment'] ?? null,
        ]);

        $this->createWorkflowNotification([
            'user_id' => $workflowRequest->requester_id,
            'type' => 'workflow',
            'title' => 'สถานะคำขอเปลี่ยนแล้ว',
            'body' => "{$workflowRequest->title}: {$workflowRequest->statusLabel()}",
            'url' => route('workflows.index'),
        ]);

        $this->log($request, 'update_workflow_status', WorkflowRequest::class, $workflowRequest->id, "Changed workflow status to {$data['status']}");

        return back()->with('status', 'อัปเดตสถานะคำขอแล้ว');
    }

    public function toggleFavorite(WorkflowTemplate $template, Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('workflows.create'), 403);

        $request->user()->favoriteWorkflowTemplates()->toggle([$template->id]);

        $this->log($request, 'toggle_workflow_favorite', WorkflowTemplate::class, $template->id, "Toggled favorite {$template->name}");

        return back()->with('status', 'อัปเดตรายการโปรดแล้ว');
    }

    public function comment(WorkflowRequest $workflowRequest, Request $request): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canViewWorkflow($user, $workflowRequest->load('requester.employee.department')), 403);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
            'attachment_links' => ['nullable', 'string', 'max:5000'],
            'workflow_files' => ['nullable', 'array', 'max:5'],
            'workflow_files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);

        WorkflowRequestEvent::create([
            'workflow_request_id' => $workflowRequest->id,
            'user_id' => $user->id,
            'action' => 'comment',
            'comment' => $data['comment'],
        ]);

        $this->storeAttachmentLinks($workflowRequest, $data['attachment_links'] ?? '', $user, 'wdc');
        $this->storeUploadedAttachments($workflowRequest, $data['workflow_files'] ?? [], $user, 'wdc-comment');
        $this->notifyWorkflowParticipants($workflowRequest, $user, 'มีคอมเมนต์ใหม่', $data['comment']);
        $this->log($request, 'comment_workflow_request', WorkflowRequest::class, $workflowRequest->id, "Commented {$workflowRequest->document_number}");

        return back()->with('status', 'เพิ่มคอมเมนต์แล้ว');
    }

    public function storeAuthorization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'authorized_user_id' => ['required', 'exists:users,id', Rule::notIn([$request->user()->id])],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $authorization = WorkflowAuthorization::create([
            'authorizer_id' => $request->user()->id,
            'authorized_user_id' => $data['authorized_user_id'],
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => 'active',
        ]);

        $authorizedUser = User::find($data['authorized_user_id']);

        if ($authorizedUser) {
            $this->createWorkflowNotification([
                'user_id' => $authorizedUser->id,
                'type' => 'workflow',
                'title' => 'Approval authorization assigned',
                'body' => "{$request->user()->name} authorized you to approve SmartFlow documents on their behalf.",
                'url' => route('workflows.index', ['view' => 'authorizations']),
            ]);
        }

        $this->log($request, 'create_workflow_authorization', WorkflowAuthorization::class, $authorization->id, "Authorized user {$authorization->authorized_user_id}");

        return redirect()->route('workflows.index', ['view' => 'authorizations'])->with('status', 'Created approval authorization.');
    }

    public function revokeAuthorization(WorkflowAuthorization $authorization, Request $request): RedirectResponse
    {
        abort_unless(
            $authorization->authorizer_id === $request->user()->id || $request->user()->canAccess('admin.system.manage'),
            403
        );

        $authorization->update([
            'status' => 'revoked',
            'revoked_by' => $request->user()->id,
            'revoked_at' => now(),
        ]);

        $this->log($request, 'revoke_workflow_authorization', WorkflowAuthorization::class, $authorization->id, "Revoked authorization {$authorization->id}");

        return redirect()->route('workflows.index', ['view' => 'authorizations'])->with('status', 'Revoked approval authorization.');
    }

    public function downloadAttachment(WorkflowRequestAttachment $attachment, Request $request)
    {
        $workflowRequest = $attachment->request()->with('requester.employee.department')->firstOrFail();
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canViewWorkflow($user, $workflowRequest), 403);
        abort_unless($attachment->file_path && Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    public function importCsv(Request $request, SmartflowCsvImporter $importer): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canManageWorkflows($user), 403);

        $data = $request->validate([
            'smartflow_csv' => ['required', 'file', 'max:10240'],
        ]);

        $stats = $importer->import($data['smartflow_csv']->getRealPath(), $user, [
            'default_requester' => $user,
        ]);

        $this->log($request, 'import_smartflow_csv', WorkflowRequest::class, null, "Imported {$stats['total']} SmartFlow rows");

        return back()
            ->with('status', "Import SmartFlow สำเร็จ {$stats['created']} ใหม่, {$stats['updated']} อัปเดต, {$stats['skipped']} ข้าม")
            ->with('import_errors', $stats['errors']);
    }

    public function downloadImportTemplate(Request $request)
    {
        abort_unless($this->canManageWorkflows($request->user()->load('role.permissions', 'permissionOverrides')), 403);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            echo "\xEF\xBB\xBF";
            fputcsv($handle, $this->smartflowImportHeaders());
            fputcsv($handle, [
                'SF-2606815',
                '7',
                'IT Helpdesk',
                'ขอใช้งาน VPN',
                'พนักงานขอเปิด VPN สำหรับทำงานนอกสถานที่',
                'EMP00125',
                'somchai@wdc.co.th',
                'submitted',
                'normal',
                now()->format('Y-m-d H:i:s'),
                '',
                now()->addDay()->format('Y-m-d H:i:s'),
                'EMP00200',
                'IT Helpdesk',
                'Your Tasks',
                'REF: #2606815',
                'https://wdc.smartflow.pw/document/2606815/',
                'SAP B1',
                'VPN',
                'https://example.com/file.pdf',
            ]);
            fclose($handle);
        }, 'wdc-smartflow-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($user->canAccess('admin.system.manage'), 403);

        $data = $this->validateTemplateData($request);

        $template = WorkflowTemplate::create($this->templateAttributes($data, [
            'source_system' => 'wdc',
            'is_active' => true,
            'sort_order' => ((int) WorkflowTemplate::max('sort_order')) + 10,
        ]));

        $this->syncTemplateSteps($template, $data['step_lines'] ?? '');
        $this->log($request, 'create_workflow_template', WorkflowTemplate::class, $template->id, "Created workflow template {$template->name}");

        return back()->with('status', 'สร้าง workflow template ใหม่แล้ว');
    }

    public function updateTemplate(WorkflowTemplate $template, Request $request): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($user->canAccess('admin.system.manage'), 403);

        $data = $this->validateTemplateData($request);

        $template->update($this->templateAttributes($data, [
            'is_active' => $request->boolean('is_active'),
        ], $template));

        $this->syncTemplateSteps($template, $data['step_lines'] ?? '');
        $this->log($request, 'update_workflow_template', WorkflowTemplate::class, $template->id, "Updated workflow template {$template->name}");

        return back()->with('status', 'อัปเดต workflow template แล้ว');
    }

    public function syncSmartflowCatalog(Request $request, SmartflowWorkflowCatalog $catalog): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($user->canAccess('admin.system.manage'), 403);

        $catalog->sync();
        $this->log($request, 'sync_smartflow_catalog', WorkflowTemplate::class, null, 'Synced SmartFlow workflow catalog from WDC source snapshot');

        return back()->with('status', 'Sync SmartFlow workflow catalog เข้า WDC แล้ว');
    }

    public function export(Request $request)
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canManageWorkflows($user), 403);

        $status = $request->string('status')->toString();
        $templateId = $request->integer('template');
        $search = trim($request->string('q')->toString());
        $activeView = $this->activeSmartflowView($request);
        $favoriteTemplateIds = $user->favoriteWorkflowTemplates()->pluck('workflow_templates.id');
        $advancedFilters = $this->workflowFilterData($request);

        $rows = $this->scopedWorkflowQuery($user)
            ->with('template', 'requester.employee.department', 'currentStep', 'assignee')
            ->latest();

        if ($status !== '') {
            $rows->where('status', $status);
        }

        if ($templateId > 0) {
            $rows->where('workflow_template_id', $templateId);
        }

        if ($search !== '') {
            $rows->where(function ($query) use ($search) {
                $query->where('document_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhere('legacy_reference', 'like', "%{$search}%")
                    ->orWhere('assigned_group', 'like', "%{$search}%")
                    ->orWhereHas('template', fn ($templateQuery) => $templateQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('requester', fn ($requesterQuery) => $requesterQuery->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%"));
            });
        }

        $this->applyWorkflowFilters($rows, $advancedFilters, true);
        $this->applySmartflowView($rows, $activeView, $user, $favoriteTemplateIds);

        $rows = $rows
            ->limit(2000)
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            echo "\xEF\xBB\xBF";
            fputcsv($handle, ['document_number', 'workflow', 'requester', 'department', 'status', 'priority', 'current_step', 'assigned_to', 'assigned_group', 'legacy_reference', 'external_url', 'submitted_at', 'due_at']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->document_number,
                    $row->template?->name,
                    $row->requester?->name,
                    $row->requester?->employee?->department?->name,
                    $row->status,
                    $row->priority,
                    $row->currentStep?->name,
                    $row->assignee?->name,
                    $row->assigned_group,
                    $row->legacy_reference,
                    $row->external_url,
                    $row->submitted_at?->format('Y-m-d H:i:s'),
                    $row->due_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 'wdc-smartflow-documents-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validateTemplateData(Request $request): array
    {
        return $request->validate([
            'legacy_workflow_id' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:120'],
            'smartflow_menu' => ['required', Rule::in(array_keys($this->smartflowMenuTabs()))],
            'service_team' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'form_schema_fields' => ['nullable', 'string', 'max:5000'],
            'sla_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'approval_policy' => ['required', 'string', 'max:120'],
            'legacy_url' => ['nullable', 'url', 'max:1000'],
            'step_lines' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function templateAttributes(array $data, array $overrides = [], ?WorkflowTemplate $template = null): array
    {
        $schema = $template?->form_schema ?? [];
        $schema['fields'] = $this->parseTemplateFieldLines($data['form_schema_fields'] ?? '');
        $schema['statuses'] = $schema['statuses'] ?? SmartflowWorkflowCatalog::defaultStatusFlow();

        return [
            'legacy_workflow_id' => $data['legacy_workflow_id'] ?? null,
            'name' => $data['name'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'smartflow_menu' => $this->smartflowMenuTabs()[$data['smartflow_menu']]['label'] ?? 'All Documents',
            'service_team' => $data['service_team'] ?? null,
            'form_schema' => $schema,
            'sla_hours' => $data['sla_hours'] ?? null,
            'approval_policy' => $data['approval_policy'],
            'legacy_url' => $data['legacy_url'] ?? null,
            ...$overrides,
        ];
    }

    private function parseTemplateFieldLines(string $fieldLines): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $fieldLines) ?: [])
            ->map(fn (string $field) => trim($field))
            ->filter()
            ->map(function (string $field) {
                $parts = array_map('trim', explode('|', $field));

                if (count($parts) > 1) {
                    return [
                        'key' => $parts[0] !== '' ? $parts[0] : str($parts[1] ?? 'field')->slug('_')->toString(),
                        'label' => $parts[1] ?? $parts[0],
                        'type' => $parts[2] ?? 'text',
                        'required' => in_array(strtolower($parts[3] ?? ''), ['1', 'true', 'yes', 'required'], true),
                        'help' => $parts[4] ?? null,
                    ];
                }

                return [
                    'key' => str($field)->slug('_')->toString(),
                    'label' => $field,
                    'type' => 'text',
                    'required' => false,
                    'help' => null,
                ];
            })
            ->values()
            ->all();
    }

    private function syncTemplateSteps(WorkflowTemplate $template, string $stepLines): void
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', $stepLines) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter();

        if ($lines->isEmpty() && ! $template->steps()->exists()) {
            $lines = collect([
                '1|Submit Request|Requester|ส่งคำขอเข้าระบบ|0',
                '2|Manager Review|Manager / Approver|ตรวจสอบรายละเอียด|0',
                '3|Complete Request|Service Owner|ปิดงานหรืออนุมัติขั้นสุดท้าย|1',
            ]);
        }

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));
            $order = (int) ($parts[0] ?? 0);

            if ($order < 1 || ($parts[1] ?? '') === '') {
                continue;
            }

            $template->steps()->updateOrCreate(
                ['step_order' => $order],
                [
                    'name' => $parts[1],
                    'mode' => 'any_one',
                    'approver_group' => $parts[2] ?? null,
                    'approver_hint' => $parts[2] ?? null,
                    'condition_label' => $parts[3] ?? null,
                    'action_label' => $parts[1],
                    'branch_label' => $parts[3] ?? null,
                    'metadata' => [
                        'approvers' => array_values(array_filter(array_map('trim', explode(',', $parts[2] ?? '')))),
                        'conditions' => array_values(array_filter([$parts[3] ?? null])),
                        'source_note' => 'Edited from WDC Workflow Backend.',
                    ],
                    'requires_input' => in_array(($parts[4] ?? '0'), ['1', 'true', 'yes', 'required'], true),
                ],
            );
        }
    }

    private function smartflowImportHeaders(): array
    {
        return [
            'document_number',
            'workflow_id',
            'workflow',
            'title',
            'details',
            'requester_employee_code',
            'requester_email',
            'status',
            'priority',
            'submitted_at',
            'completed_at',
            'due_at',
            'assigned_employee_code',
            'assigned_group',
            'smartflow_menu',
            'legacy_reference',
            'external_url',
            'system',
            'request_type',
            'attachments',
        ];
    }

    private function storeAttachmentLinks(WorkflowRequest $workflowRequest, string $rawLinks, User $user, string $sourceSystem): int
    {
        $links = collect(preg_split('/[\r\n;|]+/', $rawLinks) ?: [])
            ->map(fn (string $link) => trim($link))
            ->filter();

        $count = 0;

        foreach ($links as $index => $link) {
            $workflowRequest->attachments()->updateOrCreate(
                ['file_url' => $link],
                [
                    'source_system' => $sourceSystem,
                    'file_name' => basename(parse_url($link, PHP_URL_PATH) ?: $link),
                    'uploaded_by' => $user->id,
                    'sort_order' => $index + 1,
                ],
            );

            $count++;
        }

        return $count;
    }

    private function storeUploadedAttachments(WorkflowRequest $workflowRequest, array $files, User $user, string $sourceSystem): int
    {
        $files = collect($files)->filter();
        $baseSort = (int) $workflowRequest->attachments()->max('sort_order');
        $count = 0;

        foreach ($files as $file) {
            $path = $file->store("workflow-attachments/{$workflowRequest->id}", 'local');
            $count++;

            $workflowRequest->attachments()->create([
                'source_system' => $sourceSystem,
                'file_name' => $file->getClientOriginalName(),
                'file_url' => "local://{$path}",
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
                'sort_order' => $baseSort + $count,
            ]);
        }

        return $count;
    }

    /**
     * @param array{user_id:int,type:string,title:string,body:string,url:string|null} $payload
     */
    private function createWorkflowNotification(array $payload): void
    {
        $user = User::find($payload['user_id']);

        if (! $user) {
            return;
        }

        unset($payload['user_id']);

        app(PortalNotificationService::class)->createForUser($user, $payload);
    }

    private function notifyWorkflowParticipants(WorkflowRequest $workflowRequest, User $actor, string $title, string $body): void
    {
        collect([$workflowRequest->requester_id, $workflowRequest->assigned_to])
            ->filter(fn (?int $userId) => $userId && $userId !== $actor->id)
            ->unique()
            ->each(fn (int $userId) => $this->createWorkflowNotification([
                'user_id' => $userId,
                'type' => 'workflow',
                'title' => $title,
                'body' => "{$workflowRequest->document_number}: ".((string) str($body)->limit(120)),
                'url' => route('workflows.index'),
            ]));
    }

    private function stepForStatus(WorkflowRequest $workflowRequest, string $status): ?WorkflowStep
    {
        if (in_array($status, ['approved', 'rejected', 'completed', 'cancelled'], true)) {
            return null;
        }

        $steps = $workflowRequest->template->steps()->orderBy('step_order')->get();

        return match ($status) {
            'in_review' => $steps->skip(1)->first() ?? $steps->first(),
            'accepted' => $steps->firstWhere('name', 'Accept Case') ?? $steps->skip(1)->first() ?? $steps->first(),
            'in_progress' => $steps->firstWhere('name', 'Resolve Case') ?? $steps->skip(2)->first() ?? $steps->last(),
            'waiting_requester' => $workflowRequest->currentStep,
            default => $steps->first(),
        };
    }

    private function canManageWorkflows(User $user): bool
    {
        return $user->canAccess('workflows.manage');
    }

    private function canViewWorkflow(User $user, WorkflowRequest $workflowRequest): bool
    {
        if ($workflowRequest->requester_id === $user->id) {
            return true;
        }

        if ($this->hasActiveAuthorizationForWorkflow($user, $workflowRequest)) {
            return true;
        }

        if (! $this->canManageWorkflows($user)) {
            return false;
        }

        if ($user->canSeeAllData()) {
            return true;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $workflowRequest->requester?->employee?->department_id === $user->employee->department_id;
        }

        return false;
    }

    private function canActOnWorkflow(User $user, WorkflowRequest $workflowRequest): bool
    {
        if ($this->canManageWorkflows($user) && $this->canViewWorkflow($user, $workflowRequest)) {
            return true;
        }

        return $this->hasActiveAuthorizationForWorkflow($user, $workflowRequest);
    }

    private function hasActiveAuthorizationForWorkflow(User $user, WorkflowRequest $workflowRequest): bool
    {
        if (! $workflowRequest->assigned_to) {
            return false;
        }

        return WorkflowAuthorization::active()
            ->where('authorizer_id', $workflowRequest->assigned_to)
            ->where('authorized_user_id', $user->id)
            ->exists();
    }

    private function scopedWorkflowQuery(User $user)
    {
        $query = WorkflowRequest::query();
        $delegatedAuthorizerIds = $this->activeDelegatedAuthorizerIds($user);

        if (! $this->canManageWorkflows($user)) {
            return $query->where(function ($query) use ($user, $delegatedAuthorizerIds) {
                $query->where('requester_id', $user->id);

                if ($delegatedAuthorizerIds->isNotEmpty()) {
                    $query->orWhereIn('assigned_to', $delegatedAuthorizerIds->all());
                }
            });
        }

        if ($user->canSeeAllData()) {
            return $query;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $query->whereHas('requester.employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $user->employee->department_id));
        }

        return $query->where('requester_id', $user->id);
    }

    private function activeDelegatedAuthorizerIds(User $user)
    {
        return WorkflowAuthorization::active()
            ->where('authorized_user_id', $user->id)
            ->pluck('authorizer_id')
            ->unique()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowFilterData(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'requester' => ['nullable', 'integer', 'exists:users,id'],
            'assignee' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    private function applyWorkflowFilters($query, array $filters, bool $canManage): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if ($canManage && ! empty($filters['requester'])) {
            $query->where('requester_id', $filters['requester']);
        }

        if ($canManage && ! empty($filters['assignee'])) {
            $query->where('assigned_to', $filters['assignee']);
        }
    }

    private function activeSmartflowView(Request $request): string
    {
        $view = $request->string('view')->toString();

        return array_key_exists($view, $this->smartflowMenuTabs()) ? $view : 'all';
    }

    private function smartflowMenuTabs(): array
    {
        return [
            'all' => ['label' => 'All Documents', 'icon' => 'bi-collection'],
            'tasks' => ['label' => 'Your Tasks', 'icon' => 'bi-inbox'],
            'authorizations' => ['label' => 'Authorization', 'icon' => 'bi-shield-check'],
            'statistics' => ['label' => 'Statistics', 'icon' => 'bi-bar-chart'],
            'export' => ['label' => 'Export Excel', 'icon' => 'bi-file-earmark-spreadsheet'],
            'favorites' => ['label' => 'Favorites', 'icon' => 'bi-star'],
            'dynamic_fields' => ['label' => 'Dynamic Fields', 'icon' => 'bi-ui-checks-grid'],
            'workflows' => ['label' => 'Workflows', 'icon' => 'bi-diagram-3'],
        ];
    }

    private function applySmartflowView($query, string $view, User $user, $favoriteTemplateIds): void
    {
        if ($view === 'tasks') {
            $delegatedAuthorizerIds = $this->activeDelegatedAuthorizerIds($user);

            $query->whereIn('status', ['submitted', 'in_review', 'accepted', 'in_progress', 'waiting_requester'])
                ->where(function ($query) use ($user, $delegatedAuthorizerIds) {
                    $query->where('assigned_to', $user->id)
                        ->orWhere('requester_id', $user->id);

                    if ($delegatedAuthorizerIds->isNotEmpty()) {
                        $query->orWhereIn('assigned_to', $delegatedAuthorizerIds->all());
                    }

                    if ($this->canManageWorkflows($user)) {
                        $query->orWhereNull('assigned_to');
                    }
                });
        }

        if ($view === 'authorizations') {
            $query->whereIn('status', ['submitted', 'in_review'])
                ->whereHas('template', fn ($templateQuery) => $templateQuery->where('smartflow_menu', 'Authorization'));
        }

        if ($view === 'favorites') {
            $query->whereIn('workflow_template_id', $favoriteTemplateIds->all() ?: [0]);
        }
    }

    private function workflowStatisticsData(User $user): array
    {
        $requests = $this->scopedWorkflowQuery($user)
            ->with('template', 'assignee.employee.department', 'events.user.employee.department')
            ->latest()
            ->limit(2000)
            ->get();

        $pendingStatuses = ['submitted', 'in_review', 'accepted', 'in_progress', 'waiting_requester'];
        $terminalStatuses = ['approved', 'rejected', 'completed', 'cancelled'];
        $decisionEvents = $requests->flatMap(function (WorkflowRequest $request) {
            return $request->events
                ->filter(fn (WorkflowRequestEvent $event) => $event->user_id && in_array($event->action, ['status_change', 'smartflow_import', 'smartflow_import_update'], true))
                ->map(fn (WorkflowRequestEvent $event) => [
                    'event' => $event,
                    'request' => $request,
                ]);
        });

        $userIds = $decisionEvents
            ->pluck('event.user_id')
            ->merge($requests->pluck('assigned_to'))
            ->filter()
            ->unique()
            ->values();

        $users = User::with('employee.department')
            ->whereIn('id', $userIds->all())
            ->get()
            ->keyBy('id');

        $userStats = $userIds
            ->map(function (int $userId) use ($users, $requests, $decisionEvents, $pendingStatuses, $terminalStatuses) {
                $statUser = $users->get($userId);
                $userEvents = $decisionEvents->filter(fn (array $entry) => $entry['event']->user_id === $userId);
                $processedEvents = $userEvents->filter(fn (array $entry) => in_array((string) $entry['event']->to_status, $terminalStatuses, true));
                $averageSeconds = $this->averageEventResponseSeconds($userEvents);

                return [
                    'user' => $statUser,
                    'name' => $statUser?->name ?? 'Unknown user',
                    'email' => $statUser?->email,
                    'initial' => mb_substr($statUser?->name ?? 'U', 0, 1),
                    'total_decisions' => $userEvents->count(),
                    'pending_approvals' => $requests
                        ->where('assigned_to', $userId)
                        ->whereIn('status', $pendingStatuses)
                        ->count(),
                    'processed' => $processedEvents->count(),
                    'avg_response' => $this->formatDuration($averageSeconds),
                    'avg_response_seconds' => $averageSeconds,
                ];
            })
            ->sortByDesc('total_decisions')
            ->values();

        $workflowStats = $requests
            ->groupBy('workflow_template_id')
            ->map(function ($items) {
                $completedDurations = $items
                    ->filter(fn (WorkflowRequest $request) => $request->completed_at)
                    ->map(function (WorkflowRequest $request) {
                        $start = $request->submitted_at ?: $request->created_at;

                        return $start ? $start->diffInSeconds($request->completed_at) : null;
                    })
                    ->filter(fn ($seconds) => $seconds !== null);

                return [
                    'workflow' => $items->first()?->template?->name ?? 'Unknown workflow',
                    'service_team' => $items->first()?->template?->service_team,
                    'documents' => $items->count(),
                    'pending' => $items->whereIn('status', ['submitted', 'in_review', 'accepted', 'in_progress', 'waiting_requester'])->count(),
                    'completed' => $items->whereIn('status', ['approved', 'completed'])->count(),
                    'avg_completion' => $this->formatDuration($completedDurations->isNotEmpty() ? (int) round($completedDurations->avg()) : null),
                ];
            })
            ->sortByDesc('documents')
            ->values();

        return [
            'summary' => [
                'documents' => $requests->count(),
                'pending' => $requests->whereIn('status', $pendingStatuses)->count(),
                'processed' => $requests->whereIn('status', $terminalStatuses)->count(),
                'active_users' => $userStats->count(),
            ],
            'users' => $userStats,
            'workflows' => $workflowStats,
        ];
    }

    private function dynamicFieldsData()
    {
        return WorkflowTemplate::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->flatMap(function (WorkflowTemplate $template) {
                return collect($template->schemaFieldDefinitions())->map(function (array $field) use ($template) {
                    $options = collect($field['options'] ?? [])->filter()->values();

                    return [
                        'key' => $field['key'] ?? '',
                        'label' => $field['label'] ?? '',
                        'type' => $field['type'] ?? 'text',
                        'required' => (bool) ($field['required'] ?? false),
                        'help' => $field['help'] ?? null,
                        'options' => $options,
                        'workflow' => $template->name,
                        'workflow_id' => $template->legacy_workflow_id,
                        'category' => $template->category,
                    ];
                });
            })
            ->filter(fn (array $field) => $field['label'] !== '')
            ->sortBy([
                ['workflow', 'asc'],
                ['label', 'asc'],
            ])
            ->values();
    }

    private function averageEventResponseSeconds($events): ?int
    {
        $durations = $events
            ->map(function (array $entry) {
                $request = $entry['request'];
                $event = $entry['event'];
                $start = $request->submitted_at ?: $request->created_at;

                return $start && $event->created_at ? $start->diffInSeconds($event->created_at) : null;
            })
            ->filter(fn ($seconds) => $seconds !== null);

        return $durations->isNotEmpty() ? (int) round($durations->avg()) : null;
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'N/A';
        }

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        }

        if ($hours > 0) {
            return "{$hours}h {$minutes}m {$seconds}s";
        }

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }

    private function cleanPayload(array $payload): array
    {
        return collect($payload)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function generateDocumentNumber(WorkflowRequest $workflowRequest): string
    {
        $date = ($workflowRequest->submitted_at ?: now())->format('Ymd');

        return 'WDC-SF-'.$date.'-'.str_pad((string) $workflowRequest->id, 5, '0', STR_PAD_LEFT);
    }

    private function log(Request $request, string $action, ?string $subjectType = null, ?int $subjectId = null, ?string $description = null): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
