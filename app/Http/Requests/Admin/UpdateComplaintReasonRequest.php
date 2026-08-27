<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Правка причины жалобы. `sometimes` — по той же причине, что и у причин
 * отклонения: страница настроек переключает is_active отдельным запросом,
 * без остальных полей.
 */
class UpdateComplaintReasonRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name_ru'   => ['sometimes', 'required', 'string', 'max:255'],
            'name_tk'   => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
