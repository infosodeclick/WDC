<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REQUEST_TYPE_LABELS = [
        'general' => 'ปัญหา/รายละเอียดทั่วไป',
        'cancel_document' => 'แจ้งยกเลิกเอกสาร',
        'vpn_access' => 'แจ้งขอใช้งาน VPN',
        'sap_b1' => 'แจ้งปัญหาโปรแกรม SAP B1',
        'ai_crm' => 'แจ้งปัญหาโปรแกรม AI-CRM',
        'remote_access' => 'ขอเข้าถึง/แก้ไข database หรือ Remote Access',
    ];

    private const REQUEST_TYPE_FIELD_MAP = [
        'cancel_document' => 'แจ้งยกเลิกเอกสาร(โปรดระบุเลข Document Ref ด้วย)',
        'vpn_access' => 'แจ้งขอใช้งาน VPN (โปรดระบุวัตถุประสงค์ในการใช้งาน)',
        'sap_b1' => 'แจ้งปัญหาโปรแกรม SAP B1',
        'ai_crm' => 'แจ้งปัญหาโปรแกรม AI-CRM',
        'remote_access' => 'ขอเข้าถึง,แก้ไขข้อมูล,database/ขอ Remote Access กับเครื่องพนักงานในองค์กร',
    ];

    public function up(): void
    {
        DB::table('permissions')->where('key', 'tickets.create')->update([
            'name' => 'เปิดคำขอ IT',
            'description' => 'แจ้งปัญหา IT ผ่าน SmartFlow Workflow และติดตามงานของตนเอง',
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('key', 'tickets.manage')->update([
            'name' => 'ดูแล Helpdesk',
            'description' => 'เห็นคิว IT Helpdesk และ legacy ticket ตามขอบเขตข้อมูล',
            'updated_at' => now(),
        ]);

        $template = DB::table('workflow_templates')
            ->where('source_system', 'smartflow')
            ->where('legacy_workflow_id', '7')
            ->first();

        if (! $template) {
            return;
        }

        $steps = DB::table('workflow_steps')
            ->where('workflow_template_id', $template->id)
            ->orderBy('step_order')
            ->get();

        DB::table('tickets')->orderBy('id')->each(function (object $ticket) use ($template, $steps) {
            $status = $this->mapStatus($ticket->status);
            $submittedAt = $ticket->created_at ? Carbon::parse($ticket->created_at) : now();
            $completedAt = $status === 'completed'
                ? ($ticket->completed_at ? Carbon::parse($ticket->completed_at) : Carbon::parse($ticket->updated_at ?? now()))
                : null;

            $attributes = [
                'workflow_template_id' => $template->id,
                'requester_id' => $ticket->reporter_id,
                'current_step_id' => $this->stepIdForStatus($steps, $status),
                'assigned_to' => $ticket->assigned_to,
                'smartflow_menu' => $template->smartflow_menu ?: 'Your Tasks',
                'title' => $ticket->title,
                'details' => $ticket->details,
                'form_payload' => json_encode($this->payloadFromTicket($ticket), JSON_UNESCAPED_UNICODE),
                'assigned_group' => $template->service_team,
                'priority' => $ticket->urgency ?: 'normal',
                'status' => $status,
                'legacy_reference' => $ticket->legacy_document_ref,
                'external_source' => 'wdc_ticket',
                'external_record_id' => (string) $ticket->id,
                'external_payload' => json_encode([
                    'legacy_ticket_id' => $ticket->id,
                    'request_type' => $ticket->request_type,
                    'urgency' => $ticket->urgency,
                    'status' => $ticket->status,
                    'image_path' => $ticket->image_path,
                ], JSON_UNESCAPED_UNICODE),
                'submitted_at' => $submittedAt,
                'completed_at' => $completedAt,
                'imported_at' => now(),
                'created_at' => $ticket->created_at ?? now(),
                'updated_at' => now(),
            ];

            $existing = DB::table('workflow_requests')
                ->where('external_source', 'wdc_ticket')
                ->where('external_record_id', (string) $ticket->id)
                ->first();

            if ($existing) {
                DB::table('workflow_requests')->where('id', $existing->id)->update($attributes);
                $workflowRequestId = $existing->id;
            } else {
                $workflowRequestId = DB::table('workflow_requests')->insertGetId([
                    ...$attributes,
                    'document_number' => null,
                ]);
            }

            DB::table('workflow_requests')->where('id', $workflowRequestId)->update([
                'document_number' => 'WDC-SF-'.$submittedAt->format('Ymd').'-'.str_pad((string) $workflowRequestId, 5, '0', STR_PAD_LEFT),
            ]);

            DB::table('workflow_request_events')->updateOrInsert(
                [
                    'workflow_request_id' => $workflowRequestId,
                    'action' => 'import_legacy_ticket',
                ],
                [
                    'user_id' => $ticket->reporter_id,
                    'to_status' => $status,
                    'comment' => "Migrated legacy WDC ticket #{$ticket->id} into IT Helpdesk workflow.",
                    'created_at' => $ticket->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('ticket_comments')
                ->where('ticket_id', $ticket->id)
                ->orderBy('id')
                ->each(function (object $comment) use ($workflowRequestId) {
                    DB::table('workflow_request_events')->updateOrInsert(
                        [
                            'workflow_request_id' => $workflowRequestId,
                            'action' => "legacy_ticket_comment_{$comment->id}",
                        ],
                        [
                            'user_id' => $comment->user_id,
                            'comment' => $comment->body,
                            'created_at' => $comment->created_at ?? now(),
                            'updated_at' => now(),
                        ],
                    );
                });
        });
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'tickets.create')->update([
            'name' => 'เปิด Ticket',
            'description' => 'แจ้งปัญหา IT และติดตามงานของตนเอง',
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('key', 'tickets.manage')->update([
            'name' => 'จัดการ Ticket',
            'description' => 'เห็นและอัปเดต Ticket ตามขอบเขตข้อมูล',
            'updated_at' => now(),
        ]);

        $ids = DB::table('workflow_requests')
            ->where('external_source', 'wdc_ticket')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('workflow_request_attachments')->whereIn('workflow_request_id', $ids)->delete();
        DB::table('workflow_request_events')->whereIn('workflow_request_id', $ids)->delete();
        DB::table('workflow_requests')->whereIn('id', $ids)->delete();
    }

    private function mapStatus(?string $status): string
    {
        return match ($status) {
            'accepted' => 'accepted',
            'in_progress' => 'in_progress',
            'done' => 'completed',
            default => 'submitted',
        };
    }

    private function stepIdForStatus($steps, string $status): ?int
    {
        if (in_array($status, ['completed', 'approved', 'rejected', 'cancelled'], true)) {
            return null;
        }

        if ($status === 'accepted') {
            return $steps->firstWhere('name', 'Accept Case')?->id ?? $steps->skip(1)->first()?->id ?? $steps->first()?->id;
        }

        if ($status === 'in_progress') {
            return $steps->first(fn (object $step) => str_contains(strtolower($step->name), 'resove') || str_contains(strtolower($step->name), 'resolve'))?->id ?? $steps->last()?->id;
        }

        return $steps->first()?->id;
    }

    private function payloadFromTicket(object $ticket): array
    {
        $payload = [
            'ประเภทคำขอเดิม' => self::REQUEST_TYPE_LABELS[$ticket->request_type] ?? $ticket->request_type,
            'ปัญหา/รายละเอียด' => $ticket->details,
        ];

        if (isset(self::REQUEST_TYPE_FIELD_MAP[$ticket->request_type])) {
            $payload[self::REQUEST_TYPE_FIELD_MAP[$ticket->request_type]] = 'on';
        }

        if ($ticket->legacy_document_ref) {
            $payload['เลขอ้างอิงเอกสารเดิม'] = $ticket->legacy_document_ref;
        }

        return array_filter($payload);
    }
};
