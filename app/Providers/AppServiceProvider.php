<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $count = auth()->check()
                ? Notification::where('user_id', auth()->id())->whereNull('read_at')->count()
                : 0;

            $view->with('unreadNotificationCount', $count);
        });
    }
}
