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
use App\Events\TariffExpired;
use App\Events\TariffRequestApproved;
use App\Events\TariffRequestRejected;
use App\Listeners\LogSearchQuery;
use App\Listeners\SendChatReplyPush;
use App\Listeners\SendListingApprovedPush;
use App\Listeners\SendListingRejectedPush;
use App\Listeners\SendOrderApprovedPush;
use App\Listeners\SendOrderCanceledToStorePush;
use App\Listeners\SendOrderRejectedPush;
use App\Listeners\SendOrderStatusPush;
use App\Listeners\SendOrderToStoreOwnerPush;
use App\Listeners\SendSmsCode;
use App\Listeners\SendStoreApprovedPush;
use App\Listeners\SendStoreRejectedPush;
use App\Listeners\SendTariffExpiredPush;
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
        // Истёк платный тариф: что перешёл на бесплатный и сколько скрыто
        TariffExpired::class => [
            SendTariffExpiredPush::class,
        ],
        // Оформленный заказ сразу уходит владельцу магазина: решение по
        // заказу принимает он, доставляет тоже он
        OrderPlaced::class => [
            SendOrderToStoreOwnerPush::class,
        ],
        // Продавец принял заказ / отказался — покупателю уходит его решение
        OrderApproved::class => [
            SendOrderApprovedPush::class,
        ],
        OrderRejected::class => [
            SendOrderRejectedPush::class,
        ],
        // Одно событие на два адресата: доставлен — покупателю,
        // отменён покупателем — продавцу
        OrderStatusChanged::class => [
            SendOrderStatusPush::class,
            SendOrderCanceledToStorePush::class,
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
