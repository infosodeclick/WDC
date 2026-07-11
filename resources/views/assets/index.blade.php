@extends('layouts.app')

@section('title', 'INVENTORY | WDC Portal')

@section('content')
<div class="asset-page-toolbar mb-3">
    <div class="asset-page-title">
        <h1>INVENTORY</h1>
        <span>{{ number_format($assets->total()) }} รายการ · พร้อมใช้ {{ number_format($stockAssetCount) }} · จอง {{ number_format($reservedAssetCount) }}</span>
    </div>
    <div class="asset-toolbar-actions">
    @if($canManageAssets)
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#newAssetModal"><i class="bi bi-plus-circle"></i> เพิ่มทรัพย์สิน</button>
    @endif
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#softwareLicenseModal"><i class="bi bi-key"></i> License</button>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-tools"></i> เครื่องมือ</button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if($canManageAssetSettings)
                    <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#assetCategoryModal"><i class="bi bi-layers"></i> หมวดหมู่</button></li>
                    <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#assetLocationModal"><i class="bi bi-geo-alt"></i> สถานที่ / GPS</button></li>
                    <li><hr class="dropdown-divider"></li>
                @endif
                <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#assetInspectionModal"><i class="bi bi-clipboard-check"></i> เอกสารตรวจนับ</button></li>
                <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#assetQrModal"><i class="bi bi-qr-code"></i> QR / พิมพ์</button></li>
                <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#assetMapModal"><i class="bi bi-map"></i> แผนที่สถานที่</button></li>
                <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#assetAuditModal"><i class="bi bi-clock-history"></i> ประวัติล่าสุด</button></li>
            </ul>
        </div>
        @if($canExportAssets)
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download"></i> ส่งออก</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('assets.export') }}"><i class="bi bi-filetype-csv"></i> Export CSV</a></li>
                    <li><a class="dropdown-item" href="{{ route('assets.master-data') }}"><i class="bi bi-database-down"></i> Master Data</a></li>
                </ul>
            </div>
        @endif
    </div>
</div>

