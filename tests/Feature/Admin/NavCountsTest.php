<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Бейджи меню — общий проп navCounts. Разделы со своим `counts` (вкладки
 * фильтра) раньше перекрывали общий одноимённый проп, и бейджи меню на этих
 * страницах пропадали: на «Заказах» было 4, на «Магазинах» — ничего.
 */
it('keeps the sidebar badges on pages that have their own counts', function (string $routeName) {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('counts')
            ->has('navCounts.pendingOrders')
            ->has('navCounts.pendingStores')
            ->has('navCounts.pendingListings')
            ->etc());
})->with([
    'stores'          => ['stores.index'],
    'orders'          => ['orders.index'],
    'listings'        => ['listings.index'],
    'videos'          => ['videos.index'],
    'news'            => ['news.index'],
    'complaints'      => ['complaints.index'],
    'reviews'         => ['reviews.index'],
    'tariff requests' => ['tariff-requests.index'],
]);
