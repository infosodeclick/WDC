@extends('layouts.app')

@section('title', 'ห้องประชุม | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Google Sheet</p>
        <h1>ห้องประชุม</h1>
        <p>ตารางจองห้องประชุมจาก Google Sheet และปุ่มจองสำหรับเตรียมซิงค์กับ Google ในอนาคต</p>
    </div>
    @if($bookingUrl)
        <a class="btn btn-primary" href="{{ $bookingUrl }}" target="_blank" rel="noopener">
            <i class="bi bi-calendar-plus"></i> จองห้องประชุม
        </a>
    @else
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#meetingRoomBookingModal">
            <i class="bi bi-calendar-plus"></i> จองห้องประชุม
        </button>
    @endif
</div>

<section class="panel meeting-room-panel">
    <div class="section-title">
        <div>
            <h2>ตารางจองห้องประชุม</h2>
            <p class="section-subtitle">เตรียมเชื่อมข้อมูลจาก Google Sheet</p>
        </div>
        <span class="tag">Google Sheet</span>
    </div>

    @if($sheetEmbedUrl)
        <div class="meeting-sheet-frame">
            <iframe src="{{ $sheetEmbedUrl }}" title="ตารางจองห้องประชุมจาก Google Sheet" loading="lazy"></iframe>
        </div>
    @else
        <div class="meeting-sheet-placeholder">
            <div>
                <i class="bi bi-table"></i>
                <strong>ยังไม่ได้เชื่อม Google Sheet</strong>
                <p>เมื่อตั้งค่า <code>MEETING_ROOM_GOOGLE_SHEET_EMBED_URL</code> ระบบจะแสดงตารางจองห้องประชุมจาก Google Sheet ในหน้านี้ทันที</p>
            </div>
        </div>

        <div class="table-responsive meeting-table-wrap">
            <table class="table align-middle meeting-table">
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>เวลา</th>
                        <th>ห้องประชุม</th>
                        <th>ผู้จอง</th>
                        <th>หัวข้อ</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">รอข้อมูลจาก Google Sheet</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="panel meeting-sync-panel">
    <div class="section-title">
        <h2>สถานะการเชื่อมต่อ</h2>
        @if($sheetUrl)
            <a href="{{ $sheetUrl }}" target="_blank" rel="noopener">เปิด Google Sheet</a>
        @endif
    </div>
    <div class="meeting-room-grid">
        <div>
            <strong>Google Sheet</strong>
            <p>ระบบหน้านี้เตรียมไว้สำหรับอ่านตารางจองที่มีผู้ใช้งานแล้วจาก Google Sheet และรองรับปุ่มจองเพื่อซิงค์กลับในอนาคต</p>
        </div>
        <span class="status-pill status-submitted">พร้อมตั้งค่า</span>
    </div>
</section>

<div class="modal fade" id="meetingRoomBookingModal" tabindex="-1" aria-labelledby="meetingRoomBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="meetingRoomBookingModalLabel">จองห้องประชุม</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <p>ระบบจองห้องประชุมพร้อมเชื่อมกับ Google ในขั้นถัดไป ให้ตั้งค่า <code>MEETING_ROOM_BOOKING_URL</code> เป็นลิงก์ Google Form หรือระบบจองที่ต้องการ</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>
@endsection
