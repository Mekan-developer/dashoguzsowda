<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateAboutPageAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AboutResource;
use App\Repositories\Interfaces\SettingRepositoryInterface;

/**
 * GET /api/v1/about — текст «О нас» для одноимённого экрана приложения.
 *
 * Без авторизации: экран доступен и до входа, из онбординга.
 */
class AboutController extends Controller
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {}

    public function __invoke()
    {
        return response()->json([
            'data' => new AboutResource([
                'content_ru' => $this->settings->get(UpdateAboutPageAction::KEY_RU),
                'content_tk' => $this->settings->get(UpdateAboutPageAction::KEY_TK),
            ]),
            'message' => 'Success',
        ]);
    }
}
