<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        return view('admin.index', [
            'users' => User::with('role', 'employee.department')->orderBy('employee_code')->get(),
            'roles' => Role::orderBy('id')->get(),
            'departments' => Department::orderBy('name')->get(),
            'logs' => ActivityLog::with('user')->latest()->take(30)->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:users,employee_code'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'position' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
        ]);

        $user = User::create([
            'employee_code' => $data['employee_code'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'is_active' => true,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'department_id' => $data['department_id'],
            'position' => $data['position'],
            'phone' => $data['phone'] ?? null,
            'start_date' => $data['start_date'] ?? null,
        ]);

        $this->log($request, 'create_user', User::class, $user->id, "Created {$user->employee_code}");

        return back()->with('status', 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function updateUser(User $user, Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'role_id' => $data['role_id'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->log($request, 'update_user', User::class, $user->id, "Updated {$user->employee_code}");

        return back()->with('status', 'อัปเดตผู้ใช้งานแล้ว');
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
