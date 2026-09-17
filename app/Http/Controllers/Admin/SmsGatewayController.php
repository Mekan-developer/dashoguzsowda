<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestSmsRequest;
use App\Services\Sms\SmsGatewayStatusService;
use App\Services\Sms\SmsSenderInterface;
use Illuminate\Http\JsonResponse;

class SmsGatewayController extends Controller
{
    public function status(SmsGatewayStatusService $smsStatus): JsonResponse
    {
        return response()->json($smsStatus->resolve());
    }

    /**
     * Отправить тестовый OTP через текущий SMS-драйвер.
     */
    public function test(SendTestSmsRequest $request)
    {
        $phone = $request->validated('phone') ?: $request->user()->phone;
        $length = (int) config('sms.code_length', 6);
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        $code = (string) random_int($min, $max);

        try {
            app(SmsSenderInterface::class)->sendOtp($phone, $code);

            return back()->with('toast', ['type' => 'success', 'message' => __('messages.sms_test_sent')]);
        } catch (\Throwable $e) {
            return back()->with('toast', ['type' => 'error', 'message' => __('messages.sms_test_failed') . ': ' . $e->getMessage()]);
        }
    }
}
