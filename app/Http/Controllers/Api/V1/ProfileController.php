<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Actions\RegisterFcmTokenAction;
use App\Http\Requests\Api\V1\ChangePhoneRequest;
use App\Http\Requests\Api\V1\ConfirmPhoneRequest;
use App\Http\Requests\Api\V1\UpdateAvatarRequest;
use App\Http\Requests\Api\V1\UpdateFcmTokenRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuthService $authService,
        private readonly RegisterFcmTokenAction $registerFcmToken,
    ) {}

    /**
     * Текущий профиль.
     * GET /api/v1/profile
     *
     * @authenticated
     */
    public function show(Request $request)
    {
        return response()->json([
            'data'    => new UserResource($request->user()->load('region', 'city', 'district')),
            'message' => 'Success',
        ]);
    }

    /**
     * Частичное обновление профиля (имя, пол, дата рождения, регион, город).
     * PUT /api/v1/profile
     *
     * @authenticated
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $this->userService->update($request->user(), $request->validated());

        return response()->json([
            'data'    => new UserResource($user->load('region', 'city', 'district')),
            'message' => __('messages.updated'),
        ]);
    }

    /**
     * Обновление FCM-токена без повторного логина (см. prompt_fcm_backend.md §2.2).
     * Пустой/null fcm_token игнорируется — удалить конкретный токен устройства без
     * его значения невозможно, а удалять все токены пользователя из-за одного
     * устройства небезопасно для мультидевайса.
     * PUT /api/v1/profile/fcm-token
     *
     * @authenticated
     */
    public function updateFcmToken(UpdateFcmTokenRequest $request)
    {
        $token = $request->validated('fcm_token');

        if ($token) {
            $this->registerFcmToken->execute($request->user(), $token, $request->validated('platform'));
        }

        return response()->json([
            'message' => __('messages.fcm_token_updated'),
        ]);
    }

    /**
     * Загрузка аватара (конвертируется в WebP).
     * POST /api/v1/profile/avatar
     *
     * @authenticated
     */
    public function updateAvatar(UpdateAvatarRequest $request)
    {
        $user = $this->userService->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json([
            'data'    => new UserResource($user->load('region', 'city', 'district')),
            'message' => __('messages.updated'),
        ]);
    }

    /**
     * Удаление аватара.
     * DELETE /api/v1/profile/avatar
     *
     * @authenticated
     */
    public function deleteAvatar(Request $request)
    {
        $user = $this->userService->removeAvatar($request->user());

        return response()->json([
            'data'    => new UserResource($user->load('region', 'city', 'district')),
            'message' => __('messages.updated'),
        ]);
    }

    /**
     * Смена номера, шаг 1: SMS-код на новый номер.
     * POST /api/v1/profile/phone/send-code
     *
     * @authenticated
     */
    public function sendPhoneCode(ChangePhoneRequest $request)
    {
        $this->authService->requestPhoneChange($request->validated('phone'));

        return response()->json([
            'data' => [
                'expires_in'   => config('sms.ttl'),
                'resend_after' => config('sms.resend_cooldown'),
            ],
            'message' => __('messages.sms_code_sent'),
        ]);
    }

    /**
     * Смена номера, шаг 2: подтверждение кода — номер обновляется.
     * POST /api/v1/profile/phone/confirm
     *
     * @authenticated
     */
    public function confirmPhone(ConfirmPhoneRequest $request)
    {
        $user = $this->authService->confirmPhoneChange(
            $request->user(),
            $request->validated('phone'),
            $request->validated('code'),
        );

        return response()->json([
            'data'    => new UserResource($user->load('region', 'city', 'district')),
            'message' => __('messages.phone_changed'),
        ]);
    }
}
