<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>{{ $notification->title }}</title>
</head>
<body style="font-family: Arial, Tahoma, sans-serif; color: #221f20; line-height: 1.6; background: #f7f5ec; margin: 0; padding: 24px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #d8d2bf;">
        <tr>
            <td style="padding: 20px 24px; border-left: 6px solid #fbd647;">
                <div style="font-size: 12px; font-weight: 700; color: #7a6a1d; text-transform: uppercase;">WDC Portal</div>
                <h1 style="font-size: 22px; margin: 8px 0 12px;">{{ $notification->title }}</h1>
                <p style="font-size: 15px; margin: 0 0 18px;">{{ $notification->body }}</p>

                @if($notification->url)
                    <p style="margin: 0 0 18px;">
                        <a href="{{ $notification->url }}" style="display: inline-block; background: #fbd647; color: #221f20; font-weight: 700; text-decoration: none; padding: 10px 16px; border-radius: 6px;">เปิดใน WDC Portal</a>
                    </p>
                @endif

                <p style="font-size: 12px; color: #6b6658; margin: 0;">อีเมลนี้ส่งจากระบบ WDC Portal กรุณาอย่าตอบกลับอีเมลนี้โดยตรง</p>
            </td>
        </tr>
    </table>
</body>
</html>
