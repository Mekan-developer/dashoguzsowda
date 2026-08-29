<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OtpCodeFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            // Фильтр по части номера — вводится вручную, поэтому длину режем.
            'phone' => 'nullable|string|max:20',
        ];
    }
}