@if($canManageAssets)
<div class="modal fade" id="newAssetModal" tabindex="-1" aria-labelledby="newAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Asset Registry</p>
                    <h2 class="modal-title" id="newAssetModalLabel">เพิ่มทรัพย์สิน</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
        <form class="form-grid" id="newAssetForm" method="post" action="{{ route('assets.store') }}">
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
                    <option value="stock">พร้อมใช้งาน</option>
                    <option value="reserved">จองให้พนักงานใหม่</option>
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
        </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary" type="submit" form="newAssetForm"><i class="bi bi-save"></i> บันทึกทรัพย์สิน</button>
            </div>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="softwareLicenseModal" tabindex="-1" aria-labelledby="softwareLicenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">{{ number_format($softwareLicenses->count()) }} รายการ</p>
                    <h2 class="modal-title" id="softwareLicenseModalLabel">Software License</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
    @if($canManageAssets)
        <form class="form-grid compact-form-grid mb-4" method="post" action="{{ route('assets.licenses.store') }}">
            @csrf
            <label>รหัส License
                <input class="form-control" name="code" required placeholder="LIC-M365-001">
            </label>
            <label class="span-2">ชื่อ Software
                <input class="form-control" name="name" required placeholder="Microsoft 365 Business Standard">
            </label>
            <label>Vendor
                <input class="form-control" name="vendor" placeholder="Microsoft, Adobe, AutoDesk">
            </label>
            <label>ประเภท
                <select class="form-select" name="license_type" required>
                    <option value="subscription">Subscription</option>
                    <option value="perpetual">Perpetual</option>
                    <option value="trial">Trial</option>
                    <option value="other">Other</option>
                </select>
            </label>
            <label>จำนวนสิทธิ์
                <input class="form-control" name="seat_count" type="number" min="1" value="1" required>
            </label>
            <label>ใช้งานแล้ว
                <input class="form-control" name="assigned_seats" type="number" min="0" value="0">
            </label>
            <label>ค่าใช้จ่าย
                <input class="form-control" name="cost" type="number" min="0" step="0.01">
            </label>
            <label>แผนก/เจ้าของระบบ
                <input class="form-control" name="department" placeholder="IT, Marketing, Design">
            </label>
            <label>วันเริ่ม
                <input class="form-control" name="starts_at" type="date">
            </label>
            <label>วันหมดอายุ
                <input class="form-control" name="expires_at" type="date">
            </label>
            <label>สถานะ
                <select class="form-select" name="status" required>
                    <option value="active">ใช้งานอยู่</option>
                    <option value="expiring">ใกล้หมดอายุ</option>
                    <option value="expired">หมดอายุ</option>
                    <option value="cancelled">ยกเลิก</option>
                </select>
            </label>
            <label class="span-3">หมายเหตุ
                <textarea class="form-control" name="notes" rows="2" placeholder="เลขสัญญา ผู้ดูแล หรือเงื่อนไขการต่ออายุ"></textarea>
            </label>
            <div class="span-3 modal-inline-actions">
                <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> เพิ่ม License</button>
            </div>
        </form>
    @endif

    <div class="table-responsive">
        <table class="table align-middle asset-table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>Software</th>
                    <th>สิทธิ์</th>
                    <th>ค่าใช้จ่าย</th>
                    <th>หมดอายุ</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($softwareLicenses as $license)
                    <tr>
                        <td><strong>{{ $license->code }}</strong></td>
                        <td>
                            <strong>{{ $license->name }}</strong>
                            <small class="d-block muted">{{ $license->vendor ?: '-' }} · {{ $license->department ?: 'ไม่ระบุเจ้าของระบบ' }}</small>
                        </td>
                        <td>{{ number_format($license->assigned_seats) }} / {{ number_format($license->seat_count) }}<small class="d-block muted">คงเหลือ {{ number_format($license->availableSeats()) }}</small></td>
                        <td>{{ number_format((float) $license->cost, 0) }}</td>
                        <td>{{ $license->expires_at?->format('d/m/Y') ?? '-' }}</td>
                        <td><span class="status-pill status-{{ $license->status }}">{{ $license->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state">ยังไม่มีทะเบียน Software License</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
            </div>
        </div>
    </div>
</div>

@if($canManageAssetSettings)
<div class="modal fade" id="assetCategoryModal" tabindex="-1" aria-labelledby="assetCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">{{ number_format($categories->count()) }} หมวด</p>
                    <h2 class="modal-title" id="assetCategoryModalLabel">หมวดหมู่ทรัพย์สิน</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
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
                <div class="span-3 modal-inline-actions">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-layers"></i> เพิ่มหมวดหมู่</button>
                </div>
            </form>
            <div class="asset-chip-list">
                @foreach($categories as $category)
                    <span><strong>{{ $category->code }}</strong> {{ $category->name }} <small>{{ $category->assets_count }} รายการ</small></span>
                @endforeach
            </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assetLocationModal" tabindex="-1" aria-labelledby="assetLocationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">{{ number_format($locations->count()) }} สถานที่</p>
                    <h2 class="modal-title" id="assetLocationModalLabel">สถานที่ / GPS</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
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
                <div class="modal-inline-actions">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-geo-alt"></i> เพิ่มสถานที่</button>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>
@endif

<section class="panel" id="asset-registry">
    <div class="section-title">
        <h2>ทะเบียนทรัพย์สิน</h2>
        <span class="tag">{{ number_format($assets->total()) }} รายการ</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle asset-table asset-registry-table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>รายการ</th>
                    <th>สถานที่</th>
                    <th>ผู้ถือครอง</th>
                    <th>มูลค่า</th>
                    <th>สถานะ</th>
                    @if($canManageAssets || $canDeleteAssets)<th><span class="visually-hidden">จัดการ</span></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($assets as $asset)
                    <tr>
                        <td><strong>{{ $asset->code }}</strong><small class="d-block muted">{{ $asset->serial_number ?: 'No serial' }}</small></td>
                        <td>
                            <strong>{{ $asset->name }}</strong>
                            <small class="d-block muted">{{ $asset->category?->name ?? 'ไม่ระบุหมวด' }} · {{ trim(($asset->brand ?? '').' '.($asset->model ?? '')) ?: 'ไม่ระบุรุ่น' }}</small>
                            <div class="asset-mobile-facts" aria-label="ข้อมูลทรัพย์สินเพิ่มเติม">
                                <span><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $asset->location?->code ?? $asset->location?->name ?? '-' }}</span>
                                <span><i class="bi bi-person" aria-hidden="true"></i>{{ $asset->owner_name ?: 'ยังไม่มีผู้ถือครอง' }}</span>
                                <span><i class="bi bi-cash" aria-hidden="true"></i>{{ number_format((float) $asset->price, 0) }} บาท</span>
                            </div>
                        </td>
                        <td>{{ $asset->location?->code ?? '-' }}<small class="d-block muted">{{ $asset->location?->name ?? $asset->company }}</small></td>
                        <td>{{ $asset->owner_name ?: '-' }}<small class="d-block muted">{{ $asset->department ?: '-' }}</small></td>
                        <td>{{ number_format((float) $asset->price, 0) }}@if((float) $asset->book_value !== (float) $asset->price)<small class="d-block muted">Book {{ number_format((float) $asset->book_value, 0) }}</small>@endif</td>
                        <td><span class="status-pill status-{{ $asset->status }}">{{ $asset->statusLabel() }}</span></td>
                        @if($canManageAssets || $canDeleteAssets)
                            <td class="asset-row-action-cell">
                                <div class="dropdown">
                                    <button class="btn btn-outline-primary btn-sm asset-row-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="จัดการ {{ $asset->code }}" aria-label="จัดการ {{ $asset->code }}">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end asset-row-menu">
                                        @if($canManageAssets)
                                            <li>
                                                <button
                                                    class="dropdown-item"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#assetStatusModal"
                                                    data-asset-action="{{ route('assets.status', $asset) }}"
                                                    data-asset-code="{{ $asset->code }}"
                                                    data-asset-status="{{ $asset->status }}"
                                                    data-asset-notes="{{ $asset->notes }}"
                                                ><i class="bi bi-pencil-square"></i> แก้ไขสถานะ</button>
                                            </li>
                                        @endif
                                        @if($canDeleteAssets)
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('ยืนยันเก็บประวัติ/จำหน่ายทรัพย์สิน {{ $asset->code }} ? รายการจะไม่ถูกลบออกจากฐานข้อมูล')">
                                                    @csrf
                                                    @method('delete')
                                                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-archive"></i> เก็บประวัติ</button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
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

