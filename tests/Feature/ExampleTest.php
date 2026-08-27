<?php

it('redirects the site root to the admin dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});

it('answers the health check', function () {
    $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok']);
});
