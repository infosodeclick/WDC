@extends('layouts.app')

@section('title', 'ศูนย์รวมระบบ | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Unified Access Center</p>
        <h1>ศูนย์รวมระบบ WDC</h1>
        <p>เริ่มจาก WDC Portal เป็นประตูหลัก แล้วเชื่อมไปยังระบบเดิมโดยไม่บังคับให้พนักงานจำหลายลิงก์</p>
    </div>
    <div class="role-badge">One Portal</div>
</div>

<section class="panel">
    <h2>แนวทางลดความยุ่งยาก</h2>
    <div class="integration-steps">
        <div>
            <strong>1. เข้า WDC Portal ก่อน</strong>
            <span>พนักงานใช้รหัสพนักงานและรหัสผ่านเดียวสำหรับข่าวสาร โปรไฟล์ เอกสาร Ticket และเมนูทางลัด</span>
        </div>
        <div>
            <strong>2. ระบบเดิมยังเปิดผ่านลิงก์</strong>
            <span>Notion, SmartFlow และ Payroll ยังใช้งานได้ตามเดิมระหว่างรอ migrate ข้อมูลและ workflow</span>
        </div>
        <div>
            <strong>3. ย้ายข้อมูลเข้าระบบใหม่ทีละส่วน</strong>
            <span>เริ่มจาก directory และ helpdesk fields ก่อน จากนั้นค่อยต่อ API/import หรือแทนที่ workflow เดิม</span>
        </div>
    </div>
</section>

<div class="system-grid">
    @foreach($systems as $system)
        @php($account = $system->accounts->first())
        @php($statusLabels = ['primary' => 'ระบบหลัก', 'bridge' => 'เชื่อมต่อเดิม', 'external' => 'ระบบภายนอก', 'active' => 'ใช้งาน'])
        <article class="system-card">
            <div class="meta-row">
                <span class="tag">{{ $system->category }}</span>
                <span class="status-pill status-{{ $system->status }}">{{ $statusLabels[$system->status] ?? $system->status }}</span>
            </div>
            <h2>{{ $system->name }}</h2>
            <p>{{ $system->summary }}</p>

            <dl class="mini-detail-list">
                <dt>วิธีเข้าใช้</dt>
                <dd>{{ $system->login_method }}</dd>
                <dt>บัญชีของฉัน</dt>
                <dd>{{ $account?->login_identifier ?? 'ยังไม่ได้ผูกบัญชี' }}</dd>
                @if($account?->credential_note)
                    <dt>หมายเหตุ</dt>
                    <dd>{{ $account->credential_note }}</dd>
                @endif
            </dl>

            <a class="btn btn-outline-primary" href="{{ str_starts_with($system->url, '/') ? url($system->url) : $system->url }}" target="{{ str_starts_with($system->url, '/') ? '_self' : '_blank' }}" rel="noopener">
                <i class="bi bi-box-arrow-up-right"></i> เปิดระบบ
            </a>
        </article>
    @endforeach
</div>

<section class="panel">
    <h2>สิ่งที่ระบบใหม่จะรับช่วงต่อ</h2>
    <div class="item-list">
        <div class="list-card compact">
            <h3>Employee Directory จาก Notion</h3>
            <p>รองรับชื่อไทย/อังกฤษ ชื่อเล่น BU ทีม สาขา เบอร์ต่อ และกลุ่มอีเมล เพื่อให้ HR ย้ายข้อมูลเข้าฐานข้อมูล WDC ได้</p>
        </div>
        <div class="list-card compact">
            <h3>IT Helpdesk จาก SmartFlow</h3>
            <p>แบบฟอร์ม Ticket ใหม่เพิ่มประเภทคำขอให้ตรงกับ SmartFlow เช่น VPN, SAP B1, AI-CRM, Remote Access และเลขอ้างอิงเอกสาร</p>
        </div>
        <div class="list-card compact">
            <h3>Payroll</h3>
            <p>ยังใช้ระบบเงินเดือนเดิมและเปิดผ่านปุ่มลิงก์เท่านั้น เพื่อไม่เก็บข้อมูลเงินเดือนหรือเลขบัตรประชาชนใน WDC Portal</p>
        </div>
    </div>
</section>
@endsection
