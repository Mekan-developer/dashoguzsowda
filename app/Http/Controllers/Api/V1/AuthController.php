<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LogoutRequest;
use App\Http\Requests\Api\V1\SendCodeRequest;
use App\Http\Requests\Api\V1\VerifyCodeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Запрос SMS-кода (регистрация и вход — единый сценарий).
     * POST /api/v1/auth/send-code
     */
    public function sendCode(SendCodeRequest $request)
    {
        $this->authService->requestCode($request->validated('phone'));

        return response()->json([
            'data' => [
                'expires_in'   => config('sms.ttl'),
                'resend_after' => config('sms.resend_cooldown'),
            ],
            'message' => __('messages.sms_code_sent'),
        ]);
    }

    /**
     * Проверка кода: новый номер регистрируется, существующий входит.
     * POST /api/v1/auth/verify
     */
    public function verify(VerifyCodeRequest $request)
    {
        $result = $this->authService->verify(
            $request->validated('phone'),
            $request->validated('code'),
            $request->validated('fcm_token'),
        );

        return response()->json([
            'data' => [
                'token'  => $result['token'],
                'is_new' => $result['is_new'],
                'user'   => new UserResource($result['user']),
            ],
            'message' => __('messages.auth_success'),
        ]);
    }

    /**
     * Отзыв текущего токена. Опционально принимает fcm_token — если передан,
     * дополнительно удаляет его из таблицы device-токенов (см. prompt_fcm_backend.md §2.3).
     * POST /api/v1/auth/logout
     *
     * @authenticated
     */
    public function logout(LogoutRequest $request)
    {
        $this->authService->logout($request->user(), $request->validated('fcm_token'));

        return response()->json([
            'data'    => null,
            'message' => __('messages.logged_out'),
        ]);
    }
}
