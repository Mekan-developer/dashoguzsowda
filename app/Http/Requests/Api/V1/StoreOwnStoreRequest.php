<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesStoreFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** POST /v1/my/store — создание своего магазина. */
class StoreOwnStoreRequest extends FormRequest
{
    use ValidatesStoreFields;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return $this->storeFieldRules('required');
    }

    public function withValidator(Validator $validator): void
    {
        // По умолчанию магазин розничный — как и колонка sells_retail в БД
        $this->validateTradeFlags($validator, retailDefault: true, wholesaleDefault: false);
    }
}
