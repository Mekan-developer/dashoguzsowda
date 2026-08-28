<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'rejection_reason_id' => [
                'required',
                Rule::exists('rejection_reasons', 'id')->where('type', 'store'),
            ],
        ];
    }
}
