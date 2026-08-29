<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OtpCodeFilterRequest;
use App\Services\Sms\OtpMonitorService;
use Illuminate\Http\JsonResponse;

/**
 * JSON для секции «Мониторинг OTP» на странице настроек: страница опрашивает
 * этот роут (как settings.monitoring), поэтому Inertia-ответа здесь нет.
 */
class OtpMonitorController extends Controller
{
    public function __invoke(OtpCodeFilterRequest $request, OtpMonitorService $otpMonitor): JsonResponse
    {
        return response()->json([
            'codes' => $otpMonitor->recent($request->validated('phone')),
        ]);
    }
}
