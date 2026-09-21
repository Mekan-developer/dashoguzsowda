<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('role')) {
            $this->merge(['role' => 'user']);
        }

        foreach (['region_id', 'city_id', 'district_id', 'email', 'gender', 'birth_date', 'name', 'password', 'password_confirmation'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        $isStaff = in_array($this->input('role'), ['admin', 'manager'], true);

        return [
            'role' => ['required', 'in:user,admin,manager'],
            'phone' => ['required', 'string', 'regex:/^\+993\d{8}$/', 'unique:users,phone'],
            'activation' => [
                Rule::excludeIf($isStaff),
                Rule::requiredIf(! $isStaff),
                'in:active,sms',
            ],
            'email' => [
                Rule::excludeIf(! $isStaff),
                Rule::requiredIf($isStaff),
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                Rule::excludeIf(! $isStaff),
                Rule::requiredIf($isStaff),
                'string',
                'confirmed',
                Password::min(8),
            ],
            'region_id' => [
                Rule::requiredIf(! $isStaff),
                'nullable',
                'exists:regions,id',
            ],
            'city_id' => ['nullable', Rule::exists('cities', 'id')->where('region_id', $this->input('region_id'))],
            'district_id' => ['nullable', Rule::exists('districts', 'id')->where('city_id', $this->input('city_id'))],
            'name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => __('messages.phone_already_registered'),
            'phone.regex' => __('messages.phone_format_invalid'),
        ];
    }
}
