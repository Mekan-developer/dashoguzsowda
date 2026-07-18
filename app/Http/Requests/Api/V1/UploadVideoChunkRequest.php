<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Тело запроса — сырой бинарный поток части (application/octet-stream),
 * поэтому валидируется только порядковый номер части из query (?index=).
 */
class UploadVideoChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
