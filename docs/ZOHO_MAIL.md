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
MAIL_USERNAME=itsupport@wdc.co.th
MAIL_PASSWORD=<zoho-app-password>
MAIL_SCHEME=smtp
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=itsupport@wdc.co.th
MAIL_FROM_NAME="WDC Portal"
WDC_MAIL_NOTIFICATIONS_ENABLED=true
```

Use a Zoho app password for `MAIL_PASSWORD`. Do not use a personal mailbox password
and do not commit real credentials to Git.

## Recommended Mailboxes

- `itsupport@wdc.co.th`: IT onboarding, IT tickets, access requests, asset alerts, and password reset links.
- `hr-notify@wdc.co.th`: HR onboarding, profile change approvals, announcements.
- `no-reply@wdc.co.th`: password reset and system messages.

Start with one mailbox if Zoho licenses are limited, then split by department later.

## Notification Events To Wire

The portal stores in-app notifications first. When `WDC_MAIL_NOTIFICATIONS_ENABLED`
is true, WDC also sends email for important events through the shared notification
mail service:

- New employee onboarding sent from HR to IT.
- IT completed onboarding and returned it to HR.
- HR cancelled onboarding.
- Employee profile phone-change request sent to HR.
- IT Helpdesk workflow request created.
- Ticket replied or status changed.
- Password reset links.

Next mail events to add after SMTP is confirmed:

- Asset warranty or license expiry alerts.
- SLA overdue alerts.

## Verification

After changing production variables:

1. Run `php artisan config:clear`.
2. Trigger a password reset or a test notification.
3. Check the sender, Thai text rendering, and spam folder.
4. Check Laravel logs if Zoho rejects the message.

Common Zoho ports:

- `587` with `MAIL_SCHEME=smtp` for normal SMTP submission.
- `465` with `MAIL_SCHEME=smtps` only if the organization requires implicit SSL.
