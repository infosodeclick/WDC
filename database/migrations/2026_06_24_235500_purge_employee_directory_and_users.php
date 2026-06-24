<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        $adminId = DB::table('users')->where('employee_code', 'administrator')->value('id');

        if (! $adminId) {
            throw new RuntimeException('Cannot purge employee data because administrator user was not found.');
        }

        DB::transaction(function () use ($adminId) {
            $oldUserIds = DB::table('users')
                ->where('id', '<>', $adminId)
                ->pluck('id')
                ->all();

            if ($oldUserIds === []) {
                $this->deleteAll('employee_directory_entries');
                $this->deleteAll('employee_onboarding_systems');
                $this->deleteAll('employee_onboarding_requests');

                return;
            }

            $oldEmployeeIds = Schema::hasTable('employees')
                ? DB::table('employees')->whereIn('user_id', $oldUserIds)->pluck('id')->all()
                : [];

            $this->deleteAll('employee_onboarding_systems');
            $this->deleteAll('employee_onboarding_requests');
            $this->deleteAll('employee_directory_entries');

            $this->deleteWhereIn('announcement_reads', 'user_id', $oldUserIds);
            $this->deleteWhereIn('notifications', 'user_id', $oldUserIds);
            $this->deleteWhereIn('profile_change_requests', 'user_id', $oldUserIds);
            $this->updateWhereIn('profile_change_requests', 'reviewed_by', $oldUserIds, ['reviewed_by' => null]);

            $this->deleteWhereIn('meeting_room_bookings', 'user_id', $oldUserIds);
            $this->updateWhereIn('meeting_room_bookings', 'cancelled_by', $oldUserIds, ['cancelled_by' => null]);

            if (Schema::hasTable('tickets')) {
                $ticketIds = DB::table('tickets')
                    ->whereIn('reporter_id', $oldUserIds)
                    ->pluck('id')
                    ->all();

                $this->deleteWhereIn('ticket_comments', 'user_id', $oldUserIds);
                $this->deleteWhereIn('ticket_comments', 'ticket_id', $ticketIds);
                $this->deleteWhereIn('tickets', 'id', $ticketIds);
                $this->updateWhereIn('tickets', 'assigned_to', $oldUserIds, ['assigned_to' => null]);
            }

            if (Schema::hasTable('workflow_requests')) {
                $workflowRequestIds = DB::table('workflow_requests')
                    ->whereIn('requester_id', $oldUserIds)
                    ->pluck('id')
                    ->all();

                $this->deleteWhereIn('workflow_request_events', 'workflow_request_id', $workflowRequestIds);
                $this->deleteWhereIn('workflow_request_attachments', 'workflow_request_id', $workflowRequestIds);
                $this->deleteWhereIn('workflow_requests', 'id', $workflowRequestIds);
                $this->updateWhereIn('workflow_request_events', 'user_id', $oldUserIds, ['user_id' => null]);
                $this->updateWhereIn('workflow_request_attachments', 'uploaded_by', $oldUserIds, ['uploaded_by' => null]);
            }

            $this->updateWhereIn('complaints', 'reporter_id', $oldUserIds, ['reporter_id' => null]);
            $this->updateWhereIn('complaints', 'assigned_to', $oldUserIds, ['assigned_to' => null]);

            $this->updateWhereIn('announcements', 'created_by', $oldUserIds, ['created_by' => $adminId]);
            $this->updateWhereIn('knowledge_articles', 'author_id', $oldUserIds, ['author_id' => $adminId]);
            $this->updateWhereIn('knowledge_videos', 'author_id', $oldUserIds, ['author_id' => $adminId]);

            $this->deleteWhereIn('employee_documents', 'employee_id', $oldEmployeeIds);
            $this->updateWhereIn('employee_documents', 'created_by', $oldUserIds, ['created_by' => $adminId]);

            $this->updateWhereIn('it_assets', 'owner_id', $oldUserIds, ['owner_id' => null, 'owner_name' => null]);
            $this->updateWhereIn('asset_inspection_documents', 'created_by', $oldUserIds, ['created_by' => null]);
            $this->updateWhereIn('asset_audit_logs', 'user_id', $oldUserIds, ['user_id' => null]);

            $this->updateWhereIn('activity_logs', 'user_id', $oldUserIds, ['user_id' => null]);
            $this->deleteWhereIn('permission_user', 'user_id', $oldUserIds);
            $this->deleteWhereIn('sessions', 'user_id', $oldUserIds);

            $this->deleteWhereIn('employees', 'user_id', $oldUserIds);
            $this->deleteWhereIn('users', 'id', $oldUserIds);
        });
    }

    public function down(): void
    {
        //
    }

    private function deleteAll(string $table): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->delete();
        }
    }

    private function deleteWhereIn(string $table, string $column, array $values): void
    {
        if ($values === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $values)->delete();
    }

    private function updateWhereIn(string $table, string $column, array $values, array $updates): void
    {
        if ($values === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $values)->update($updates);
    }
};
