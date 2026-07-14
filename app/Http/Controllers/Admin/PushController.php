<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendPushRequest;
use App\Models\PushNotification;
use App\Models\Region;
use App\Models\Tariff;
use App\Models\User;
use App\Services\PushNotificationService;
use Inertia\Inertia;

class PushController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function index()
    {
        return Inertia::render('Push/Index', [
            'pushNotifications' => PushNotification::with('creator')->latest()->paginate(20),
            'regions'           => Region::all(),
            'tariffs'           => Tariff::where('is_active', true)->get(),
        ]);
    }

    public function send(SendPushRequest $request)
    {
        $data = $request->validated();

        $users = $this->resolveTargetUsers($data);

        $reachedCount = $this->pushNotificationService->sendToUsers(
            $users,
            $data['title'],
            $data['body'],
            [
                'type' => $data['link_type'] ?? 'system',
                'id'   => $data['link_id'] ?? null,
            ],
        );

        PushNotification::create([
            'title'      => $data['title'],
            'body'       => $data['body'],
            'target'     => $data['target'],
            'user_ids'   => $data['user_ids'] ?? null,
            'filters'    => $data['filters'] ?? null,
            'link_type'  => $data['link_type'] ?? null,
            'link_id'    => $data['link_id'] ?? null,
            'sent_count' => $reachedCount,
            'sent_at'    => now(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', [
            'type'    => 'success',
            'message' => __('messages.push_queued', ['count' => $reachedCount]),
        ]);
    }

    private function resolveTargetUsers(array $data)
    {
        $query = User::where('role', 'user')->where('status', 'active');

        if ($data['target'] === 'selected' && ! empty($data['user_ids'])) {
            $query->whereIn('id', $data['user_ids']);
        } elseif ($data['target'] === 'filtered' && ! empty($data['filters'])) {
            $filters = $data['filters'];
            if (! empty($filters['region_id'])) {
                $query->where('region_id', $filters['region_id']);
            }
            if (! empty($filters['tariff_id'])) {
                $query->where('tariff_id', $filters['tariff_id']);
            }
        }

        return $query->get();
    }
}
