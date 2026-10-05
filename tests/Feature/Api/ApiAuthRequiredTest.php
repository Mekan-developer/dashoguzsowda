<?php

/*
 * Приложением пользуются только зарегистрированные (у каждого с регистрации
 * есть тариф), поэтому без токена открыты лишь «О нас» и вход по SMS.
 */

it('rejects guests on content endpoints', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    '/api/v1/banners',
    '/api/v1/categories',
    '/api/v1/regions',
    '/api/v1/complaint-reasons',
    '/api/v1/news',
    '/api/v1/stores',
    '/api/v1/stores/popular',
    '/api/v1/search/popular',
    '/api/v1/listings',
    '/api/v1/videos',
]);

it('keeps the about page open for guests', function () {
    $this->getJson('/api/v1/about')->assertOk();
});

it('keeps sms login open for guests', function () {
    // Не 401: дальше запрос упирается только в валидацию
    $this->postJson('/api/v1/auth/send-code', [])->assertUnprocessable();
    $this->postJson('/api/v1/auth/verify', [])->assertUnprocessable();
});

it('serves content to an authenticated client', function () {
    actingAsClient();

    $this->getJson('/api/v1/banners')->assertOk();
});
