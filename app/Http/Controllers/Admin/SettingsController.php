<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ToggleManagerBannerPermissionAction;
use App\Actions\ToggleManagerNewsPermissionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBoostSettingsRequest;
use App\Http\Requests\Admin\UpdateLocalizationRequest;
use App\Http\Requests\Admin\UpdateManagerPermissionsRequest;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use App\Repositories\Interfaces\SettingRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\MonitoringService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly ReasonRepositoryInterface $reasons,
    ) {}

    public function index(MonitoringService $monitoring)
    {
        return Inertia::render('Settings/Index', [
            'monitoring'         => $monitoring->getStatus(),
            'canManageNews'      => (bool) $this->settings->get('manager_can_manage_news', false),
            'canManageBanners'   => (bool) $this->settings->get('manager_can_manage_banners', false),
            // Страница управляет только причинами отклонения объявлений
            // (Settings/Index.vue создаёт их с type: 'listing').
            'rejectionReasons'   => $this->reasons->rejectionReasonsByType('listing'),
            'complaintReasons'   => $this->reasons->allComplaintReasons(),
            'ownLocale'          => Auth::user()->locale,
            'defaultAppLocale'   => $this->settings->get('default_app_locale', 'ru'),
            'boostIntervalHours' => (int) $this->settings->get('boost_interval_hours', 24),
        ]);
    }

    public function updateBoostSettings(UpdateBoostSettingsRequest $request)
    {
        $this->settings->set('boost_interval_hours', (string) $request->validated('boost_interval_hours'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.boost_settings_updated')]);
    }

    public function updateManagerPermissions(UpdateManagerPermissionsRequest $request, ToggleManagerNewsPermissionAction $newsAction, ToggleManagerBannerPermissionAction $bannerAction)
    {
        // Каждый тумблер на странице шлёт только своё поле — обновляем лишь то,
        // что реально пришло, чтобы не затирать соседний флаг значением по умолчанию.
        if ($request->has('can_manage_news')) {
            $newsAction->execute($request->boolean('can_manage_news'));
        }
        if ($request->has('can_manage_banners')) {
            $bannerAction->execute($request->boolean('can_manage_banners'));
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.manager_permission_updated')]);
    }

    public function updateLocalization(UpdateLocalizationRequest $request, UserRepositoryInterface $users)
    {
        $data = $request->validated();

        $users->updateLocale($request->user(), $data['own_locale'] ?? null);
        $this->settings->set('default_app_locale', $data['default_app_locale']);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.localization_updated')]);
    }
}
