<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendPushRequest;
use App\Services\PushBroadcastService;
use Inertia\Inertia;

class PushController extends Controller
{
    public function __construct(
        private readonly PushBroadcastService $pushBroadcastService,
    ) {}

    public function index()
    {
        return Inertia::render('Push/Index', [
            'pushNotifications' => $this->pushBroadcastService->history(),
            'regions'           => $this->pushBroadcastService->regionOptions(),
            'tariffs'           => $this->pushBroadcastService->tariffOptions(),
        ]);
    }

    public function send(SendPushRequest $request)
    {
        $reachedCount = $this->pushBroadcastService->send($request->validated(), $request->user());

        return back()->with('toast', [
            'type'    => 'success',
            'message' => __('messages.push_queued', ['count' => $reachedCount]),
        ]);
    }
}
