<?php

use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\ComplaintReasonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DistrictController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PushController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\RejectionReasonController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SmsGatewayController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\StatusController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\TariffController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

/*
|--------------------------------------------------------------------------
| Админка
|--------------------------------------------------------------------------
| URL-префикс — /admin (CLAUDE.md → «Структура роутов»); корень домена остаётся
| свободным под публичную часть. Имена роутов префикса не несут: во фронтенде и
| тестах всё идёт через Ziggy route('users.index'), поэтому смена URI их не задела.
| Страницы входа (routes/auth.php) намеренно живут в корне: /login, /logout.
|
| Доступ описан здесь и только здесь — контроллеры его не дублируют.
|
| admin + manager: модерация объявлений/роликов/отзывов/жалоб, чат,
|   просмотр пользователей, статистика, новости и баннеры (по флагу в настройках).
| только admin: любое удаление, управление пользователями, тарифы, категории,
|   география, push-рассылка, справочники причин и системные настройки.
| См. CLAUDE.md → «Роли».
*/
Route::prefix('admin')->middleware(['auth', 'role:admin,manager'])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Язык интерфейса текущего пользователя (переключатель в топбаре)
    Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');

    // Уведомления в топбаре (индивидуальный dismiss на пользователя)
    Route::post('notifications/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');

    // Пользователи: менеджеру — только просмотр (ТЗ: «просматривать пользователей»)
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    // Объявлен ДО users/{user}, иначе «check-phone» уйдёт в model binding
    Route::get('users/check-phone', [UserController::class, 'checkPhone'])
        ->middleware('role:admin')->name('users.check-phone');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

    // Объявления — модерация
    Route::get('listings',                     [ListingController::class, 'index'])->name('listings.index');
    Route::get('listings/{listing}',           [ListingController::class, 'show'])->name('listings.show');
    Route::put('listings/{listing}',           [ListingController::class, 'update'])->name('listings.update');
    Route::patch('listings/{listing}/approve', [ListingController::class, 'approve'])->name('listings.approve');
    Route::patch('listings/{listing}/reject',  [ListingController::class, 'reject'])->name('listings.reject');
    Route::patch('listings/{listing}/boost',   [ListingController::class, 'boost'])->name('listings.boost');

    // Ролики — модерация
    Route::get('videos',                   [VideoController::class, 'index'])->name('videos.index');
    Route::get('videos/{video}',           [VideoController::class, 'show'])->name('videos.show');
    Route::put('videos/{video}',           [VideoController::class, 'update'])->name('videos.update');
    Route::patch('videos/{video}/approve', [VideoController::class, 'approve'])->name('videos.approve');
    Route::patch('videos/{video}/reject',  [VideoController::class, 'reject'])->name('videos.reject');

    // Чат
    Route::get('chat',                [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{user}',         [ChatController::class, 'show'])->name('chat.show');
    Route::post('chat/{user}/reply',  [ChatController::class, 'reply'])->name('chat.reply');
    Route::patch('chat/{user}/read',  [ChatController::class, 'markRead'])->name('chat.read');

    // Жалобы
    Route::get('complaints',                       [ComplaintController::class, 'index'])->name('complaints.index');
    Route::patch('complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])->name('complaints.resolve');

    // Отзывы
    Route::get('reviews',                    [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject',  [ReviewController::class, 'reject'])->name('reviews.reject');

    // Статистика
    Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics.index');

    // Новости — менеджеру нужно право can_manage_news (Настройки → Роли и права)
    Route::middleware('news.permission')->group(function () {
        Route::get('news',                    [NewsController::class, 'index'])->name('news.index');
        Route::post('news',                   [NewsController::class, 'store'])->name('news.store');
        Route::put('news/{news}',             [NewsController::class, 'update'])->name('news.update');
        Route::patch('news/{news}/publish',   [NewsController::class, 'publish'])->name('news.publish');
        Route::patch('news/{news}/unpublish', [NewsController::class, 'unpublish'])->name('news.unpublish');
    });

    // Баннеры — менеджеру нужно право can_manage_banners
    Route::middleware('banner.permission')->group(function () {
        Route::get('banners',                   [BannerController::class, 'index'])->name('banners.index');
        Route::post('banners',                  [BannerController::class, 'store'])->name('banners.store');
        Route::put('banners/{banner}',          [BannerController::class, 'update'])->name('banners.update');
        Route::patch('banners/{banner}/toggle', [BannerController::class, 'toggle'])->name('banners.toggle');
        Route::patch('banners/{banner}/move',   [BannerController::class, 'move'])->name('banners.move');
    });

    // Магазины: менеджеру — только просмотр списка, управление (toggle/move/
    // удаление/медиа) — только admin (Store не входит в список сущностей,
    // модерируемых менеджером по CLAUDE.md → «Роли»)
    Route::get('stores', [StoreController::class, 'index'])->name('stores.index');

    /*
    |----------------------------------------------------------------------
    | Только admin
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {

        // Пользователи: создание, правка, блокировка, выдача тарифа
        // (users.check-phone объявлен выше — до users/{user})
        Route::post('users',                  [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}',            [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}',         [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('users/{user}/block',    [UserController::class, 'block'])->name('users.block');
        Route::patch('users/{user}/unblock',  [UserController::class, 'unblock'])->name('users.unblock');
        Route::post('users/{user}/tariff',    [UserController::class, 'assignTariff'])->name('users.tariff');

        // Удаление контента
        Route::delete('listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
        Route::delete('videos/{video}',     [VideoController::class, 'destroy'])->name('videos.destroy');
        Route::delete('news/{news}',        [NewsController::class, 'destroy'])->name('news.destroy');
        Route::delete('banners/{banner}',   [BannerController::class, 'destroy'])->name('banners.destroy');

        // Тарифы — это деньги и лимиты
        Route::resource('tariffs', TariffController::class)->except('create', 'edit', 'show');
        Route::patch('tariffs/{tariff}/toggle', [TariffController::class, 'toggle'])->name('tariffs.toggle');

        // Магазины — правка, кураторство «популярных», удаление
        Route::put('stores/{store}',    [StoreController::class, 'update'])->name('stores.update');
        Route::patch('stores/{store}/toggle', [StoreController::class, 'toggle'])->name('stores.toggle');
        Route::patch('stores/{store}/move',   [StoreController::class, 'move'])->name('stores.move');
        Route::delete('stores/{store}', [StoreController::class, 'destroy'])->name('stores.destroy');
        Route::delete('stores/{store}/photos/{photo}', [StoreController::class, 'destroyPhoto'])->name('stores.photos.destroy');

        // Категории — структура всего каталога
        Route::resource('categories', CategoryController::class)->except('create', 'edit', 'show');
        Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::patch('categories/{category}/move',   [CategoryController::class, 'move'])->name('categories.move');

        // География: Регион → Город → Район
        Route::resource('regions', RegionController::class)->only('index', 'store', 'update', 'destroy');
        Route::patch('regions/{region}/toggle', [RegionController::class, 'toggle'])->name('regions.toggle');

        Route::resource('cities', CityController::class)->only('store', 'update', 'destroy');
        Route::patch('cities/{city}/toggle', [CityController::class, 'toggle'])->name('cities.toggle');

        Route::post('cities/{city}/districts',      [DistrictController::class, 'store'])->name('districts.store');
        Route::put('districts/{district}',          [DistrictController::class, 'update'])->name('districts.update');
        Route::patch('districts/{district}/toggle', [DistrictController::class, 'toggle'])->name('districts.toggle');
        Route::delete('districts/{district}',       [DistrictController::class, 'destroy'])->name('districts.destroy');

        // Push-рассылка
        Route::get('push',       [PushController::class, 'index'])->name('push.index');
        Route::post('push/send', [PushController::class, 'send'])->name('push.send');

        // Справочники причин
        Route::resource('rejection-reasons', RejectionReasonController::class)->except('create', 'edit', 'show');
        Route::resource('complaint-reasons', ComplaintReasonController::class)->except('create', 'edit', 'show');

        // Настройки (мониторинг, права менеджера, локализация, SMS-шлюз)
        Route::get('settings',                       [SettingsController::class, 'index'])->name('settings.index');
        Route::get('settings/monitoring',             StatusController::class)->name('settings.monitoring');
        Route::patch('settings/manager-permissions',  [SettingsController::class, 'updateManagerPermissions'])->name('settings.manager-permissions');
        Route::patch('settings/localization',         [SettingsController::class, 'updateLocalization'])->name('settings.localization');
        Route::patch('settings/boost',                [SettingsController::class, 'updateBoostSettings'])->name('settings.boost');
        Route::get('settings/sms-gateway',            [SmsGatewayController::class, 'status'])->name('settings.sms-gateway');
        Route::post('settings/sms-gateway/test',      [SmsGatewayController::class, 'test'])->name('settings.sms-gateway.test');
    });
});

require __DIR__ . '/auth.php';
