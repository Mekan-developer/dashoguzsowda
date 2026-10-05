<?php

use App\Http\Controllers\Api\V1\AboutController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ComplaintController;
use App\Http\Controllers\Api\V1\ComplaintReasonController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\NewsController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SearchPopularController;
use App\Http\Controllers\Api\V1\SearchRecentController;
use App\Http\Controllers\Api\V1\MyStoreController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\StoreOrderController;
use App\Http\Controllers\Api\V1\TariffController;
use App\Http\Controllers\Api\V1\VideoController;
use App\Http\Controllers\Api\V1\VideoUploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Мобильное API v1
|--------------------------------------------------------------------------
| Разложено по доменам: один домен — один непрерывный блок.
|
| Без авторизации — только «О нас» и вход по SMS: приложением пользуются
| лишь зарегистрированные, у каждого с регистрации есть тариф (бесплатный).
| Весь контент (лента, справочники, магазины, баннеры…) — под auth:sanctum.
|
| Два правила порядка, которые нельзя нарушать при правках:
|   1. статический сегмент объявляется ДО model binding — иначе «my»,
|      «popular», «upload» уедут в {listing}/{store}/{video};
|   2. заблокированному пользователю закрыто всё, что публикует контент
|      или влияет на чужие счётчики (ТЗ 13.3) — middleware not_blocked.
|
| Имена: api.v1.<домен>.<действие>. В @routes они не попадают —
| см. config/ziggy.php.
*/
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(\App\Http\Middleware\SetApiLocale::class)
    ->group(function () {

        /*
        |----------------------------------------------------------------------
        | О нас — статический текст, который админ правит в настройках
        |----------------------------------------------------------------------
        | Без авторизации: экран открывается и до входа, из онбординга.
        */
        Route::get('about', AboutController::class)->name('about');

        /*
        |----------------------------------------------------------------------
        | Аутентификация по SMS (регистрация и вход — один сценарий)
        |----------------------------------------------------------------------
        */
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/send-code', [AuthController::class, 'sendCode'])->middleware('throttle:5,1')->name('send-code');
            Route::post('/verify',    [AuthController::class, 'verify'])->middleware('throttle:10,1')->name('verify');
            Route::post('/logout',    [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
        });

        /*
        |======================================================================
        | Всё остальное — только для авторизованных
        |======================================================================
        */
        Route::middleware('auth:sanctum')->group(function () {

        /*
        |----------------------------------------------------------------------
        | Профиль, тариф и настройки текущего пользователя
        |----------------------------------------------------------------------
        */

            Route::prefix('profile')->name('profile.')->group(function () {
                Route::get('/',  [ProfileController::class, 'show'])->name('show');
                Route::put('/',  [ProfileController::class, 'update'])->name('update');

                Route::post('/avatar',   [ProfileController::class, 'updateAvatar'])->name('avatar.update');
                Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('avatar.destroy');

                Route::put('/fcm-token', [ProfileController::class, 'updateFcmToken'])->name('fcm-token');

                // Смена номера требует повторного подтверждения по SMS
                Route::post('/phone/send-code', [ProfileController::class, 'sendPhoneCode'])->middleware('throttle:5,1')->name('phone.send-code');
                Route::post('/phone/confirm',   [ProfileController::class, 'confirmPhone'])->middleware('throttle:10,1')->name('phone.confirm');

                // Тариф пользователя живёт в TariffController, но по URL — часть профиля
                Route::get('/tariff',       [TariffController::class, 'show'])->name('tariff');
                Route::put('/subscription', [TariffController::class, 'updateSubscription'])->name('subscription');
            });

            // Каталог тарифных планов для выбора в профиле
            Route::get('/tariffs', [TariffController::class, 'catalog'])->name('tariffs.catalog');

            // Свой магазин: один на пользователя, поэтому без {id}.
            // Доступен только на тарифе с can_have_store, каждая правка
            // витринных полей заново уходит на модерацию.
            Route::prefix('my/store')->name('my.store.')->group(function () {
                Route::get('/',    [MyStoreController::class, 'show'])->name('show');
                Route::post('/',   [MyStoreController::class, 'store'])
                    ->middleware(['not_blocked', 'throttle:10,1'])->name('store');
                Route::put('/',    [MyStoreController::class, 'update'])
                    ->middleware(['not_blocked', 'throttle:20,1'])->name('update');
                Route::delete('/photos/{photo}', [MyStoreController::class, 'destroyPhoto'])->name('photos.destroy');

                // Заказы, пришедшие в магазин. Заказ идёт прямо владельцу и
                // ведёт его он: принимает (или отказывается), собирает, везёт
                // сам и закрывает доставленным. Админ в решении не участвует.
                Route::prefix('orders')->name('orders.')->group(function () {
                    Route::get('/',                   [StoreOrderController::class, 'index'])->name('index');
                    Route::get('/{suborder}',         [StoreOrderController::class, 'show'])->name('show');
                    Route::post('/{suborder}/accept', [StoreOrderController::class, 'accept'])
                        ->middleware(['not_blocked', 'throttle:30,1'])->name('accept');
                    Route::post('/{suborder}/decline', [StoreOrderController::class, 'decline'])
                        ->middleware(['not_blocked', 'throttle:30,1'])->name('decline');
                    Route::post('/{suborder}/complete', [StoreOrderController::class, 'complete'])
                        ->middleware(['not_blocked', 'throttle:30,1'])->name('complete');
                });
            });

            // Настройки, синхронизируемые между устройствами (язык и тема — device-local)
            Route::prefix('preferences')->name('preferences.')->group(function () {
                Route::get('/', [PreferenceController::class, 'show'])->name('show');
                Route::put('/', [PreferenceController::class, 'update'])->name('update');
            });

        /*
        |----------------------------------------------------------------------
        | Справочники
        |----------------------------------------------------------------------
        */
        // Дерево категорий для мобильного приложения
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        // Регионы и города — для форм регистрации/профиля/объявлений
        Route::get('/regions',    [RegionController::class, 'index'])->name('regions.index');
        // Промо-карусель на главной
        Route::get('/banners',    [BannerController::class, 'index'])->name('banners.index');
        // Справочник для формы жалобы (ТЗ 8.3)
        Route::get('/complaint-reasons', [ComplaintReasonController::class, 'index'])->name('complaint-reasons.index');

        /*
        |----------------------------------------------------------------------
        | Новости
        |----------------------------------------------------------------------
        */
        Route::get('/news',        [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');

        /*
        |----------------------------------------------------------------------
        | Магазины (витрина курируется из админки, ТЗ §1)
        |----------------------------------------------------------------------
        */
        Route::prefix('stores')->name('stores.')->group(function () {
            // popular — ДО /{store}, иначе уйдёт в model binding
            Route::get('/popular',           [StoreController::class, 'popular'])->name('popular');
            Route::get('/',                  [StoreController::class, 'index'])->name('index');
            Route::get('/{store}',           [StoreController::class, 'show'])->name('show');
            Route::get('/{store}/listings',  [StoreController::class, 'listings'])->name('listings');
        });

        /*
        |----------------------------------------------------------------------
        | Поиск
        |----------------------------------------------------------------------
        */
        Route::prefix('search')->name('search.')->group(function () {
            // Популярные запросы по всему сайту
            Route::get('/popular', [SearchPopularController::class, 'index'])->name('popular');

            // История запросов пользователя
            Route::get('/recent',    [SearchRecentController::class, 'index'])->name('recent.index');
            Route::post('/recent',   [SearchRecentController::class, 'store'])->name('recent.store');
            Route::delete('/recent', [SearchRecentController::class, 'destroy'])->name('recent.destroy');
        });

        /*
        |----------------------------------------------------------------------
        | Объявления
        |----------------------------------------------------------------------
        */
        Route::prefix('listings')->name('listings.')->group(function () {
            Route::get('/', [ListingController::class, 'index'])->name('index');

            // my — ДО /{listing}, иначе уйдёт в model binding
            Route::get('/my', [ListingController::class, 'my'])->name('my');

            // Публикация и повторная публикация (update возвращает объявление
            // в pending) — заблокированному недоступны (ТЗ 13.3).
            Route::post('/', [ListingController::class, 'store'])
                ->middleware(['not_blocked', 'throttle:20,1'])->name('store');

            // Multipart-PUT PHP не парсит — обновление слать POST-ом
            Route::match(['put', 'post'], '/{listing}', [ListingController::class, 'update'])
                ->middleware(['not_blocked', 'throttle:20,1'])->can('update', 'listing')->name('update');

            Route::delete('/{listing}',      [ListingController::class, 'destroy'])->can('delete', 'listing')->name('destroy');
            Route::post('/{listing}/boost',  [ListingController::class, 'boost'])->can('boost', 'listing')->name('boost');

            Route::get('/{listing}', [ListingController::class, 'show'])->name('show');

            // Отзывы об объявлении: только approved (ТЗ 8.2)
            Route::get('/{listing}/reviews', [ReviewController::class, 'forListing'])->name('reviews');
        });

        /*
        |----------------------------------------------------------------------
        | Ролики (ТЗ §7): лента отдаёт только approved
        |----------------------------------------------------------------------
        */
        Route::prefix('videos')->name('videos.')->group(function () {
            Route::get('/', [VideoController::class, 'index'])->name('index');

                // my — ДО /{video}, иначе уйдёт в model binding
                Route::get('/my', [VideoController::class, 'my'])->name('my');

                // Chunked / streaming-загрузка (видео любого размера). Статический
                // сегмент «upload» не конфликтует с model binding /videos/{video}.
                // Частей у большого файла много — throttle здесь не вешаем.
                Route::prefix('upload')->name('upload.')->group(function () {
                    Route::post('/init',                 [VideoUploadController::class, 'init'])->middleware('not_blocked')->name('init');
                    Route::post('/{uploadId}/chunk',     [VideoUploadController::class, 'chunk'])->middleware('not_blocked')->name('chunk');
                    Route::post('/{uploadId}/complete',  [VideoUploadController::class, 'complete'])->middleware('not_blocked')->name('complete');
                    Route::delete('/{uploadId}',         [VideoUploadController::class, 'destroy'])->name('destroy');
                });

                // Загрузка ролика одним запросом (для мелких файлов)
                Route::post('/', [VideoController::class, 'store'])
                    ->middleware(['not_blocked', 'throttle:10,1'])->name('store');

                // Лайк тоже накручивает чужие счётчики — закрыт для заблокированных
                Route::post('/{video}/like', [VideoController::class, 'like'])
                    ->middleware(['not_blocked', 'throttle:60,1'])->name('like');

                Route::delete('/{video}', [VideoController::class, 'destroy'])->can('delete', 'video')->name('destroy');

            Route::get('/{video}', [VideoController::class, 'show'])->name('show');

            // Просмотр из ленты
            Route::post('/{video}/view', [VideoController::class, 'view'])->middleware('throttle:60,1')->name('view');
        });

        /*
        |----------------------------------------------------------------------
        | Заказы
        |----------------------------------------------------------------------
        | Корзина живёт на устройстве — сюда приходит уже собранный заказ, и
        | всегда на один магазин. Заказать можно только товар магазина с
        | доставкой; дальше заказ ведёт сам продавец.
        */
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');

            Route::post('/', [OrderController::class, 'store'])
                ->middleware(['not_blocked', 'throttle:20,1'])->name('store');

            Route::get('/{order}',         [OrderController::class, 'show'])->name('show');
            Route::post('/{order}/cancel', [OrderController::class, 'cancel'])
                ->middleware('throttle:30,1')->name('cancel');
        });

        /*
        |----------------------------------------------------------------------
        | Избранное (ТЗ 8.1)
        |----------------------------------------------------------------------
        */
        Route::prefix('favorites')->name('favorites.')->group(function () {
            Route::get('/', [FavoriteController::class, 'index'])->name('index');
            Route::post('/', [FavoriteController::class, 'store'])->middleware('throttle:60,1')->name('store');
            Route::delete('/{listing}', [FavoriteController::class, 'destroy'])->middleware('throttle:60,1')->name('destroy');
        });

        /*
        |----------------------------------------------------------------------
        | Отзывы и жалобы — уходят на модерацию (ТЗ 8.2/8.3)
        |----------------------------------------------------------------------
        */
        Route::middleware(['not_blocked', 'throttle:10,1'])->group(function () {
            Route::post('/reviews',    [ReviewController::class, 'store'])->name('reviews.store');
            Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');

            // Правка своего отзыва возвращает его на модерацию
            Route::match(['put', 'patch'], '/reviews/{review}', [ReviewController::class, 'update'])
                ->can('update', 'review')->name('reviews.update');
        });

        // Свои отзывы — со статусом модерации и причиной отказа
        Route::get('/reviews/my', [ReviewController::class, 'my'])->name('reviews.my');

        // Удаление своего отзыва: доступно и заблокированному — он убирает свой текст
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])
            ->can('delete', 'review')->name('reviews.destroy');

        // Отзывы о продавце — лента одобренных
        Route::get('/users/{user}/reviews', [ReviewController::class, 'forUser'])->name('users.reviews');

        /*
        |----------------------------------------------------------------------
        | Чат с поддержкой (единственный диалог пользователя с админом)
        |----------------------------------------------------------------------
        */
        Route::prefix('chat')->name('chat.')->group(function () {
            Route::get('/',       [ChatController::class, 'index'])->name('index');
            Route::post('/',      [ChatController::class, 'store'])->middleware('throttle:30,1')->name('store');
            Route::patch('/read', [ChatController::class, 'markRead'])->name('read');
        });

        }); // auth:sanctum
    });
