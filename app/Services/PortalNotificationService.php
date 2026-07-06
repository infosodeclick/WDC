<?php

namespace App\Services;

use App\Mail\PortalNotificationMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PortalNotificationService
{
    /**
     * @param array{type:string,title:string,body:string,url:string|null} $payload
     */
    public function createForUser(User $user, array $payload): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $payload['type'],
            'title' => $payload['title'],
            'body' => $payload['body'],
            'url' => $payload['url'] ?? null,
        ]);

        $this->sendMailIfEnabled($user, $notification);

        return $notification;
    }

    /**
     * @param iterable<int, User>|Collection<int, User> $users
     * @param array{type:string,title:string,body:string,url:string|null} $payload
     */
    public function createForUsers(iterable $users, array $payload): void
    {
        foreach ($users as $user) {
            $this->createForUser($user, $payload);
        }
    }

    private function sendMailIfEnabled(User $user, Notification $notification): void
    {
        if (! config('wdc.mail_notifications_enabled')) {
            return;
        }

        if (! $user->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(new PortalNotificationMail($notification));
        } catch (Throwable $exception) {
            Log::warning('WDC notification email failed.', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
