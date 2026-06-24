@extends('layouts.app')

@section('title', 'INVENTORY | WDC Portal')

@section('content')
<div class="button-row mb-3">
    @if($canExportAssets)
        <a class="btn btn-outline-primary" href="{{ route('assets.export') }}"><i class="bi bi-filetype-csv"></i> Export CSV</a>
        <a class="btn btn-outline-primary" href="{{ route('assets.master-data') }}"><i class="bi bi-download"></i> Master Data</a>
    @endif
    @if($canManageAssets)
        <a class="btn btn-primary" href="#new-asset"><i class="bi bi-plus-circle"></i> เพิ่มทรัพย์สิน</a>
    @endif
</div>

@if($canManageAssets)
    <section class="panel" id="new-asset">
        <div class="section-title">
            <h2>เพิ่มรายการ Inventory</h2>
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
            <label>ผูกกับพนักงาน
                <select class="form-select" name="owner_id">
                    <option value="">ไม่ระบุ</option>
                    @foreach($manageableUsers as $managedUser)
                        <option value="{{ $managedUser->id }}">{{ $managedUser->employee_code }} - {{ $managedUser->name }}</option>
                    @endforeach
                </select>
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

@if($canManageAssetSettings)
    <div class="content-grid asset-admin-grid">
        <section class="panel" id="asset-categories">
            <div class="section-title">
                <h2>หมวดหมู่ทรัพย์สิน</h2>
                <span class="tag">{{ number_format($categories->count()) }} หมวด</span>
            </div>
            <form class="form-grid compact-form-grid" method="post" action="{{ route('assets.categories.store') }}">
                @csrf
                <label>Code
                    <input class="form-control" name="code" required placeholder="COM">
                </label>
                <label class="span-2">ชื่อหมวดหมู่
                    <input class="form-control" name="name" required placeholder="Computer / Notebook">
                </label>
                <label class="span-3">คำอธิบาย
                    <input class="form-control" name="description" placeholder="ใช้จัดกลุ่มอุปกรณ์และรายงาน">
                </label>
                <div class="span-3 button-row">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-layers"></i> เพิ่มหมวดหมู่</button>
                </div>
            </form>
            <div class="asset-chip-list">
                @foreach($categories as $category)
                    <span><strong>{{ $category->code }}</strong> {{ $category->name }} <small>{{ $category->assets_count }} รายการ</small></span>
                @endforeach
            </div>
        </section>

        <section class="panel" id="asset-locations">
            <div class="section-title">
                <h2>สถานที่ / GPS</h2>
                <span class="tag">{{ number_format($locations->count()) }} สถานที่</span>
            </div>
            <form class="form-grid compact-form-grid" method="post" action="{{ route('assets.locations.store') }}">
                @csrf
                <label>Code
                    <input class="form-control" name="code" required placeholder="HQ-IT">
                </label>
                <label class="span-2">ชื่อสถานที่
                    <input class="form-control" name="name" required placeholder="สำนักงานใหญ่ - ห้อง IT">
                </label>
                <label>บริษัท
                    <input class="form-control" name="company" value="WDC">
                </label>
                <label>Latitude
                    <input class="form-control" name="latitude" type="number" step="0.0000001">
                </label>
                <label>Longitude
                    <input class="form-control" name="longitude" type="number" step="0.0000001">
                </label>
                <label>Radius (m)
                    <input class="form-control" name="radius_meters" type="number" min="0" placeholder="1000">
                </label>
                <label class="small-check">
                    <input name="has_gps" type="checkbox" value="1">
                    ใช้พิกัด GPS
                </label>
                <div class="button-row">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-geo-alt"></i> เพิ่มสถานที่</button>
                </div>
            </form>
        </section>
    </div>
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
                    @if($canManageAssets || $canDeleteAssets)<th>จัดการ</th>@endif
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
                        @if($canManageAssets || $canDeleteAssets)
                            <td>
                                @if($canManageAssets)
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
                                @endif
                                @if($canDeleteAssets)
                                    <form class="asset-status-form mt-2" method="post" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('ยืนยันเก็บประวัติ/จำหน่ายทรัพย์สิน {{ $asset->code }} ? รายการจะไม่ถูกลบออกจากฐานข้อมูล')">
                                        @csrf
                                        @method('delete')
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-archive"></i> เก็บประวัติ</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ ($canManageAssets || $canDeleteAssets) ? 7 : 6 }}"><div class="empty-state">ยังไม่มีรายการทรัพย์สิน</div></td></tr>
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
        <h2>แผนที่สถานที่จัดเก็บ</h2>
        <span class="tag">Location Map</span>
    </div>
    <div class="asset-location-grid">
        @foreach($locations as $location)
            <article class="asset-location-card">
                <div>
                    <strong>{{ $location->code }}</strong>
                    <span>{{ $location->assets_count }} รายการ</span>
                </div>
                <h3>{{ $location->name }}</h3>
                <p>{{ $location->company }}</p>
                @if($location->has_gps)
                    <small><i class="bi bi-geo-alt-fill"></i> {{ $location->latitude }}, {{ $location->longitude }} · {{ $location->radius_meters ?? 0 }} m</small>
                @else
                    <small><i class="bi bi-geo-alt"></i> ยังไม่ระบุพิกัด GPS</small>
                @endif
            </article>
        @endforeach
    </div>
</section>

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
