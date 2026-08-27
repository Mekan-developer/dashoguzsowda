<?php

it('returns an empty popular list without auth when nothing was searched yet', function () {
    $this->getJson('/api/v1/search/popular')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('surfaces the most frequently searched query first', function () {
    $this->getJson('/api/v1/listings?search=iPhone')->assertOk();
    $this->getJson('/api/v1/listings?search=iPhone')->assertOk();
    $this->getJson('/api/v1/listings?search=Toyota')->assertOk();

    $popular = $this->getJson('/api/v1/search/popular')->assertOk()->json('data');

    expect($popular[0])->toBe('iPhone')
        ->and($popular)->toContain('Toyota');
});

it('treats different casing as the same query', function () {
    $this->getJson('/api/v1/listings?search=iphone')->assertOk();
    $this->getJson('/api/v1/listings?search=IPHONE')->assertOk();

    $popular = $this->getJson('/api/v1/search/popular')->assertOk()->json('data');

    expect($popular)->toHaveCount(1);
});
