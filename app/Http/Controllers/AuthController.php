<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'string', 'max:255'],
        ]);

        $account = trim($validated['account']);
        $user = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($account): void {
                $query->where('employee_code', $account)
                    ->orWhere('email', $account);
            })
            ->first();

        if ($user?->email) {
            $status = Password::sendResetLink(['email' => $user->email]);

            if ($status === Password::RESET_THROTTLED) {
                return back()->with('status', 'ระบบส่งลิงก์ไปแล้ว กรุณารอสักครู่ก่อนขอใหม่อีกครั้ง');
            }
        }

        return back()->with('status', 'ถ้าบัญชีนี้มีอีเมลในระบบ ระบบจะส่งลิงก์รีเซ็ตรหัสผ่านให้ทางอีเมลบริษัท');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('role', 'employee.department')
            ->where('employee_code', $credentials['employee_code'])
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withErrors(['employee_code' => 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง'])
                ->onlyInput('employee_code');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => 'User logged in',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'password_reset',
                    'description' => 'User reset password from email link',
                    'ip_address' => request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว'])
                ->withInput($request->only('email'));
        }

        return redirect()->route('login')->with('status', 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่');
    }

    public function logout(Request $request): RedirectResponse
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'logout',
            'description' => 'User logged out',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
