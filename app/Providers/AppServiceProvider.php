<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Observers\AnnouncementObserver;
use App\View\Composers\NotificationComposer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.simalas');
        Paginator::defaultSimpleView('vendor.pagination.simalas');

        View::composer('layouts.dashboard', NotificationComposer::class);

        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Announcement::observe(AnnouncementObserver::class);
    }
}
