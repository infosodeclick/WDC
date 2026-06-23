@extends('layouts.app')

@section('title', 'ห้องประชุม | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Google Calendar</p>
        <h1>ห้องประชุม</h1>
        <p>ตารางจองห้องประชุมจาก Google Calendar และปุ่มจองใน WDC โดยไม่ต้องเปิดแท็บใหม่</p>
    </div>
    <div class="page-actions">
        @if($sheetUrl)
            <a class="btn btn-outline-primary" href="{{ $sheetUrl }}" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right"></i> เปิดปฏิทิน
            </a>
        @endif
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#meetingRoomBookingModal">
            <i class="bi bi-calendar-plus"></i> จองห้องประชุม
        </button>
    </div>
</div>

<section class="panel meeting-room-panel">
    <div class="section-title">
        <div>
            <h2>ตารางจองห้องประชุม</h2>
            <p class="section-subtitle">แสดงรายการใช้งานห้องประชุมจาก Google Calendar</p>
        </div>
        <span class="tag">Google Calendar</span>
    </div>

    @if($sheetEmbedUrl)
        <div class="meeting-sheet-frame">
            <iframe src="{{ $sheetEmbedUrl }}" title="ตารางจองห้องประชุมจาก Google Calendar" loading="lazy"></iframe>
        </div>
    @else
        <div class="meeting-sheet-placeholder">
            <div>
                <i class="bi bi-calendar2-week"></i>
                <strong>ยังไม่ได้เชื่อม Google Calendar</strong>
                <p>เมื่อตั้งค่า <code>MEETING_ROOM_GOOGLE_SHEET_EMBED_URL</code> ระบบจะแสดงตารางจองห้องประชุมจาก Google Calendar ในหน้านี้ทันที</p>
            </div>
        </div>
    @endif
</section>

<section class="panel">
    <div class="section-title">
        <h2>คำขอจองล่าสุด</h2>
        <span class="tag">WDC Booking</span>
    </div>

    <div class="table-responsive meeting-table-wrap">
        <table class="table align-middle meeting-table">
            <thead>
                <tr>
                    <th>วันเวลา</th>
                    <th>ห้องประชุม</th>
                    <th>หัวข้อ</th>
                    <th>ผู้จอง</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td>
                            <strong>{{ $booking->start_at->format('d/m/Y H:i') }}</strong>
                            <small>{{ $booking->end_at->format('H:i') }}</small>
                        </td>
                        <td>{{ $booking->room_name }}</td>
                        <td>
                            <strong>{{ $booking->title }}</strong>
                            @if($booking->attendees)
                                <small>{{ $booking->attendees }} คน</small>
                            @endif
                        </td>
                        <td>{{ $booking->user?->name ?? '-' }}</td>
                        <td><span class="status-pill status-submitted">รอซิงค์</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><div class="empty-state">ยังไม่มีคำขอจองใน WDC</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel meeting-sync-panel">
    <div class="section-title">
        <h2>สถานะการเชื่อมต่อ</h2>
        @if($sheetUrl)
            <a href="{{ $sheetUrl }}" target="_blank" rel="noopener">เปิด Google Calendar</a>
        @endif
    </div>
    <div class="meeting-room-grid">
        <div>
            <strong>Google Calendar</strong>
            <p>ระบบหน้านี้แสดงปฏิทินห้องประชุมที่มีผู้ใช้งานแล้ว ส่วนคำขอที่จองใน WDC จะพร้อมนำไปซิงค์กับ Google Calendar ในขั้นถัดไป</p>
        </div>
        <span class="status-pill status-in_progress">เชื่อมต่อแล้ว</span>
    </div>
</section>

<div class="modal fade" id="meetingRoomBookingModal" tabindex="-1" aria-labelledby="meetingRoomBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" method="post" action="{{ route('meeting-rooms.store') }}">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="meetingRoomBookingModalLabel">จองห้องประชุม</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <label>
                        <span>ห้องประชุม</span>
                        <select class="form-select" name="room_name" required>
                            <option value="">เลือกห้องประชุม</option>
                            <option value="ห้องประชุมใหญ่">ห้องประชุมใหญ่</option>
                            <option value="ห้องประชุมเล็ก">ห้องประชุมเล็ก</option>
                            <option value="ห้องประชุมผู้บริหาร">ห้องประชุมผู้บริหาร</option>
                            <option value="Training Room">Training Room</option>
                        </select>
                    </label>
                    <label>
                        <span>หัวข้อการประชุม</span>
                        <input class="form-control" name="title" required maxlength="160" placeholder="เช่น ประชุมฝ่ายขายประจำสัปดาห์">
                    </label>
                    <label>
                        <span>เริ่ม</span>
                        <input class="form-control" type="datetime-local" name="start_at" required>
                    </label>
                    <label>
                        <span>สิ้นสุด</span>
                        <input class="form-control" type="datetime-local" name="end_at" required>
                    </label>
                    <label>
                        <span>จำนวนผู้เข้าร่วม</span>
                        <input class="form-control" type="number" name="attendees" min="1" max="200" placeholder="เช่น 8">
                    </label>
                    <label class="span-2">
                        <span>รายละเอียดเพิ่มเติม</span>
                        <textarea class="form-control" name="notes" rows="4" maxlength="1000" placeholder="อุปกรณ์ที่ต้องใช้ หรือหมายเหตุอื่น ๆ"></textarea>
                    </label>
                </div>
                <p class="form-help mt-3">คำขอนี้จะบันทึกใน WDC ก่อน และเตรียมซิงค์ไป Google Calendar ในขั้นถัดไป</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> ส่งคำขอจอง</button>
            </div>
        </form>
    </div>
</div>
@endsection
