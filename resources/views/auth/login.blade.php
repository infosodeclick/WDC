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
                <strong>Employee Portal</strong>
                <small>Company news, HR, IT Helpdesk, Knowledge Base</small>
            </span>
        </div>
        <h1>ระบบพนักงานกลาง สำหรับบริษัท 200 คน</h1>
        <p>รวมโปรไฟล์ ข่าวสาร คู่มือวิดีโอ Helpdesk เรื่องร้องเรียน และเอกสาร HR ไว้ในเว็บเดียวด้วยสิทธิ์ Employee, Supervisor, HR และ Admin</p>
        <div class="login-metrics">
            <span><strong>3</strong> Portals</span>
            <span><strong>4</strong> Roles</span>
            <span><strong>12+</strong> Tables</span>
        </div>
    </section>

    <section class="login-panel">
        <h2>เข้าสู่ระบบ</h2>
        <p class="muted">ใช้รหัสพนักงานและรหัสผ่าน</p>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('login.store') }}" class="stack-form">
            @csrf
            <label>
                <span>รหัสพนักงาน</span>
                <input class="form-control" name="employee_code" value="{{ old('employee_code', 'EMP00125') }}" autocomplete="username" required autofocus>
            </label>
            <label>
                <span>รหัสผ่าน</span>
                <input class="form-control" name="password" type="password" value="password123" autocomplete="current-password" required>
            </label>
            <label class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" value="1">
                <span class="form-check-label">จดจำการเข้าสู่ระบบ</span>
            </label>
            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</button>
        </form>

        <div class="demo-users">
            <div>บัญชีตัวอย่าง</div>
            <code>EMP00125</code> Employee
            <code>EMP00200</code> Supervisor / IT
            <code>EMP01000</code> HR
            <code>EMP09999</code> Admin
            <span>รหัสผ่านทุกบัญชี: <code>password123</code></span>
        </div>
    </section>
</main>
</body>
</html>
