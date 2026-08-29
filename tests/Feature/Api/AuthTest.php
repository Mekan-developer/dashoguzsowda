<?php

use App\Models\FcmToken;
use App\Models\User;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use App\Services\Sms\SmsCode;
use Illuminate\Support\Facades\Log;

const PHONE = '+99361123456';

/** Коды живут в кэше, а не в таблице — читаем их через репозиторий. */
function storedCode(string $phone = PHONE): ?SmsCode
{
    return app(SmsCodeRepositoryInterface::class)->findLatest($phone);
}

function requestCode(string $phone = PHONE): string
{
    test()->postJson('/api/v1/auth/send-code', ['phone' => $phone])->assertOk();

    return storedCode($phone)->code;
}

it('sends sms code and stores it', function () {
    Log::spy();

    $this->postJson('/api/v1/auth/send-code', ['phone' => PHONE])
        ->assertOk()
        ->assertJsonPath('data.expires_in', config('sms.ttl'));

    $code = storedCode();
    expect($code)->not->toBeNull()
        ->and($code->usedAt)->toBeNull()
        ->and(strlen($code->code))->toBe(config('sms.code_length'));

    // OTP пишется в laravel.log (dev-реализация LogSmsService)
    Log::shouldHaveReceived('info')->withArgs(fn ($msg) => str_contains($msg, $code->code))->once();
});

it('rejects invalid phone format', function () {
    $this->postJson('/api/v1/auth/send-code', ['phone' => 'not-a-phone'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('enforces resend cooldown', function () {
    requestCode();

    $this->postJson('/api/v1/auth/send-code', ['phone' => PHONE])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');

    $this->travel(config('sms.resend_cooldown') + 1)->seconds();

    $this->postJson('/api/v1/auth/send-code', ['phone' => PHONE])->assertOk();
});

// Кулдаун живёт отдельным ключом: истёкший код не должен открывать повторную отправку
it('keeps the resend cooldown when the code ttl is shorter than it', function () {
    config(['sms.ttl' => 10]);

    requestCode();

    $this->travel(11)->seconds();

    expect(storedCode())->toBeNull();

    $this->postJson('/api/v1/auth/send-code', ['phone' => PHONE])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('registers a new user on verify', function () {
    $code = requestCode();

    $response = $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $code])
        ->assertOk()
        ->assertJsonPath('data.is_new', true)
        ->assertJsonPath('data.user.phone', PHONE);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();

    $user = User::where('phone', PHONE)->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('user')
        ->and($user->status)->toBe('active');

    expect(storedCode()->usedAt)->not->toBeNull();
});

it('logs in an existing user on verify', function () {
    $user = User::factory()->create(['phone' => PHONE]);
    $code = requestCode();

    $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $code, 'fcm_token' => 'fcm-123'])
        ->assertOk()
        ->assertJsonPath('data.is_new', false)
        ->assertJsonPath('data.user.id', $user->id);

    expect(User::count())->toBe(1);

    $token = FcmToken::where('user_id', $user->id)->first();
    expect($token)->not->toBeNull()
        ->and($token->token)->toBe('fcm-123');
});

it('registers multiple fcm tokens for the same user (multi-device)', function () {
    $user = User::factory()->create(['phone' => PHONE]);

    $this->postJson('/api/v1/auth/verify', [
        'phone' => PHONE, 'code' => requestCode(), 'fcm_token' => 'device-a',
    ])->assertOk();

    $this->travel(config('sms.resend_cooldown') + 1)->seconds();

    $this->postJson('/api/v1/auth/verify', [
        'phone' => PHONE, 'code' => requestCode(), 'fcm_token' => 'device-b',
    ])->assertOk();

    expect(FcmToken::where('user_id', $user->id)->pluck('token')->sort()->values()->all())
        ->toBe(['device-a', 'device-b']);
});

it('rejects a wrong code', function () {
    requestCode();

    $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    expect(storedCode()->attempts)->toBe(1)
        ->and(User::count())->toBe(0);
});

it('rejects an expired code', function () {
    $code = requestCode();

    $this->travel(config('sms.ttl') + 1)->seconds();

    $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $code])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('blocks the code after too many wrong attempts', function () {
    $code = requestCode();

    foreach (range(1, config('sms.max_attempts')) as $i) {
        $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => '000000'])
            ->assertStatus(422);
    }

    // Даже правильный код больше не принимается
    $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $code])
        ->assertStatus(422);
});

it('invalidates previous code when a new one is requested', function () {
    $oldCode = requestCode();

    $this->travel(config('sms.resend_cooldown') + 1)->seconds();
    requestCode();

    $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $oldCode])
        ->assertStatus(422);
});

it('denies login to a blocked user', function () {
    User::factory()->blocked()->create(['phone' => PHONE]);
    $code = requestCode();

    $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $code])
        ->assertStatus(403);
});

it('revokes the token on logout', function () {
    $code = requestCode();
    $token = $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $code])
        ->json('data.token');

    $headers = ['Authorization' => "Bearer {$token}"];

    $this->getJson('/api/v1/profile', $headers)->assertOk();
    $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

    // Guard кеширует пользователя между запросами одного теста — сбрасываем
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/profile', $headers)->assertStatus(401);
});

it('removes the fcm token on logout when provided', function () {
    $code = requestCode();
    $response = $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $code, 'fcm_token' => 'fcm-123'])
        ->json('data');

    $headers = ['Authorization' => "Bearer {$response['token']}"];
    $user = User::where('phone', PHONE)->first();

    expect(FcmToken::where('user_id', $user->id)->where('token', 'fcm-123')->exists())->toBeTrue();

    $this->postJson('/api/v1/auth/logout', ['fcm_token' => 'fcm-123'], $headers)->assertOk();

    expect(FcmToken::where('user_id', $user->id)->where('token', 'fcm-123')->exists())->toBeFalse();
});

it('keeps the fcm token on logout when not provided', function () {
    $code = requestCode();
    $response = $this->postJson('/api/v1/auth/verify', ['phone' => PHONE, 'code' => $code, 'fcm_token' => 'fcm-123'])
        ->json('data');

    $headers = ['Authorization' => "Bearer {$response['token']}"];
    $user = User::where('phone', PHONE)->first();

    $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

    expect(FcmToken::where('user_id', $user->id)->where('token', 'fcm-123')->exists())->toBeTrue();
});

it('requires auth for profile endpoints', function () {
    $this->getJson('/api/v1/profile')->assertStatus(401);
    $this->putJson('/api/v1/profile', [])->assertStatus(401);
});
