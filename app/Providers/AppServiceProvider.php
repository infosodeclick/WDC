<?php

namespace App\Providers;

use App\Models\Notification;
use App\Services\ItHelpdeskWorkflow;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.app', function ($view) {
            $count = auth()->check()
                ? Notification::where('user_id', auth()->id())->whereNull('read_at')->count()
                : 0;

            $helpdesk = app(ItHelpdeskWorkflow::class);
            $helpdeskTemplate = $helpdesk->primaryTemplate();

            $view->with([
                'unreadNotificationCount' => $count,
                'itHelpdeskNavUrl' => route('workflows.index', array_filter(['template' => $helpdeskTemplate?->id])),
                'itHelpdeskTemplateId' => $helpdeskTemplate?->id,
            ]);
        });
    }
}
