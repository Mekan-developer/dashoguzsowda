<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePreferencesRequest;
use App\Http\Resources\Api\V1\PreferencesResource;
use App\Services\UserService;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * Настройки, синхронизируемые между устройствами.
     * GET /api/v1/preferences
     *
     * @authenticated
     */
    public function show(Request $request)
    {
        return response()->json([
            'data' => new PreferencesResource($request->user()),
        ]);
    }

    /**
     * PUT /api/v1/preferences
     *
     * @authenticated
     */
    public function update(UpdatePreferencesRequest $request)
    {
        $user = $this->userService->setOnboardingCompleted(
            $request->user(),
            $request->boolean('onboarding_completed'),
        );

        return response()->json([
            'data' => new PreferencesResource($user),
        ]);
    }
}
