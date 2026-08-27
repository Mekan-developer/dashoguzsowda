<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Синхронизация онбординга между устройствами.
 *
 * Язык и тема сюда НЕ входят: они остаются device-local
 * (mobile_docs/CLAUDE_CODE_BACKEND_PLAN.md, задача 3 → «Do NOT put in this endpoint»).
 */
class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'onboarding_completed' => ['required', 'boolean'],
        ];
    }
}
