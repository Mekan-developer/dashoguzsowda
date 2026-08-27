<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name_ru' => ['required', 'string', 'max:255'],
            'name_tk' => ['required', 'string', 'max:255'],
        ];
    }
}
