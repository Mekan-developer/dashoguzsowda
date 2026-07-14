<?php

namespace App\Services;

use App\Actions\RegisterFcmTokenAction;
use App\Actions\RemoveFcmTokenAction;
use App\Actions\SendSmsCodeAction;
use App\Actions\VerifySmsCodeAction;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly SendSmsCodeAction $sendSmsCode,
        private readonly VerifySmsCodeAction $verifySmsCode,
        private readonly RegisterFcmTokenAction $registerFcmToken,
        private readonly RemoveFcmTokenAction $removeFcmToken,
    ) {}

    /**
     * Шаг 1: запрос SMS-кода на номер (регистрация и вход — один сценарий).
     */
    public function requestCode(string $phone): void
    {
        $this->sendSmsCode->execute($phone);
    }

    /**
     * Шаг 2: проверка кода. Новый номер — регистрируем, существующий — входим.
     *
     * @return array{user: User, token: string, is_new: bool}
     *
     * @throws AuthorizationException если пользователь заблокирован
     */
    public function verify(string $phone, string $code, ?string $fcmToken = null): array
    {
        $this->verifySmsCode->execute($phone, $code);

        $user = $this->userRepository->findByPhone($phone);
        $isNew = $user === null;

        if ($isNew) {
            // Регистрация только по номеру: пароль не используется мобильным клиентом
            $user = $this->userRepository->create([
                'phone'             => $phone,
                'phone_verified_at' => now(),
                'role'              => 'user',
                'status'            => 'active',
                'password'          => Hash::make(Str::random(40)),
            ]);
        }

        if ($user->isBlocked()) {
            throw new AuthorizationException(__('messages.user_blocked_message'));
        }

        // Созданный из админки с опцией «Подтверждение по SMS» активируется
        // при первом успешном вводе кода
        if ($user->phone_verified_at === null) {
            $user = $this->userRepository->update($user, ['phone_verified_at' => now()]);
        }

        if ($fcmToken) {
            $this->registerFcmToken->execute($user, $fcmToken);
        }

        return [
            'user'   => $user->load('region', 'city'),
            'token'  => $user->createToken('mobile')->plainTextToken,
            'is_new' => $isNew,
        ];
    }

    public function logout(User $user, ?string $fcmToken = null): void
    {
        $user->currentAccessToken()?->delete();

        if ($fcmToken) {
            $this->removeFcmToken->execute($user, $fcmToken);
        }
    }

    /**
     * Смена номера, шаг 1: SMS-код отправляется на НОВЫЙ номер.
     * Уникальность нового номера проверяется в Form Request.
     */
    public function requestPhoneChange(string $newPhone): void
    {
        $this->sendSmsCode->execute($newPhone);
    }

    /**
     * Смена номера, шаг 2: подтверждение кода с нового номера.
     */
    public function confirmPhoneChange(User $user, string $newPhone, string $code): User
    {
        $this->verifySmsCode->execute($newPhone, $code);

        // Повторная проверка уникальности — номер могли занять между шагами
        $taken = $this->userRepository->findByPhone($newPhone);
        if ($taken && $taken->id !== $user->id) {
            throw ValidationException::withMessages([
                'phone' => __('validation.unique', ['attribute' => 'phone']),
            ]);
        }

        return $this->userRepository->update($user, ['phone' => $newPhone]);
    }
}
