<?php

namespace App\Http\Requests\Admin;

use App\Models\StorePhoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStoreRequest extends FormRequest
{
    /** Суммарно (существующие + новые) фото галереи на один магазин. */
    private const MAX_PHOTOS = 6;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'phone'       => 'nullable|string|max:32',
            'address'     => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'logo'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
            'crop_x'      => 'nullable|numeric|between:0,100',
            'crop_y'      => 'nullable|numeric|between:0,100',
            'photos'      => 'nullable|array',
            'photos.*'    => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $store = $this->route('store');
            $newCount = count($this->file('photos', []));

            if ($newCount === 0) {
                return;
            }

            $existingCount = StorePhoto::where('store_id', $store->id)->count();

            if ($existingCount + $newCount > self::MAX_PHOTOS) {
                $v->errors()->add('photos', __('messages.store_photos_limit', ['limit' => self::MAX_PHOTOS]));
            }
        });
    }
}
