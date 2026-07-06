<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ WDC Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
<main class="login-screen">
    <section class="login-hero">
        <div class="brand-lockup login-brand">
            <span class="brand-mark">WDC</span>
            <span>
                <strong>WDC Portal</strong>
            </span>
        </div>
    </section>

    <section class="login-panel">
        <h2>เข้าสู่ระบบ</h2>
        <p class="muted">ใช้รหัสพนักงานและรหัสผ่าน</p>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('login.store') }}" class="stack-form">
            @csrf
            <label>
                <span>รหัสพนักงาน</span>
                <input class="form-control" name="employee_code" value="{{ old('employee_code') }}" autocomplete="username" required autofocus>
            </label>
            <label>
                <span>รหัสผ่าน</span>
                <input class="form-control" name="password" type="password" autocomplete="current-password" required>
            </label>
            <label class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" value="1">
                <span class="form-check-label">จดจำการเข้าสู่ระบบ</span>
            </label>
            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</button>
            <a class="auth-help-link" href="{{ route('password.request') }}">ลืมรหัสผ่าน?</a>
        </form>
    </section>
</main>
</body>
</html>
