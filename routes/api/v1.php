<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ComplaintController;
use App\Http\Controllers\Api\V1\ComplaintReasonController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\NewsController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\TariffController;
use App\Http\Controllers\Api\V1\VideoController;
use App\Http\Controllers\Api\V1\VideoUploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(\App\Http\Middleware\SetApiLocale::class)->group(function () {
    // Аутентификация по SMS (регистрация и вход — один сценарий)
    Route::prefix('auth')->group(function () {
        Route::post('/send-code', [AuthController::class, 'sendCode'])->middleware('throttle:5,1');
        Route::post('/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    // Профиль текущего пользователя
    Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::get('/tariff', [TariffController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/fcm-token', [ProfileController::class, 'updateFcmToken']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
        Route::post('/phone/send-code', [ProfileController::class, 'sendPhoneCode'])->middleware('throttle:5,1');
        Route::post('/phone/confirm', [ProfileController::class, 'confirmPhone'])->middleware('throttle:10,1');
    });

    // Новости (публичные)
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/{news}', [NewsController::class, 'show']);

    // Категории (публичные, дерево для мобильного приложения)
    Route::get('/categories', [CategoryController::class, 'index']);

    // Регионы и города (публичные, для форм регистрации/профиля/объявлений)
    Route::get('/regions', [RegionController::class, 'index']);

    // Баннеры (публичные, промо-карусель для мобильного приложения)
    Route::get('/banners', [BannerController::class, 'index']);

    // Объявления
    Route::get('/listings', [ListingController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        // /listings/my объявлен ДО /listings/{listing}, иначе «my» уйдёт в model binding
        Route::get('/listings/my', [ListingController::class, 'my']);
        // Публикация и повторная публикация (update возвращает объявление в pending) —
        // заблокированному пользователю недоступны (ТЗ 13.3)
        Route::post('/listings', [ListingController::class, 'store'])->middleware('not_blocked');
        // Multipart-PUT PHP не парсит — обновление слать POST-ом
        Route::match(['put', 'post'], '/listings/{listing}', [ListingController::class, 'update'])->middleware('not_blocked')->can('update', 'listing');
        Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->can('delete', 'listing');
        Route::post('/listings/{listing}/boost', [ListingController::class, 'boost'])->can('boost', 'listing');
    });

    Route::get('/listings/{listing}', [ListingController::class, 'show']);

    // Чат с поддержкой (единственный диалог пользователя с админом)
    Route::middleware('auth:sanctum')->prefix('chat')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/', [ChatController::class, 'store']);
        Route::patch('/read', [ChatController::class, 'markRead']);
    });

    // Причины жалоб (публичный справочник — для формы жалобы, ТЗ 8.3)
    Route::get('/complaint-reasons', [ComplaintReasonController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        // Отзывы и жалобы уходят на модерацию (ТЗ 8.2/8.3);
        // заблокированный пользователь публиковать контент не может (ТЗ 13.3)
        Route::post('/reviews', [ReviewController::class, 'store'])->middleware(['not_blocked', 'throttle:10,1']);
        Route::post('/complaints', [ComplaintController::class, 'store'])->middleware(['not_blocked', 'throttle:10,1']);

        // Избранное (ТЗ 8.1)
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites', [FavoriteController::class, 'store']);
        Route::delete('/favorites/{listing}', [FavoriteController::class, 'destroy']);
    });

    // Ролики (ТЗ §7): публичная лента отдаёт только approved
    Route::get('/videos', [VideoController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        // /videos/my объявлен ДО /videos/{video}, иначе «my» уйдёт в model binding
        Route::get('/videos/my', [VideoController::class, 'my']);

        // Chunked / streaming-загрузка (видео любого размера) — заблокированному недоступна (ТЗ 13.3).
        // Статические сегменты «upload/...» не конфликтуют с model binding /videos/{video}.
        Route::post('/videos/upload/init', [VideoUploadController::class, 'init'])->middleware('not_blocked');
        Route::post('/videos/upload/{uploadId}/chunk', [VideoUploadController::class, 'chunk'])->middleware('not_blocked');
        Route::post('/videos/upload/{uploadId}/complete', [VideoUploadController::class, 'complete'])->middleware('not_blocked');
        Route::delete('/videos/upload/{uploadId}', [VideoUploadController::class, 'destroy']);

        // Загрузка ролика одним запросом (для мелких файлов) — заблокированному недоступна (ТЗ 13.3)
        Route::post('/videos', [VideoController::class, 'store'])->middleware('not_blocked');
        Route::post('/videos/{video}/like', [VideoController::class, 'like'])->middleware('throttle:60,1');
        Route::delete('/videos/{video}', [VideoController::class, 'destroy'])->can('delete', 'video');
    });

    Route::get('/videos/{video}', [VideoController::class, 'show']);
    // Просмотр из ленты доступен и гостю (лента публичная)
    Route::post('/videos/{video}/view', [VideoController::class, 'view'])->middleware('throttle:60,1');
});