@if($canManageAssets)
<div class="modal fade" id="assetStatusModal" tabindex="-1" aria-labelledby="assetStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">แก้ไขทรัพย์สิน</p>
                    <h2 class="modal-title" id="assetStatusModalLabel" data-asset-status-title>สถานะทรัพย์สิน</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <form method="post" data-asset-status-form>
                @csrf
                @method('patch')
                <div class="modal-body stack-form">
                    <label>สถานะ
                        <select class="form-select" name="status" data-asset-status-select>
                            @foreach(['stock' => 'พร้อมใช้งาน', 'reserved' => 'จองให้พนักงานใหม่', 'active' => 'ใช้งานอยู่', 'repair' => 'ส่งซ่อม', 'lost' => 'สูญหาย', 'retired' => 'จำหน่าย/เลิกใช้'] as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>หมายเหตุ
                        <textarea class="form-control" name="notes" rows="3" data-asset-status-notes></textarea>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="assetInspectionModal" tabindex="-1" aria-labelledby="assetInspectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="assetInspectionModalLabel">เอกสารตรวจนับ</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
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
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assetQrModal" tabindex="-1" aria-labelledby="assetQrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="assetQrModalLabel">QR / Print Preview</h2>
                <div class="modal-header-actions-inline">
            <button class="btn btn-outline-primary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
            </div>
            <div class="modal-body">
        <div class="asset-qr-grid">
            @foreach($assets->getCollection()->take(6) as $asset)
                <div class="asset-qr-card">
                    <div class="asset-qr-code">{{ $asset->code }}</div>
                    <strong>{{ $asset->code }}</strong>
                    <span>{{ $asset->name }}</span>
                </div>
            @endforeach
        </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assetMapModal" tabindex="-1" aria-labelledby="assetMapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="assetMapModalLabel">แผนที่สถานที่จัดเก็บ</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
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
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assetAuditModal" tabindex="-1" aria-labelledby="assetAuditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content inventory-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="assetAuditModalLabel">ประวัติการเปลี่ยนแปลงล่าสุด</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
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
            </div>
        </div>
    </div>
</div>
@endsection
