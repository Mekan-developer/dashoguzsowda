<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Со стандартной заготовкой (пустой список email) дашборд Horizon
        // отдаёт 403 всем при APP_ENV=production. Доступ — по роли admin.
        //
        // Роль живёт в колонке users.role, а не в Spatie-таблицах: hasRole()
        // здесь падал с BadMethodCallException (трейта HasRoles в модели нет),
        // и /horizon в проде отдавал 500 вместо 403.
        Gate::define('viewHorizon', function ($user = null) {
            return (bool) $user?->isAdmin();
        });
    }
}
