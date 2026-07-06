# Zoho Mail Setup

WDC uses Laravel mail configuration. Local development should keep `MAIL_MAILER=log`.
Production can use Zoho SMTP for password reset links, onboarding alerts, approval
alerts, and ticket notifications.

## Railway / Production Variables

Set these values in Railway service variables or the target host environment:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.zoho.com
MAIL_PORT=587
MAIL_USERNAME=it-notify@wdc.co.th
MAIL_PASSWORD=<zoho-app-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=it-notify@wdc.co.th
MAIL_FROM_NAME="WDC Portal"
```

Use a Zoho app password for `MAIL_PASSWORD`. Do not use a personal mailbox password
and do not commit real credentials to Git.

## Recommended Mailboxes

- `it-notify@wdc.co.th`: IT onboarding, IT tickets, access requests, asset alerts.
- `hr-notify@wdc.co.th`: HR onboarding, profile change approvals, announcements.
- `no-reply@wdc.co.th`: password reset and system messages.

Start with one mailbox if Zoho licenses are limited, then split by department later.

## Notification Events To Wire

The portal already stores in-app notifications. Email should mirror the same important
events only:

- New employee onboarding sent from HR to IT.
- IT completed onboarding and returned it to HR.
- HR cancelled onboarding.
- Profile phone-change request sent to HR.
- Password reset requested.
- Ticket assigned, replied, resolved, or overdue.
- Asset warranty or license expiry alerts.

## Verification

After changing production variables:

1. Run `php artisan config:clear`.
2. Trigger a password reset or a test notification.
3. Check the sender, Thai text rendering, and spam folder.
4. Check Laravel logs if Zoho rejects the message.

Common Zoho ports:

- `587` with `tls` for normal SMTP submission.
- `465` with `ssl` only if the organization requires implicit SSL.
