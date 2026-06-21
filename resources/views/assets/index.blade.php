@extends('layouts.app')

@section('title', 'ทรัพย์สิน IT | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">IT Asset Management</p>
        <h1>ทรัพย์สิน IT</h1>
        <p>ทะเบียนทรัพย์สิน ตรวจนับสถานที่ พิมพ์ QR และรายงานสำหรับทีม IT ใน WDC Portal</p>
    </div>
    <div class="button-row">
        @if($canExportAssets)
            <a class="btn btn-outline-primary" href="{{ route('assets.export') }}"><i class="bi bi-filetype-csv"></i> Export CSV</a>
        @endif
        @if($canManageAssets)
            <a class="btn btn-primary" href="#new-asset"><i class="bi bi-plus-circle"></i> เพิ่มทรัพย์สิน</a>
        @endif
    </div>
</div>

<div class="metric-grid asset-metric-grid">
    <div class="metric-card"><span>ทรัพย์สินทั้งหมด</span><strong>{{ number_format($assetCount) }}</strong><small>รายการในทะเบียน WDC</small></div>
    <div class="metric-card"><span>ใช้งานอยู่</span><strong>{{ number_format($activeCount) }}</strong><small>พร้อมใช้งาน</small></div>
    <div class="metric-card"><span>ส่งซ่อม</span><strong>{{ number_format($repairCount) }}</strong><small>รอทีม IT ติดตาม</small></div>
    <div class="metric-card"><span>สูญหาย</span><strong>{{ number_format($lostCount) }}</strong><small>ต้องตรวจสอบ</small></div>
    <div class="metric-card"><span>มูลค่ารวม</span><strong>{{ number_format($totalValue, 0) }}</strong><small>บาท ตามราคาทุน</small></div>
</div>

<form class="panel asset-filter" method="get" action="{{ route('assets.index') }}">
    <div class="form-grid">
        <label class="span-2">ค้นหา
            <input class="form-control" name="q" value="{{ $q }}" placeholder="รหัส ชื่อ รุ่น serial ผู้ถือครอง แผนก">
        </label>
        <label>สถานะ
            <select class="form-select" name="status">
                <option value="">ทุกสถานะ</option>
                @foreach(['active' => 'ใช้งานอยู่', 'repair' => 'ส่งซ่อม', 'lost' => 'สูญหาย', 'retired' => 'จำหน่าย/เลิกใช้'] as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>หมวดหมู่
            <select class="form-select" name="category_id">
                <option value="">ทุกหมวดหมู่</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->code }} - {{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label>สถานที่
            <select class="form-select" name="location_id">
                <option value="">ทุกสถานที่</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->code }} - {{ $location->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
    </div>
</form>

@if($canManageAssets)
    <section class="panel" id="new-asset">
        <div class="section-title">
            <h2>เพิ่มทรัพย์สิน IT</h2>
            <span class="tag">Asset Registry</span>
        </div>
        <form class="form-grid" method="post" action="{{ route('assets.store') }}">
            @csrf
            <label>รหัสทรัพย์สิน
                <input class="form-control" name="code" required placeholder="เช่น WDC-NB-0004">
            </label>
            <label class="span-2">ชื่อรายการ
                <input class="form-control" name="name" required placeholder="เช่น Notebook Dell Latitude">
            </label>
            <label>หมวดหมู่
                <select class="form-select" name="asset_category_id">
                    <option value="">ไม่ระบุ</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->code }} - {{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>สถานที่
                <select class="form-select" name="asset_location_id">
                    <option value="">ไม่ระบุ</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>สถานะ
                <select class="form-select" name="status">
                    <option value="active">ใช้งานอยู่</option>
                    <option value="repair">ส่งซ่อม</option>
                    <option value="lost">สูญหาย</option>
                    <option value="retired">จำหน่าย/เลิกใช้</option>
                </select>
            </label>
            <label>บริษัท
                <input class="form-control" name="company" value="WDC">
            </label>
            <label>แผนก
                <input class="form-control" name="department" placeholder="IT, Accounting, Warehouse">
            </label>
            <label>ผู้ถือครอง
                <input class="form-control" name="owner_name" placeholder="ชื่อพนักงานหรือทีม">
            </label>
            <label>Brand
                <input class="form-control" name="brand" placeholder="Dell, HP, TP-Link">
            </label>
            <label>Model
                <input class="form-control" name="model">
            </label>
            <label>Serial Number
                <input class="form-control" name="serial_number">
            </label>
            <label>ราคาทุน
                <input class="form-control" name="price" type="number" min="0" step="0.01">
            </label>
            <label>มูลค่าตามบัญชี
                <input class="form-control" name="book_value" type="number" min="0" step="0.01">
            </label>
            <label>วันซื้อ
                <input class="form-control" name="purchased_at" type="date">
            </label>
            <label>หมดประกัน
                <input class="form-control" name="warranty_until" type="date">
            </label>
            <label class="span-3">หมายเหตุ
                <textarea class="form-control" name="notes" rows="3" placeholder="ข้อมูลการใช้งาน การซ่อม หรือการโอนย้าย"></textarea>
            </label>
            <div class="span-3 button-row">
                <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> บันทึกทรัพย์สิน</button>
            </div>
        </form>
    </section>
@endif

<section class="panel">
    <div class="section-title">
        <h2>ทะเบียนทรัพย์สิน</h2>
        <span class="tag">{{ number_format($assets->total()) }} รายการ</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle asset-table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>รายการ</th>
                    <th>สถานที่</th>
                    <th>ผู้ถือครอง</th>
                    <th>มูลค่า</th>
                    <th>สถานะ</th>
                    @if($canManageAssets)<th>อัปเดต</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($assets as $asset)
                    <tr>
                        <td><strong>{{ $asset->code }}</strong><small class="d-block muted">{{ $asset->serial_number ?: 'No serial' }}</small></td>
                        <td>
                            <strong>{{ $asset->name }}</strong>
                            <small class="d-block muted">{{ $asset->category?->name ?? 'ไม่ระบุหมวด' }} · {{ trim(($asset->brand ?? '').' '.($asset->model ?? '')) ?: 'ไม่ระบุรุ่น' }}</small>
                            @if($asset->notes)<small class="d-block muted">{{ $asset->notes }}</small>@endif
                        </td>
                        <td>{{ $asset->location?->code ?? '-' }}<small class="d-block muted">{{ $asset->location?->name ?? $asset->company }}</small></td>
                        <td>{{ $asset->owner_name ?: '-' }}<small class="d-block muted">{{ $asset->department ?: '-' }}</small></td>
                        <td>{{ number_format((float) $asset->price, 0) }}<small class="d-block muted">Book {{ number_format((float) $asset->book_value, 0) }}</small></td>
                        <td><span class="status-pill status-{{ $asset->status }}">{{ $asset->statusLabel() }}</span></td>
                        @if($canManageAssets)
                            <td>
                                <form class="asset-status-form" method="post" action="{{ route('assets.status', $asset) }}">
                                    @csrf
                                    @method('patch')
                                    <select class="form-select form-select-sm" name="status" aria-label="อัปเดตสถานะ {{ $asset->code }}">
                                        @foreach(['active' => 'ใช้งานอยู่', 'repair' => 'ส่งซ่อม', 'lost' => 'สูญหาย', 'retired' => 'จำหน่าย/เลิกใช้'] as $key => $label)
                                            <option value="{{ $key }}" @selected($asset->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input class="form-control form-control-sm" name="notes" value="{{ $asset->notes }}" placeholder="หมายเหตุ">
                                    <button class="btn btn-outline-primary btn-sm" type="submit">บันทึก</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManageAssets ? 7 : 6 }}"><div class="empty-state">ยังไม่พบทรัพย์สินตามเงื่อนไขที่ค้นหา</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $assets->links() }}
</section>

<div class="content-grid">
    <section class="panel">
        <div class="section-title">
            <h2>เอกสารตรวจนับ</h2>
            @if($canManageAssets)<span class="tag">Inspection</span>@endif
        </div>
        @if($canManageAssets)
            <form class="stack-form" method="post" action="{{ route('assets.inspections.store') }}">
                @csrf
                <label>เลขเอกสาร
                    <input class="form-control" name="code" placeholder="เว้นว่างเพื่อสร้างอัตโนมัติ">
                </label>
                <label>วันที่ตรวจนับ
                    <input class="form-control" name="inspection_date" type="date" value="{{ now()->toDateString() }}" required>
                </label>
                <label>สถานที่
                    <select class="form-select" name="asset_location_id">
                        <option value="">ไม่ระบุ</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>จำนวนรายการ
                    <input class="form-control" name="item_count" type="number" min="0" value="0">
                </label>
                <label>สถานะ
                    <select class="form-select" name="status">
                        <option value="open">เปิดเอกสาร</option>
                        <option value="closed">ปิดเอกสาร</option>
                    </select>
                </label>
                <label>หมายเหตุ
                    <textarea class="form-control" name="notes" rows="3"></textarea>
                </label>
                <button class="btn btn-primary" type="submit"><i class="bi bi-clipboard-check"></i> สร้างเอกสารตรวจนับ</button>
            </form>
        @endif
        <div class="item-list mt-3">
            @forelse($inspectionDocuments as $document)
                <article class="list-card compact">
                    <div class="meta-row">
                        <span class="status-pill status-{{ $document->status }}">{{ $document->status === 'closed' ? 'ปิดแล้ว' : 'เปิดอยู่' }}</span>
                        <span>{{ $document->inspection_date?->format('d/m/Y') }}</span>
                    </div>
                    <h3>{{ $document->code }}</h3>
                    <p>{{ $document->location?->code ?? '-' }} · {{ $document->location?->name ?? $document->company }}</p>
                    <small>{{ number_format($document->item_count) }} รายการ · {{ $document->creator?->name ?? 'System' }}</small>
                </article>
            @empty
                <div class="empty-state">ยังไม่มีเอกสารตรวจนับ</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="section-title">
            <h2>QR / Print Preview</h2>
            <button class="btn btn-outline-primary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>
        </div>
        <div class="asset-qr-grid">
            @foreach($assets->getCollection()->take(6) as $asset)
                <div class="asset-qr-card">
                    <div class="asset-qr-code">{{ $asset->code }}</div>
                    <strong>{{ $asset->code }}</strong>
                    <span>{{ $asset->name }}</span>
                </div>
            @endforeach
        </div>
    </section>
</div>

<section class="panel">
    <div class="section-title">
        <h2>ประวัติการเปลี่ยนแปลงล่าสุด</h2>
        <span class="tag">Audit</span>
    </div>
    <div class="item-list">
        @forelse($auditLogs as $log)
            <article class="list-card compact">
                <div class="meta-row">
                    <span>{{ $log->action }}</span>
                    <span>{{ $log->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <h3>{{ $log->asset?->code ?? '-' }}</h3>
                <p>{{ $log->summary }}</p>
                <small>{{ $log->user?->name ?? 'System' }}</small>
            </article>
        @empty
            <div class="empty-state">ยังไม่มีประวัติการเปลี่ยนแปลง</div>
        @endforelse
    </div>
</section>
@endsection
