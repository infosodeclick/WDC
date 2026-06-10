@extends('layouts.app')

@section('title', 'ศูนย์รวมระบบ | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Unified Access Center</p>
        <h1>ศูนย์รวมระบบ WDC</h1>
        <p>ให้ WDC Portal เป็นจุดเริ่มต้นเดียวของพนักงาน แล้วค่อยเชื่อมไปยังระบบเดิมเฉพาะงานที่ยังต้องใช้ระหว่างย้ายข้อมูล</p>
    </div>
    <div class="role-badge">One Portal</div>
</div>

<section class="panel">
    <h2>แนวทางลดความยุ่งยาก</h2>
    <div class="integration-steps">
        <div>
            <strong>1. เข้าจาก WDC Portal ก่อน</strong>
            <span>พนักงานใช้รหัสพนักงานและรหัสผ่านเดียวสำหรับข่าวสาร โปรไฟล์ เอกสาร Ticket และเมนูทางลัด</span>
        </div>
        <div>
            <strong>2. ระบบเดิมยังเปิดผ่านลิงก์</strong>
            <span>Notion, SmartFlow และ Payroll ยังใช้งานได้ตามเดิมระหว่าง migration โดยไม่บังคับให้พนักงานจำหลายหน้า</span>
        </div>
        <div>
            <strong>3. ย้ายข้อมูลทีละส่วน</strong>
            <span>เริ่มจาก directory และ helpdesk ก่อน แล้วค่อยย้าย workflow อื่นตามเจ้าของข้อมูลและขั้นตอนอนุมัติที่ยืนยันแล้ว</span>
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
    <div class="section-title">
        <h2>หลักการทำงานที่ดึงจากระบบเดิม</h2>
        <span class="muted">{{ $snapshots->count() }} snapshot</span>
    </div>
    <div class="snapshot-grid">
        @foreach($snapshots as $snapshot)
            <article class="snapshot-card">
                <div class="meta-row">
                    <span class="tag">{{ strtoupper($snapshot->source_system) }}</span>
                    <span>{{ $snapshot->captured_at?->format('d/m/Y H:i') }}</span>
                </div>
                <h3>{{ $snapshot->title }}</h3>
                <p>{{ $snapshot->summary }}</p>

                @if(($snapshot->payload['observed_breakdown'] ?? null))
                    <div class="snapshot-stat-row">
                        @foreach($snapshot->payload['observed_breakdown'] as $label => $value)
                            <span><strong>{{ $value }}</strong>{{ str_replace('_', ' ', $label) }}</span>
                        @endforeach
                    </div>
                @endif

                @if(($snapshot->payload['main_menus'] ?? null))
                    <div class="snapshot-list">
                        <strong>เมนูหลัก SmartFlow</strong>
                        <span>{{ collect(array_keys($snapshot->payload['main_menus']))->join(', ') }}</span>
                    </div>
                @endif

                @if(($snapshot->payload['workflow_templates'] ?? null))
                    <div class="snapshot-list">
                        <strong>Workflow ที่พบ</strong>
                        <span>{{ collect($snapshot->payload['workflow_templates'])->pluck('name')->join(', ') }}</span>
                    </div>
                @endif

                @if($snapshot->source_url)
                    <a class="source-link" href="{{ $snapshot->source_url }}" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right"></i> เปิดระบบต้นทาง
                    </a>
                @endif
            </article>
        @endforeach
    </div>
</section>

<section class="panel">
    <h2>สิ่งที่ระบบใหม่จะรับช่วงต่อ</h2>
    <div class="item-list">
        <div class="list-card compact">
            <h3>Employee Directory จาก Notion</h3>
            <p>รองรับชื่อไทย/อังกฤษ ชื่อเล่น BU ทีม สาขา อีเมล เบอร์ต่อ รูปพนักงาน และกลุ่มอีเมล ผ่าน importer `portal:import-notion-directory`</p>
        </div>
        <div class="list-card compact">
            <h3>IT Helpdesk จาก SmartFlow</h3>
            <p>คงประเภทคำขอเดิม เช่น VPN, SAP B1, AI-CRM, Remote Access และเลขอ้างอิงเอกสาร พร้อมเตรียม flow รับเรื่องและปิดงานใน WDC</p>
        </div>
        <div class="list-card compact">
            <h3>Payroll</h3>
            <p>ยังใช้ระบบเงินเดือนเดิมผ่านปุ่มลิงก์เท่านั้น เพื่อไม่เก็บข้อมูลเงินเดือนหรือเลขบัตรประชาชนใน WDC Portal</p>
        </div>
    </div>
</section>
@endsection
