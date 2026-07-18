<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class InitVideoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'      => ['required', 'string', 'max:255'],
            'tags'       => ['nullable', 'array', 'max:10'],
            'tags.*'     => ['string', 'max:30'],
            // Имя/расширение нужны только чтобы выбрать контейнер собранного файла
            'filename'   => ['nullable', 'string', 'max:255'],
            'extension'  => ['nullable', 'string', 'max:10'],
            // Необязательная подсказка размера — для клиентской индикации прогресса
            'total_size' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
