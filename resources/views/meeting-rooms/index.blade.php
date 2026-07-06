@extends('layouts.app')

@section('title', 'ห้องประชุม | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <h1>ห้องประชุม</h1>
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

<section class="panel" id="wdc-bookings">
    <div class="section-title">
        <div>
            <h2>การจองใน WDC</h2>
            <p class="section-subtitle">แสดงเฉพาะรายการที่คุณเป็นผู้จอง เพื่อให้ตรวจสถานะและยกเลิกได้ง่าย</p>
        </div>
        <span class="tag">การจองของฉัน</span>
    </div>

    <div class="table-responsive meeting-table-wrap">
        <table class="table align-middle meeting-table">
            <thead>
                <tr>
                    <th>วันเวลา</th>
                    <th>ห้องประชุม</th>
                    <th>หัวข้อ</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
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
                        <td>
                            <span class="status-pill {{ $booking->statusClass() }}">{{ $booking->statusLabel() }}</span>
                            @if($booking->sync_error && $booking->status !== 'cancelled')
                                <small class="sync-error">{{ $booking->sync_error }}</small>
                            @endif
                        </td>
                        <td>
                            @if($booking->status !== 'cancelled')
                                <form method="post" action="{{ route('meeting-rooms.cancel', $booking) }}" onsubmit="return confirm('ยืนยันยกเลิกการจองนี้?')">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">
                                        <i class="bi bi-x-circle"></i> ยกเลิก
                                    </button>
                                </form>
                            @else
                                <small>{{ $booking->cancelled_at?->format('d/m/Y H:i') }}</small>
                            @endif
                        </td>
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
                <strong>ยังไม่ได้ตั้งค่าหน้าแสดงปฏิทิน</strong>
                <p>เมื่อตั้งค่า <code>MEETING_ROOM_GOOGLE_SHEET_EMBED_URL</code> ระบบจะแสดงตารางจองห้องประชุมจาก Google Calendar ในหน้านี้ทันที</p>
            </div>
        </div>
    @endif
</section>

<section class="panel meeting-sync-panel">
    <div class="section-title">
        <h2>สถานะการเชื่อมต่อ</h2>
        @if($sheetUrl)
            <a href="{{ $sheetUrl }}" target="_blank" rel="noopener">เปิด Google Calendar</a>
        @endif
    </div>
    <div class="meeting-sync-grid">
        <div class="meeting-sync-card {{ $calendarStatus['can_display_calendar'] ? 'ready' : 'warning' }}">
            <i class="bi bi-calendar2-week"></i>
            <div>
                <span>แสดงปฏิทิน</span>
                <strong>{{ $calendarStatus['can_display_calendar'] ? 'พร้อมแสดงผล' : 'รอตั้งค่า Embed URL' }}</strong>
            </div>
        </div>
        <div class="meeting-sync-card {{ $calendarStatus['can_sync_events'] ? 'ready' : 'warning' }}">
            <i class="bi bi-arrow-repeat"></i>
            <div>
                <span>ซิงค์การจอง</span>
                <strong>{{ $calendarStatus['can_sync_events'] ? 'พร้อมสร้าง event' : 'รอตั้งค่า Service Account' }}</strong>
            </div>
        </div>
        <div class="meeting-sync-card {{ $calendarStatus['is_ready'] ? 'ready' : 'warning' }}">
            <i class="bi bi-check2-circle"></i>
            <div>
                <span>สถานะรวม</span>
                <strong>{{ $calendarStatus['is_ready'] ? 'พร้อมใช้งานครบ' : 'ใช้งาน WDC ได้ แต่ Google sync ยังไม่ครบ' }}</strong>
            </div>
        </div>
    </div>
    <div class="meeting-sync-note">
        <p>การจองจะบันทึกใน WDC เสมอ หากตั้งค่า <code>MEETING_ROOM_GOOGLE_CALENDAR_ID</code> และ <code>MEETING_ROOM_GOOGLE_SERVICE_ACCOUNT_JSON</code> ครบ ระบบจะสร้างหรือลบ event ใน Google Calendar ให้ทันที</p>
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
                <p class="form-help mt-3">ระบบจะบันทึกใน WDC และพยายามซิงค์เข้า Google Calendar ทันที หากยังไม่ได้ตั้งค่า Google จะแสดงสถานะซิงค์ไม่สำเร็จให้ตรวจสอบ</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> ส่งคำขอจอง</button>
            </div>
        </form>
    </div>
</div>
@endsection
