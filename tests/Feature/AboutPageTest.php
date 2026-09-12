<?php

use App\Actions\UpdateAboutPageAction;
use App\Models\Setting;
use App\Models\User;

/**
 * Страница «О нас»: админ правит текст в настройках, приложение читает его
 * через публичный GET /v1/about (экран открывается и до входа).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('сохраняет обе версии текста из редактора', function () {
    $this->actingAs($this->admin)
        ->patch(route('settings.about'), [
            'about_ru' => '<h2>О нас</h2><p>Доска объявлений</p>',
            'about_tk' => '<p>Bildiriş tagtasy</p>',
        ])
        ->assertRedirect();

    expect(Setting::get(UpdateAboutPageAction::KEY_RU))->toBe('<h2>О нас</h2><p>Доска объявлений</p>')
        ->and(Setting::get(UpdateAboutPageAction::KEY_TK))->toBe('<p>Bildiriş tagtasy</p>');
});

it('чистит разметку перед записью', function () {
    $this->actingAs($this->admin)
        ->patch(route('settings.about'), [
            'about_ru' => '<p onclick="alert(1)">Текст</p><script>alert(2)</script>',
            'about_tk' => null,
        ])
        ->assertRedirect();

    expect(Setting::get(UpdateAboutPageAction::KEY_RU))->toBe('<p>Текст</p>');
});

it('записывает пустую версию как отсутствие текста', function () {
    Setting::set(UpdateAboutPageAction::KEY_TK, '<p>Köne tekst</p>');

    $this->actingAs($this->admin)
        ->patch(route('settings.about'), ['about_ru' => '<p>Есть</p>', 'about_tk' => '<p></p>'])
        ->assertRedirect();

    expect(Setting::get(UpdateAboutPageAction::KEY_TK))->toBeNull();
});

it('отдаёт страницу настроек с текущим текстом', function () {
    Setting::set(UpdateAboutPageAction::KEY_RU, '<p>Текст</p>');

    $this->actingAs($this->admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->where('aboutRu', '<p>Текст</p>'));
});

it('закрывает правку от менеджера — это системная настройка', function () {
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->patch(route('settings.about'), ['about_ru' => '<p>Своё</p>'])
        ->assertForbidden();
});

it('отдаёт текст мобильному приложению без авторизации', function () {
    Setting::set(UpdateAboutPageAction::KEY_RU, '<p>Доска объявлений</p>');
    Setting::set(UpdateAboutPageAction::KEY_TK, '<p>Bildiriş tagtasy</p>');

    $this->getJson('/api/v1/about')
        ->assertOk()
        ->assertJsonPath('data.content_ru', '<p>Доска объявлений</p>')
        ->assertJsonPath('data.content_tk', '<p>Bildiriş tagtasy</p>')
        ->assertJsonPath('data.content', '<p>Доска объявлений</p>');
});

it('отдаёт content на языке запроса', function () {
    Setting::set(UpdateAboutPageAction::KEY_RU, '<p>Русский</p>');
    Setting::set(UpdateAboutPageAction::KEY_TK, '<p>Türkmen</p>');

    $this->getJson('/api/v1/about', ['Accept-Language' => 'tk'])
        ->assertOk()
        ->assertJsonPath('data.content', '<p>Türkmen</p>');
});

it('подставляет вторую версию, если на языке запроса текста нет', function () {
    Setting::set(UpdateAboutPageAction::KEY_RU, '<p>Только по-русски</p>');

    $this->getJson('/api/v1/about', ['Accept-Language' => 'tk'])
        ->assertOk()
        ->assertJsonPath('data.content', '<p>Только по-русски</p>');
});

it('отдаёт пустой текст, пока админ его не заполнил', function () {
    $this->getJson('/api/v1/about')
        ->assertOk()
        ->assertJsonPath('data.content', null)
        ->assertJsonPath('data.content_ru', null);
});
