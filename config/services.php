<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payroll' => [
        'url' => env('PAYROLL_URL', 'https://example.com/payroll'),
    ],

    'time_attendance' => [
        'url' => env('TIME_ATTENDANCE_URL'),
    ],

    'meeting_rooms' => [
        'sheet_url' => env('MEETING_ROOM_GOOGLE_SHEET_URL', 'https://calendar.google.com/calendar/u/0/embed?src=641a219d5e8a0c60b9107fff5f155eba12e1d82d03809d7df47bc8aa656ea1e6@group.calendar.google.com&ctz=Asia/Bangkok'),
        'sheet_embed_url' => env('MEETING_ROOM_GOOGLE_SHEET_EMBED_URL', 'https://calendar.google.com/calendar/u/0/embed?src=641a219d5e8a0c60b9107fff5f155eba12e1d82d03809d7df47bc8aa656ea1e6@group.calendar.google.com&ctz=Asia/Bangkok'),
        'booking_url' => env('MEETING_ROOM_BOOKING_URL', 'https://calendar.google.com/calendar/u/0/r/eventedit?text=%E0%B8%88%E0%B8%AD%E0%B8%87%E0%B8%AB%E0%B9%89%E0%B8%AD%E0%B8%87%E0%B8%9B%E0%B8%A3%E0%B8%B0%E0%B8%8A%E0%B8%B8%E0%B8%A1&ctz=Asia/Bangkok'),
        'calendar_id' => env('MEETING_ROOM_GOOGLE_CALENDAR_ID', '641a219d5e8a0c60b9107fff5f155eba12e1d82d03809d7df47bc8aa656ea1e6@group.calendar.google.com'),
        'timezone' => env('MEETING_ROOM_TIMEZONE', 'Asia/Bangkok'),
        'service_account_json' => env('MEETING_ROOM_GOOGLE_SERVICE_ACCOUNT_JSON'),
    ],

];
