<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตั้งรหัสผ่านใหม่ WDC Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
<main class="login-screen auth-flow-screen">
    <section class="login-hero">
        <div class="brand-lockup login-brand">
            <span class="brand-mark">WDC</span>
            <span><strong>WDC Portal</strong></span>
        </div>
    </section>

    <section class="login-panel">
        <h2>ตั้งรหัสผ่านใหม่</h2>
        <p class="muted">กำหนดรหัสผ่านใหม่อย่างน้อย 8 ตัวอักษร แล้วเข้าสู่ระบบด้วยรหัสพนักงานตามเดิม</p>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('password.update') }}" class="stack-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>
                <span>อีเมลบริษัท</span>
                <input class="form-control" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            </label>
            <label>
                <span>รหัสผ่านใหม่</span>
                <input class="form-control" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
            </label>
            <label>
                <span>ยืนยันรหัสผ่านใหม่</span>
                <input class="form-control" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
            </label>
            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-shield-check"></i> บันทึกรหัสผ่านใหม่</button>
        </form>
    </section>
</main>
</body>
</html>
