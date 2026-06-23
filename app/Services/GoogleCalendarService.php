<?php

namespace App\Services;

use App\Models\MeetingRoomBooking;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCalendarService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_EVENTS_SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    public function createEvent(MeetingRoomBooking $booking): string
    {
        $calendarId = (string) config('services.meeting_rooms.calendar_id');

        if ($calendarId === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า MEETING_ROOM_GOOGLE_CALENDAR_ID');
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->eventsUrl($calendarId), [
                'summary' => "{$booking->room_name}: {$booking->title}",
                'location' => $booking->room_name,
                'description' => $this->descriptionFor($booking),
                'start' => [
                    'dateTime' => $booking->start_at->toIso8601String(),
                    'timeZone' => (string) config('services.meeting_rooms.timezone', 'Asia/Bangkok'),
                ],
                'end' => [
                    'dateTime' => $booking->end_at->toIso8601String(),
                    'timeZone' => (string) config('services.meeting_rooms.timezone', 'Asia/Bangkok'),
                ],
                'extendedProperties' => [
                    'private' => [
                        'wdc_booking_id' => (string) $booking->id,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->responseMessage($response, 'สร้าง Google Calendar event ไม่สำเร็จ'));
        }

        $eventId = $response->json('id');

        if (! is_string($eventId) || $eventId === '') {
            throw new RuntimeException('Google Calendar ไม่ส่ง event id กลับมา');
        }

        return $eventId;
    }

    public function deleteEvent(string $eventId): void
    {
        if ($eventId === '') {
            return;
        }

        $calendarId = (string) config('services.meeting_rooms.calendar_id');

        if ($calendarId === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า MEETING_ROOM_GOOGLE_CALENDAR_ID');
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->delete($this->eventsUrl($calendarId).'/'.rawurlencode($eventId));

        if (in_array($response->status(), [404, 410], true)) {
            return;
        }

        if ($response->failed()) {
            throw new RuntimeException($this->responseMessage($response, 'ลบ Google Calendar event ไม่สำเร็จ'));
        }
    }

    private function accessToken(): string
    {
        $credentials = $this->serviceAccountCredentials();
        $now = time();
        $assertion = $this->jwt([
            'iss' => $credentials['client_email'],
            'scope' => self::CALENDAR_EVENTS_SCOPE,
            'aud' => $credentials['token_uri'] ?? self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $credentials['private_key']);

        $response = Http::asForm()->post($credentials['token_uri'] ?? self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->responseMessage($response, 'ขอ Google access token ไม่สำเร็จ'));
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google ไม่ส่ง access token กลับมา');
        }

        return $token;
    }

    private function serviceAccountCredentials(): array
    {
        $raw = config('services.meeting_rooms.service_account_json');

        if (! is_string($raw) || trim($raw) === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า MEETING_ROOM_GOOGLE_SERVICE_ACCOUNT_JSON');
        }

        $credentials = json_decode($raw, true);

        if (! is_array($credentials)) {
            $decoded = base64_decode($raw, true);
            $credentials = is_string($decoded) ? json_decode($decoded, true) : null;
        }

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('ค่า MEETING_ROOM_GOOGLE_SERVICE_ACCOUNT_JSON ไม่ถูกต้อง');
        }

        return $credentials;
    }

    private function jwt(array $payload, string $privateKey): string
    {
        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signed = openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if ($signed !== true) {
            throw new RuntimeException('ลงนาม Google service account JWT ไม่สำเร็จ');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function eventsUrl(string $calendarId): string
    {
        return 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events';
    }

    private function descriptionFor(MeetingRoomBooking $booking): string
    {
        return collect([
            'จองผ่าน WDC Portal',
            'ผู้จอง: '.($booking->user?->name ?? '-'),
            'จำนวนผู้เข้าร่วม: '.($booking->attendees ?: '-'),
            $booking->notes ? 'รายละเอียด: '.$booking->notes : null,
        ])->filter()->implode("\n");
    }

    private function responseMessage(Response $response, string $fallback): string
    {
        $message = $response->json('error.message')
            ?? $response->json('error_description')
            ?? $response->body()
            ?: $fallback;

        return mb_strimwidth($fallback.': '.$message, 0, 1000);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
