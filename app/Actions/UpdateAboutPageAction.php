<?php

namespace App\Actions;

use App\Repositories\Interfaces\SettingRepositoryInterface;
use App\Services\RichTextSanitizer;

/**
 * Сохранение страницы «О нас».
 *
 * Отдельной таблицы у неё нет: это две строки в settings (about_ru / about_tk),
 * как default_app_locale и boost_interval_hours. Страница одна, версий и
 * истории у неё не предполагается — заводить сущность не под что.
 *
 * HTML из визуального редактора чистится перед записью, а не при отдаче:
 * читают текст на каждом открытии экрана «О нас» в мобилке, а пишут раз в
 * полгода.
 */
class UpdateAboutPageAction
{
    public const KEY_RU = 'about_ru';
    public const KEY_TK = 'about_tk';

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly RichTextSanitizer $sanitizer,
    ) {}

    /** @param array{about_ru?: string|null, about_tk?: string|null} $data */
    public function execute(array $data): void
    {
        // Форма присылает обе версии сразу: пустая — это «на этом языке текста
        // нет», и её надо записать, а не пропустить как «поле не менялось»
        $this->settings->set(self::KEY_RU, $this->sanitizer->clean($data['about_ru'] ?? null));
        $this->settings->set(self::KEY_TK, $this->sanitizer->clean($data['about_tk'] ?? null));
    }
}
