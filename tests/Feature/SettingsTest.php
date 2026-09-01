<?php

use App\Models\Setting;
use App\Models\User;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use App\Services\Sms\SmsCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function issueOtpCode(string $phone, string $code, ?int $ttl = null): SmsCode
{
    return app(SmsCodeRepositoryInterface::class)->create($phone, $code, $ttl ?? config('sms.ttl'));
}

// User::factory() assumes an `email_verified_at` column that this project's
// users table does not have — build the row directly with real columns instead.
function actingAsSettingsRole(string $role): User
{
    $user = User::factory()->create(['name' => 'Test ' . $role, 'role' => $role]);
    test()->actingAs($user);

    return $user;
}

it('lets admin view the settings page', function () {
    actingAsSettingsRole('admin');

    $this->get(route('settings.index'))->assertOk();
});

it('forbids manager from viewing the settings page', function () {
    actingAsSettingsRole('manager');

    $this->get(route('settings.index'))->assertForbidden();
});

it('lets admin toggle the manager news permission', function () {
    actingAsSettingsRole('admin');

    $this->patch(route('settings.manager-permissions'), ['can_manage_news' => true])
        ->assertRedirect();

    expect((bool) Setting::get('manager_can_manage_news'))->toBeTrue();
});

it('blocks manager from news routes until the permission is granted, then allows it', function () {
    actingAsSettingsRole('manager');

    $this->get(route('news.index'))->assertForbidden();

    Setting::set('manager_can_manage_news', '1');

    $this->get(route('news.index'))->assertOk();
});

it('returns monitoring JSON only to admin', function () {
    actingAsSettingsRole('admin');
    $this->get(route('settings.monitoring'))
        ->assertOk()
        ->assertJsonStructure([
            'queues' => ['ok', 'pending', 'failed', 'worker', 'checked_at'],
            'ws'     => ['ok', 'host', 'port', 'driver', 'checked_at'],
            'fcm'    => ['ok', 'configured', 'project_id', 'tokens', 'checked_at'],
            'sms'    => ['connected', 'configured', 'device', 'address', 'clients', 'last_sync_at'],
        ]);

    actingAsSettingsRole('manager');
    $this->get(route('settings.monitoring'))->assertForbidden();
});

it('lets admin update the boost interval and validates it', function () {
    actingAsSettingsRole('admin');

    $this->patch(route('settings.boost'), ['boost_interval_hours' => 48])->assertRedirect();
    expect((int) Setting::get('boost_interval_hours'))->toBe(48);

    // Ноль/пусто отклоняются валидацией
    $this->patch(route('settings.boost'), ['boost_interval_hours' => 0])
        ->assertSessionHasErrors('boost_interval_hours');
});

it('forbids manager from changing the boost interval', function () {
    actingAsSettingsRole('manager');

    $this->patch(route('settings.boost'), ['boost_interval_hours' => 12])->assertForbidden();
});

it('shows admin the recent OTP codes with their status', function () {
    actingAsSettingsRole('admin');

    $owner = User::factory()->create(['name' => 'Кодовладелец', 'phone' => '+99361000001', 'role' => 'user']);

    issueOtpCode($owner->phone, '111111');
    $used = issueOtpCode('+99361000002', '222222');
    app(SmsCodeRepositoryInterface::class)->markUsed($used);

    $codes = collect($this->getJson(route('settings.otp-codes'))->assertOk()->json('codes'))
        ->keyBy('code');

    expect($codes)->toHaveCount(2);
    expect($codes['111111']['status'])->toBe('active');
    expect($codes['111111']['user_name'])->toBe('Кодовладелец');
    expect($codes['111111']['expires_in'])->toBeGreaterThan(0);
    expect($codes['222222']['status'])->toBe('used');
    // Номер без зарегистрированного пользователя — фронт покажет «Новый номер»
    expect($codes['222222']['user_name'])->toBeNull();
});

it('drops OTP codes from the monitor once their ttl runs out', function () {
    actingAsSettingsRole('admin');

    issueOtpCode('+99361000001', '111111');

    $this->travel(config('sms.ttl') + 1)->seconds();

    expect($this->getJson(route('settings.otp-codes'))->assertOk()->json('codes'))->toBeEmpty();
});

// Индекс ленты живёт до самой долгой своей записи, а не до последней добавленной
it('keeps live OTP codes in the monitor after a short-lived one expires', function () {
    actingAsSettingsRole('admin');

    issueOtpCode('+99361000001', '111111');
    issueOtpCode('+99361000002', '222222', 30);

    $this->travel(31)->seconds();

    $codes = $this->getJson(route('settings.otp-codes'))->assertOk()->json('codes');

    expect($codes)->toHaveCount(1);
    expect($codes[0]['code'])->toBe('111111');
});

it('renders the settings page with the OTP codes already loaded', function () {
    actingAsSettingsRole('admin');

    issueOtpCode('+99361000001', '111111');

    $this->get(route('settings.index'))
        ->assertInertia(fn ($page) => $page->where('otpCodes.0.code', '111111'));
});

it('filters OTP codes by phone fragment', function () {
    actingAsSettingsRole('admin');

    issueOtpCode('+99361000001', '111111');
    issueOtpCode('+99362000002', '222222');

    $codes = $this->getJson(route('settings.otp-codes', ['phone' => '62000002']))->assertOk()->json('codes');

    expect($codes)->toHaveCount(1);
    expect($codes[0]['code'])->toBe('222222');
});

it('forbids manager from reading OTP codes', function () {
    actingAsSettingsRole('manager');

    $this->getJson(route('settings.otp-codes'))->assertForbidden();
});
