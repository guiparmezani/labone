<?php

namespace App\Providers;

use App\Services\AlertInbox;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            if ($user === null || ! $user->managesProjects()) {
                $view->with('alertasPendentes', 0);

                return;
            }

            $view->with('alertasPendentes', app(AlertInbox::class)->unreadCount($user));
        });
    }
}
