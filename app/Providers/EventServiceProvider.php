<?php

namespace App\Providers;

use App\Events\AdminReplied;
use App\Events\ListingApproved;
use App\Events\ListingRejected;
use App\Events\ListingSearched;
use App\Events\OrderApproved;
use App\Events\OrderPlaced;
use App\Events\OrderRejected;
use App\Events\OrderStatusChanged;
use App\Events\SmsCodeRequested;
use App\Events\StoreApproved;
use App\Events\StoreRejected;
use App\Events\TariffRequestApproved;
use App\Events\TariffRequestRejected;
use App\Listeners\LogSearchQuery;
use App\Listeners\SendChatReplyPush;
use App\Listeners\SendListingApprovedPush;
use App\Listeners\SendListingRejectedPush;
use App\Listeners\SendOrderApprovedPush;
use App\Listeners\SendOrderRejectedPush;
use App\Listeners\SendOrderStatusPush;
use App\Listeners\SendOrderToStoreOwnersPush;
use App\Listeners\SendSmsCode;
use App\Listeners\SendStoreApprovedPush;
use App\Listeners\SendStoreRejectedPush;
use App\Listeners\SendTariffRequestApprovedPush;
use App\Listeners\SendTariffRequestRejectedPush;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ListingApproved::class => [
            SendListingApprovedPush::class,
        ],
        ListingRejected::class => [
            SendListingRejectedPush::class,
        ],
        StoreApproved::class => [
            SendStoreApprovedPush::class,
        ],
        StoreRejected::class => [
            SendStoreRejectedPush::class,
        ],
        TariffRequestApproved::class => [
            SendTariffRequestApprovedPush::class,
        ],
        TariffRequestRejected::class => [
            SendTariffRequestRejectedPush::class,
        ],
        // Оформленный заказ сразу уходит владельцам магазинов — отвечают они
        // первыми, админ подтверждает заказ уже по их ответам
        OrderPlaced::class => [
            SendOrderToStoreOwnersPush::class,
        ],
        OrderApproved::class => [
            SendOrderApprovedPush::class,
        ],
        OrderRejected::class => [
            SendOrderRejectedPush::class,
        ],
        OrderStatusChanged::class => [
            SendOrderStatusPush::class,
        ],
        AdminReplied::class => [
            SendChatReplyPush::class,
        ],
        SmsCodeRequested::class => [
            SendSmsCode::class,
        ],
        ListingSearched::class => [
            LogSearchQuery::class,
        ],
    ];
}
