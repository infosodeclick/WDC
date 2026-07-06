<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ลืมรหัสผ่าน WDC Portal</title>
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
        <a class="auth-back-link" href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> กลับหน้าเข้าสู่ระบบ</a>
        <h2>ลืมรหัสผ่าน</h2>
        <p class="muted">กรอกรหัสพนักงานหรืออีเมลบริษัท ระบบจะส่งลิงก์รีเซ็ตให้ทางอีเมลที่ผูกกับบัญชี</p>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('password.email') }}" class="stack-form">
            @csrf
            <label>
                <span>รหัสพนักงานหรืออีเมลบริษัท</span>
                <input class="form-control" name="account" value="{{ old('account') }}" autocomplete="username" required autofocus>
            </label>
            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-envelope-paper"></i> ส่งลิงก์รีเซ็ตรหัสผ่าน</button>
        </form>
    </section>
</main>
</body>
</html>
