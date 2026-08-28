<?php

namespace App\Providers;

use App\Models\Listing;
use App\Observers\ListingObserver;
use App\Repositories\BannerRepository;
use App\Repositories\CategoryIconRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ChatRepository;
use App\Repositories\ComplaintRepository;
use App\Repositories\FavoriteRepository;
use App\Repositories\FcmTokenRepository;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use App\Repositories\Interfaces\CategoryIconRepositoryInterface;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ChatRepositoryInterface;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use App\Repositories\Interfaces\FavoriteRepositoryInterface;
use App\Repositories\Interfaces\FcmTokenRepositoryInterface;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\NewsRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Repositories\Interfaces\PushNotificationRepositoryInterface;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use App\Repositories\Interfaces\RegionRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\SearchQueryLogRepositoryInterface;
use App\Repositories\Interfaces\SearchRecentRepositoryInterface;
use App\Repositories\Interfaces\SettingRepositoryInterface;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use App\Repositories\Interfaces\TariffRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use App\Repositories\ListingRepository;
use App\Repositories\NewsRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PushNotificationRepository;
use App\Repositories\ReasonRepository;
use App\Repositories\RegionRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\SearchQueryLogRepository;
use App\Repositories\SearchRecentRepository;
use App\Repositories\SettingRepository;
use App\Repositories\SmsCodeRepository;
use App\Repositories\StoreRepository;
use App\Repositories\TariffRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoRepository;
use App\Services\Sms\LocalModemSmsService;
use App\Services\Sms\LogSmsService;
use App\Services\Sms\SmsSenderInterface;
use App\Services\Video\FfprobeVideoProbe;
use App\Services\Video\VideoProbeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ListingRepositoryInterface::class, ListingRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(VideoRepositoryInterface::class, VideoRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(CategoryIconRepositoryInterface::class, CategoryIconRepository::class);
        $this->app->bind(TariffRepositoryInterface::class, TariffRepository::class);
        $this->app->bind(ChatRepositoryInterface::class, ChatRepository::class);
        $this->app->bind(NewsRepositoryInterface::class, NewsRepository::class);
        $this->app->bind(RegionRepositoryInterface::class, RegionRepository::class);
        $this->app->bind(ComplaintRepositoryInterface::class, ComplaintRepository::class);
        $this->app->bind(ReviewRepositoryInterface::class, ReviewRepository::class);
        $this->app->bind(SmsCodeRepositoryInterface::class, SmsCodeRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(BannerRepositoryInterface::class, BannerRepository::class);
        $this->app->bind(FavoriteRepositoryInterface::class, FavoriteRepository::class);
        $this->app->bind(FcmTokenRepositoryInterface::class, FcmTokenRepository::class);
        $this->app->bind(PushNotificationRepositoryInterface::class, PushNotificationRepository::class);
        $this->app->bind(ReasonRepositoryInterface::class, ReasonRepository::class);
        $this->app->bind(SettingRepositoryInterface::class, SettingRepository::class);
        $this->app->bind(SearchRecentRepositoryInterface::class, SearchRecentRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, StoreRepository::class);
        $this->app->bind(SearchQueryLogRepositoryInterface::class, SearchQueryLogRepository::class);

        // SMS_DRIVER=log — OTP пишется в laravel.log (dev);
        // SMS_DRIVER=modem — уходит в socket-server → телефон-отправитель (прод).
        $this->app->bind(SmsSenderInterface::class, fn () => config('sms.driver') === 'modem'
            ? $this->app->make(LocalModemSmsService::class)
            : $this->app->make(LogSmsService::class));

        // Длительность роликов при загрузке (в тестах подменяется фейком)
        $this->app->bind(VideoProbeInterface::class, FfprobeVideoProbe::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Listing::observe(ListingObserver::class);
        Auth::guard('web')->setRememberDuration(60 * 24 * 30);
    }
}
