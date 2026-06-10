<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowRequestEvent;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ItHelpdeskWorkflow
{
    public const HELPDESK_LEGACY_ID = '7';

    public const IT_LEGACY_IDS = ['7', '13'];

    public const ACTIVE_STATUSES = ['submitted', 'in_review', 'accepted', 'in_progress', 'waiting_requester'];

    public const DONE_STATUSES = ['approved', 'completed'];

    public const TERMINAL_STATUSES = ['approved', 'rejected', 'completed', 'cancelled'];

    private const REQUEST_TYPE_FIELD_MAP = [
        'cancel_document' => 'แจ้งยกเลิกเอกสาร(โปรดระบุเลข Document Ref ด้วย)',
        'vpn_access' => 'แจ้งขอใช้งาน VPN (โปรดระบุวัตถุประสงค์ในการใช้งาน)',
        'sap_b1' => 'แจ้งปัญหาโปรแกรม SAP B1',
        'ai_crm' => 'แจ้งปัญหาโปรแกรม AI-CRM',
        'remote_access' => 'ขอเข้าถึง,แก้ไขข้อมูล,database/ขอ Remote Access กับเครื่องพนักงานในองค์กร',
    ];

    public function primaryTemplate(): ?WorkflowTemplate
    {
        return WorkflowTemplate::with('steps')
            ->where('source_system', 'smartflow')
            ->where('legacy_workflow_id', self::HELPDESK_LEGACY_ID)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<int, int>
     */
    public function templateIds(): array
    {
        return WorkflowTemplate::query()
            ->where('source_system', 'smartflow')
            ->whereIn('legacy_workflow_id', self::IT_LEGACY_IDS)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function route(array $query = []): string
    {
        $templateId = $this->primaryTemplate()?->id;

        return route('workflows.index', array_filter([
            'template' => $templateId,
            ...$query,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function queryFor(User $user, bool $canManage): Builder
    {
        $query = WorkflowRequest::with('template', 'requester.employee.department', 'assignee', 'currentStep', 'events.user', 'attachments')
            ->whereIn('workflow_template_id', $this->templateIds() ?: [0])
            ->latest();

        if (! $canManage) {
            return $query->where('requester_id', $user->id);
        }

        if ($user->canSeeAllData()) {
            return $query;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $query->whereHas('requester.employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $user->employee->department_id));
        }

        return $query->where('requester_id', $user->id);
    }

    public function nonItWorkflowQueryFor(User $user, bool $canManage): Builder
    {
        $query = WorkflowRequest::with('template', 'currentStep')
            ->whereNotIn('workflow_template_id', $this->templateIds() ?: [0])
            ->latest();

        if (! $canManage) {
            return $query->where('requester_id', $user->id);
        }

        if ($user->canSeeAllData()) {
            return $query;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $query->whereHas('requester.employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $user->employee->department_id));
        }

        return $query->where('requester_id', $user->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromLegacyTicketInput(User $user, array $data): WorkflowRequest
    {
        $template = $this->primaryTemplate();

        if (! $template) {
            throw new RuntimeException('IT Helpdesk workflow template is not ready.');
        }

        return DB::transaction(function () use ($template, $user, $data) {
            $firstStep = $template->steps->first();

            $workflowRequest = WorkflowRequest::create([
                'workflow_template_id' => $template->id,
                'requester_id' => $user->id,
                'current_step_id' => $firstStep?->id,
                'smartflow_menu' => $template->smartflow_menu ?: 'Your Tasks',
                'title' => $data['title'],
                'details' => $data['details'],
                'form_payload' => $this->payloadFromLegacyTicketInput($data),
                'assigned_group' => $firstStep?->approver_group ?: $template->service_team,
                'priority' => $data['urgency'] ?? 'normal',
                'status' => 'submitted',
                'legacy_reference' => $data['legacy_document_ref'] ?? null,
                'due_at' => $template->sla_hours ? now()->addHours($template->sla_hours) : null,
                'submitted_at' => now(),
            ]);

            $workflowRequest->update([
                'document_number' => $this->documentNumber($workflowRequest),
            ]);

            WorkflowRequestEvent::create([
                'workflow_request_id' => $workflowRequest->id,
                'user_id' => $user->id,
                'action' => 'create',
                'to_status' => 'submitted',
                'comment' => "Created IT Helpdesk request {$workflowRequest->document_number} from legacy ticket shortcut",
            ]);

            return $workflowRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function payloadFromLegacyTicketInput(array $data): array
    {
        $requestType = (string) ($data['request_type'] ?? 'general');
        $payload = [
            'ประเภทคำขอเดิม' => $requestType,
            'ปัญหา/รายละเอียด' => (string) ($data['details'] ?? ''),
        ];

        if (isset(self::REQUEST_TYPE_FIELD_MAP[$requestType])) {
            $payload[self::REQUEST_TYPE_FIELD_MAP[$requestType]] = 'on';
        }

        if (! empty($data['legacy_document_ref'])) {
            $payload['เลขอ้างอิงเอกสารเดิม'] = (string) $data['legacy_document_ref'];
        }

        return array_filter($payload, fn (string $value) => $value !== '');
    }

    public function stepForStatus(WorkflowTemplate $template, string $status): ?WorkflowStep
    {
        $steps = $template->steps;

        return match ($status) {
            'accepted' => $steps->firstWhere('name', 'Accept Case') ?? $steps->skip(1)->first() ?? $steps->first(),
            'in_progress' => $steps->first(fn (WorkflowStep $step) => str_contains(strtolower($step->name), 'resove') || str_contains(strtolower($step->name), 'resolve')) ?? $steps->last(),
            'completed', 'approved', 'rejected', 'cancelled' => null,
            default => $steps->first(),
        };
    }

    public function documentNumber(WorkflowRequest $workflowRequest): string
    {
        $date = ($workflowRequest->submitted_at ?: now())->format('Ymd');

        return 'WDC-SF-'.$date.'-'.str_pad((string) $workflowRequest->id, 5, '0', STR_PAD_LEFT);
    }
}
