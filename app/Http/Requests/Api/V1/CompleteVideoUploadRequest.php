<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Финализация загрузки: upload_id приходит в пути, тело не требуется.
 * Form Request здесь ради единообразия и авторизации (CLAUDE.md).
 */
class CompleteVideoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
