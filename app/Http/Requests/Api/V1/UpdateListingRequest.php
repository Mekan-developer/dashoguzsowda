<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTradeFields;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateListingRequest extends FormRequest
{
    use ValidatesTradeFields;

    /** Владение проверяется route-middleware can:update,listing (ListingPolicy) */
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            ...$this->tradeFieldRules(),
            'title'        => ['sometimes', 'required', 'string', 'max:255'],
            'description'  => ['sometimes', 'required', 'string', 'max:5000'],
            'type'         => ['sometimes', 'required', 'in:goods,services'],
            // 0/1 вместо false/true: sqlite биндит false как '' и не матчит 0
            'category_id'  => ['sometimes', 'required', Rule::exists('categories', 'id')->where('is_active', 1)],
            'region_id'    => ['sometimes', 'required', Rule::exists('regions', 'id')->where('is_hidden', 0)],
            'city_id'      => ['required_with:region_id', Rule::exists('cities', 'id')->where('is_hidden', 0)->where('region_id', $this->input('region_id'))],
            'district_id'  => ['nullable', Rule::exists('districts', 'id')->where('city_id', $this->input('city_id'))],
            'price'        => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'phone'        => ['nullable', 'string', 'regex:/^\+993\d{8}$/'],
            'tags'         => ['nullable', 'array', 'max:10'],
            'tags.*'       => ['string', 'max:30'],
            'location'     => ['nullable', 'array'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            'photos'       => ['sometimes', 'array', 'max:8'],
            'photos.*'     => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'remove_media_ids'   => ['sometimes', 'array'],
            'remove_media_ids.*' => [
                'integer',
                Rule::exists('listing_media', 'id')->where('listing_id', $this->route('listing')?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('messages.phone_format_invalid'),
        ];
    }

    /** Итоговое число фото после удаления/добавления: 1–8 + leaf-проверка категории */
    public function withValidator(Validator $validator): void
    {
        $this->validateWholesaleAllowed($validator);

        $validator->after(function (Validator $v) {
            if (! $v->errors()->has('category_id') && $this->filled('category_id')) {
                $categoryId = (int) $this->input('category_id');
                if (Category::where('parent_id', $categoryId)->where('is_active', true)->exists()) {
                    $v->errors()->add('category_id', __('messages.category_must_be_leaf'));
                }
            }

            if ($v->errors()->has('photos') || $v->errors()->has('remove_media_ids')) {
                return;
            }

            $current = $this->route('listing')?->media()->count() ?? 0;
            $total   = $current
                - count($this->input('remove_media_ids', []))
                + count($this->file('photos', []));

            if ($total < 1) {
                $v->errors()->add('photos', __('messages.listing_photos_required'));
            } elseif ($total > 8) {
                $v->errors()->add('photos', __('messages.listing_photos_limit'));
            }
        });
    }
}
