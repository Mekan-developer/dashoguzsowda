<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendPushRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'target'    => ['required', 'in:all,selected,filtered'],
            'user_ids'  => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
            'filters'   => ['nullable', 'array'],
            'link_type' => ['nullable', 'string'],
            'link_id'   => ['nullable', 'integer'],
        ];
    }
}
