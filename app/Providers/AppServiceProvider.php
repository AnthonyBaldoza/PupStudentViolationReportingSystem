<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
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
        Paginator::useBootstrapFive();

        // School settings are needed by the layouts, the login/password pages (child views are
        // rendered before their layout, so they need the data too) and the standalone report views.
        View::composer(['layouts.*', 'auth.*', 'reports.print', 'reports.pdf'], function ($view) {
            $view->with('appSettings', Setting::allCached());
        });

        // Notification bell: only the signed-in layout needs it.
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();

            $view->with('unreadCount', $user ? $user->unreadNotifications()->count() : 0);
            $view->with('recentNotifications', $user ? $user->unreadNotifications()->limit(5)->get() : collect());
        });
    }
}
