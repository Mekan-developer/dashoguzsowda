<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesStoreFields;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** PUT /v1/my/store — правка своего магазина. */
class UpdateOwnStoreRequest extends FormRequest
{
    use ValidatesStoreFields;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $rules = $this->storeFieldRules('sometimes');

        // Город проверяем против региона магазина, если регион не переслали
        if (! $this->has('region_id') && $this->store()) {
            $rules['city_id'] = ['sometimes', 'exists:cities,id'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $store = $this->store();

        $this->validateTradeFlags(
            $validator,
            retailDefault: (bool) ($store?->sells_retail ?? true),
            wholesaleDefault: (bool) ($store?->sells_wholesale ?? false),
        );

        $this->validatePaymentMethods($validator);

        $validator->after(function (Validator $v) use ($store) {
            $newCount = count($this->file('photos', []));

            if ($newCount === 0 || ! $store) {
                return;
            }

            if ($store->photos()->count() + $newCount > self::MAX_PHOTOS) {
                $v->errors()->add('photos', __('messages.store_photos_limit', ['limit' => self::MAX_PHOTOS]));
            }
        });
    }

    private function store(): ?Store
    {
        return $this->user()?->store;
    }
}
