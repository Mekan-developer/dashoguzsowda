<?php

namespace App\Http\Middleware;

use App\Http\Resources\Admin\AuthUserResource;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly NotificationService $notificationService,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            // Ресурс, а не сырая модель: в props уходят только те поля,
            // которые реально читает фронт.
            // resolve(), а не сам ресурс: JsonResource сериализуется в Inertia
            // с обёрткой `data`, и фронт ждал бы auth.user.data.role.
            'auth'  => ['user' => $user ? (new AuthUserResource($user))->resolve($request) : null],
            'flash' => fn () => ['toast' => $request->session()->get('toast')],
            // Шесть COUNT-ов заменены одним запросом в репозитории.
            // navCounts, а не counts: страницы отдают свой `counts` для вкладок
            // фильтра, и он перекрывал бы этот — бейджи меню пропадали.
            'navCounts' => fn () => $user ? $this->notifications->counters($user->id) : [],
            'notifications' => fn () => $user
                ? $this->notificationService->forUser($user)
                : [],
        ];
    }
}
