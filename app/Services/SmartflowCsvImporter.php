<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SmartflowCsvImporter
{
    private const KNOWN_KEYS = [
        'document_number',
        'document_no',
        'doc_no',
        'workflow',
        'workflow_name',
        'workflow_id',
        'template',
        'title',
        'subject',
        'details',
        'description',
        'requester',
        'requester_name',
        'requester_email',
        'requester_employee_code',
        'employee_code',
        'status',
        'priority',
        'submitted_at',
        'created_at',
        'completed_at',
        'closed_at',
        'due_at',
        'assigned_to',
        'assigned_email',
        'assigned_employee_code',
        'assigned_group',
        'current_step',
        'smartflow_menu',
        'legacy_reference',
        'ref',
        'reference',
        'external_url',
        'url',
        'attachments',
        'attachment_url',
        'file_url',
    ];

    /**
     * @return array{total:int,created:int,updated:int,skipped:int,attachments:int,errors:array<int,string>,dry_run:bool}
     */
    public function import(string $path, User $actor, array $options = []): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read SmartFlow import file: {$path}");
        }

        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'attachments' => 0,
            'errors' => [],
            'dry_run' => (bool) ($options['dry_run'] ?? false),
        ];

        $rows = $this->readCsv($path);
        $defaultRequester = $options['default_requester'] ?? $actor;

        if ($stats['dry_run']) {
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $index => $row) {
                $stats['total']++;

                try {
                    $result = $this->importRow($row, $actor, $defaultRequester);
                    $stats[$result['created'] ? 'created' : 'updated']++;
                    $stats['attachments'] += $result['attachments'];
                } catch (RuntimeException $exception) {
                    $stats['skipped']++;
                    $stats['errors'][] = 'Row '.($index + 2).': '.$exception->getMessage();
                }
            }

            if ($stats['dry_run']) {
                DB::rollBack();
            }
        } catch (\Throwable $throwable) {
            if ($stats['dry_run'] && DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $throwable;
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException('Cannot open CSV file.');
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers) || $headers === []) {
            fclose($handle);
            throw new RuntimeException('CSV file has no header row.');
        }

        $headers = array_map(fn ($header) => $this->normalizeKey((string) $header), $headers);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }

            $row = [];

            foreach ($headers as $position => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($values[$position] ?? ''));
            }

            if (collect($row)->filter()->isEmpty()) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array{created:bool,attachments:int}
     */
    private function importRow(array $row, User $actor, User $defaultRequester): array
    {
        $workflowName = $this->value($row, ['workflow', 'workflow_name', 'template', 'ประเภทเอกสาร', 'workflow_']);
        $workflowId = $this->value($row, ['workflow_id', 'legacy_workflow_id', 'form_id', 'id']);
        $documentNumber = $this->value($row, ['document_number', 'document_no', 'doc_no', 'เลขเอกสาร', 'เลขที่เอกสาร']);
        $legacyReference = $this->value($row, ['legacy_reference', 'ref', 'reference', 'smartflow_ref', 'เลขอ้างอิง']) ?: $documentNumber;

        if ($workflowName === '' && $workflowId === '' && $documentNumber === '') {
            throw new RuntimeException('Missing workflow name/id and document number.');
        }

        $template = $this->resolveTemplate($workflowName, $workflowId);
        $requester = $this->resolveUser($row, [
            'requester_employee_code',
            'employee_code',
            'requester_email',
            'requester',
            'requester_name',
        ]) ?: $defaultRequester;
        $assignee = $this->resolveUser($row, [
            'assigned_employee_code',
            'assigned_email',
            'assigned_to',
        ]);
        $status = $this->normalizeStatus($this->value($row, ['status', 'สถานะ']));
        $priority = $this->normalizePriority($this->value($row, ['priority', 'urgency', 'ความเร่งด่วน']));
        $submittedAt = $this->parseDate($this->value($row, ['submitted_at', 'created_at', 'วันที่สร้าง', 'วันที่ส่ง'])) ?: now();
        $completedAt = $this->parseDate($this->value($row, ['completed_at', 'closed_at', 'วันที่เสร็จ', 'วันที่ปิด']));
        $dueAt = $this->parseDate($this->value($row, ['due_at', 'sla_due_at', 'กำหนด']));
        $currentStep = $this->resolveStep($template, $status, $this->value($row, ['current_step', 'step', 'ขั้นตอน']));
        $externalRecordId = $documentNumber ?: $legacyReference;

        $attributes = [
            'workflow_template_id' => $template->id,
            'requester_id' => $requester->id,
            'current_step_id' => $currentStep?->id,
            'assigned_to' => $assignee?->id,
            'document_number' => $documentNumber ?: null,
            'smartflow_menu' => $this->value($row, ['smartflow_menu', 'menu']) ?: ($template->smartflow_menu ?: 'All Documents'),
            'title' => $this->value($row, ['title', 'subject', 'เรื่อง']) ?: ($workflowName ?: "SmartFlow {$legacyReference}"),
            'details' => $this->value($row, ['details', 'description', 'รายละเอียด']) ?: 'Imported from SmartFlow export.',
            'form_payload' => $this->payload($row),
            'assigned_group' => $this->value($row, ['assigned_group', 'group', 'ทีมรับผิดชอบ']) ?: ($currentStep?->approver_group ?: $template->service_team),
            'priority' => $priority,
            'status' => $status,
            'legacy_reference' => $legacyReference ?: null,
            'external_source' => 'smartflow',
            'external_record_id' => $externalRecordId ?: null,
            'external_url' => $this->value($row, ['external_url', 'url', 'link']) ?: null,
            'external_payload' => $row,
            'due_at' => $dueAt ?: ($template->sla_hours ? $submittedAt->copy()->addHours($template->sla_hours) : null),
            'submitted_at' => $submittedAt,
            'completed_at' => in_array($status, ['approved', 'rejected', 'completed', 'cancelled'], true) ? ($completedAt ?: now()) : null,
            'imported_at' => now(),
        ];

        $existing = $this->findExistingRequest($documentNumber, $externalRecordId);
        $created = ! $existing;
        $workflowRequest = $existing ?: new WorkflowRequest();
        $workflowRequest->fill($attributes)->save();

        if (! $workflowRequest->document_number) {
            $workflowRequest->update([
                'document_number' => 'WDC-SF-'.$submittedAt->format('Ymd').'-'.str_pad((string) $workflowRequest->id, 5, '0', STR_PAD_LEFT),
            ]);
        }

        $workflowRequest->events()->create([
            'user_id' => $actor->id,
            'action' => $created ? 'smartflow_import' : 'smartflow_import_update',
            'to_status' => $status,
            'comment' => 'Imported from SmartFlow CSV export.',
        ]);

        $attachmentCount = $this->syncAttachments($workflowRequest, $row, $actor);

        return ['created' => $created, 'attachments' => $attachmentCount];
    }

    private function findExistingRequest(string $documentNumber, string $externalRecordId): ?WorkflowRequest
    {
        if ($documentNumber === '' && $externalRecordId === '') {
            return null;
        }

        return WorkflowRequest::query()
            ->when($documentNumber !== '', fn ($query) => $query->orWhere('document_number', $documentNumber))
            ->when($externalRecordId !== '', fn ($query) => $query->orWhere(function ($query) use ($externalRecordId) {
                $query->where('external_source', 'smartflow')->where('external_record_id', $externalRecordId);
            }))
            ->first();
    }

    private function resolveTemplate(string $workflowName, string $workflowId): WorkflowTemplate
    {
        if ($workflowId !== '') {
            $template = WorkflowTemplate::where('source_system', 'smartflow')
                ->where('legacy_workflow_id', $workflowId)
                ->first();

            if ($template) {
                return $template;
            }
        }

        if ($workflowName !== '') {
            $template = WorkflowTemplate::where('name', $workflowName)->first();

            if ($template) {
                return $template;
            }
        }

        $template = WorkflowTemplate::create([
            'source_system' => 'smartflow',
            'legacy_workflow_id' => $workflowId ?: null,
            'name' => $workflowName ?: "SmartFlow Workflow {$workflowId}",
            'category' => 'SmartFlow Import',
            'description' => 'Imported workflow template from SmartFlow export.',
            'smartflow_menu' => 'All Documents',
            'service_team' => 'Imported Owner',
            'form_schema' => ['fields' => ['Requester', 'Reference', 'Original status']],
            'sla_hours' => 48,
            'approval_policy' => 'any_one',
            'legacy_url' => $workflowId ? "https://wdc.smartflow.pw/document/submit/{$workflowId}/" : null,
            'is_active' => true,
            'sort_order' => ((int) WorkflowTemplate::max('sort_order')) + 10,
        ]);

        $this->ensureDefaultSteps($template);

        return $template;
    }

    private function ensureDefaultSteps(WorkflowTemplate $template): void
    {
        if ($template->steps()->exists()) {
            return;
        }

        foreach ([
            [1, 'Submit Request', 'Requester', false],
            [2, 'Manager Review', 'Manager / Approver', false],
            [3, 'Complete Request', $template->service_team ?: 'Service Owner', true],
        ] as [$order, $name, $group, $requiresInput]) {
            $template->steps()->create([
                'step_order' => $order,
                'name' => $name,
                'mode' => 'any_one',
                'approver_group' => $group,
                'approver_hint' => $group,
                'condition_label' => null,
                'requires_input' => $requiresInput,
            ]);
        }
    }

    private function resolveStep(WorkflowTemplate $template, string $status, string $stepName): ?WorkflowStep
    {
        if (in_array($status, ['approved', 'rejected', 'completed', 'cancelled'], true)) {
            return null;
        }

        $steps = $template->steps()->orderBy('step_order')->get();

        if ($stepName !== '') {
            $step = $steps->first(fn (WorkflowStep $step) => Str::lower($step->name) === Str::lower($stepName));

            if ($step) {
                return $step;
            }
        }

        return match ($status) {
            'in_review' => $steps->skip(1)->first() ?? $steps->first(),
            'accepted' => $steps->firstWhere('name', 'Accept Case') ?? $steps->skip(1)->first() ?? $steps->first(),
            'in_progress' => $steps->firstWhere('name', 'Resolve Case') ?? $steps->skip(2)->first() ?? $steps->last(),
            'waiting_requester' => $steps->last(),
            default => $steps->first(),
        };
    }

    private function resolveUser(array $row, array $keys): ?User
    {
        $value = $this->value($row, $keys);

        if ($value === '') {
            return null;
        }

        return User::query()
            ->where('employee_code', $value)
            ->orWhere('email', $value)
            ->orWhere('name', $value)
            ->first();
    }

    private function payload(array $row): array
    {
        return collect($row)
            ->reject(fn ($value, $key) => in_array($key, self::KNOWN_KEYS, true))
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->all();
    }

    private function syncAttachments(WorkflowRequest $workflowRequest, array $row, User $actor): int
    {
        $raw = $this->value($row, ['attachments', 'attachment_url', 'file_url', 'ไฟล์แนบ']);

        if ($raw === '') {
            return 0;
        }

        $count = 0;
        $urls = preg_split('/[\r\n;|]+/', $raw) ?: [];

        foreach ($urls as $index => $url) {
            $url = trim($url);

            if ($url === '') {
                continue;
            }

            $workflowRequest->attachments()->updateOrCreate(
                ['file_url' => $url],
                [
                    'source_system' => 'smartflow',
                    'file_name' => basename(parse_url($url, PHP_URL_PATH) ?: $url),
                    'uploaded_by' => $actor->id,
                    'sort_order' => $index + 1,
                ],
            );

            $count++;
        }

        return $count;
    }

    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeKey($key);

            if (isset($row[$normalized]) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }

        return '';
    }

    private function normalizeKey(string $key): string
    {
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
        $key = Str::lower(trim($key));
        $key = str_replace([' ', '-', '.', '/', '\\', ':', '#'], '_', $key);
        $key = preg_replace('/_+/', '_', $key) ?? $key;

        return trim($key, '_');
    }

    private function normalizeStatus(string $status): string
    {
        $value = Str::lower(trim($status));

        return match (true) {
            $value === '' => 'submitted',
            str_contains($value, 'reject') || str_contains($value, 'ไม่อนุมัติ') => 'rejected',
            str_contains($value, 'cancel') || str_contains($value, 'ยกเลิก') => 'cancelled',
            str_contains($value, 'complete') || str_contains($value, 'done') || str_contains($value, 'เสร็จ') || str_contains($value, 'ปิด') => 'completed',
            str_contains($value, 'approve') || str_contains($value, 'อนุมัติ') => 'approved',
            str_contains($value, 'progress') || str_contains($value, 'ดำเนิน') => 'in_progress',
            str_contains($value, 'accept') || str_contains($value, 'รับเรื่อง') => 'accepted',
            str_contains($value, 'wait') || str_contains($value, 'รอ') => 'waiting_requester',
            str_contains($value, 'review') || str_contains($value, 'ตรวจ') => 'in_review',
            default => in_array($value, array_keys(WorkflowRequest::statusLabels()), true) ? $value : 'submitted',
        };
    }

    private function normalizePriority(string $priority): string
    {
        $value = Str::lower(trim($priority));

        return match (true) {
            str_contains($value, 'critical') || str_contains($value, 'urgent') || str_contains($value, 'วิกฤต') || str_contains($value, 'ด่วนมาก') => 'critical',
            str_contains($value, 'high') || str_contains($value, 'สูง') || str_contains($value, 'ด่วน') => 'high',
            str_contains($value, 'low') || str_contains($value, 'ต่ำ') => 'low',
            default => 'normal',
        };
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, 'Asia/Bangkok');
            } catch (\Throwable) {
                //
            }
        }

        try {
            return Carbon::parse($value, 'Asia/Bangkok');
        } catch (\Throwable) {
            return null;
        }
    }
}
