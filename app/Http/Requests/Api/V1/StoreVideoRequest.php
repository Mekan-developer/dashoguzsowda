<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Video\VideoProbeInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVideoRequest extends FormRequest
{
    /** Максимальная длительность ролика (ТЗ §7.1) */
    public const MAX_DURATION_SECONDS = 60;

    private ?float $duration = null;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'video'  => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-matroska,video/3gpp', 'max:102400'],
            'title'  => ['required', 'string', 'max:255'],
            'tags'   => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
        ];
    }

    /** Файл длиннее минуты отклоняется ещё до постановки в очередь на сжатие */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('video')) {
                return;
            }

            $file = $this->file('video');

            if (! $file) {
                return;
            }

            $probe = app(VideoProbeInterface::class);

            // Нет ffprobe — это не битый файл пользователя, а misconfiguration сервера
            abort_unless($probe->available(), 503, __('messages.video_service_unavailable'));

            $this->duration = $probe->duration($file->getRealPath());

            if ($this->duration === null) {
                $v->errors()->add('video', __('messages.video_unreadable'));
            } elseif ($this->duration > self::MAX_DURATION_SECONDS) {
                $v->errors()->add('video', __('messages.video_too_long'));
            }
        });
    }

    /** Длительность, измеренная при валидации, — сохраняется в записи ролика */
    public function durationSeconds(): int
    {
        return (int) round($this->duration ?? 0);
    }
}
