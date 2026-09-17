<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCodeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'phone'     => ['required', 'string', 'regex:/^\+?\d{8,15}$/'],
            'code'      => ['required', 'string', 'digits:' . (int) config('sms.code_length', 6)],
            'fcm_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
