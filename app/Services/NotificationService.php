<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository,
    ) {}

    public function forUser(User $user): array
    {
        $items = $this->notificationRepository->pendingItems();
        $dismissed = array_flip($this->notificationRepository->dismissedKeys($user->id));

        $list = [
            ...$items['newUsers']->map(fn ($u) => $this->entry(
                "user:{$u->id}", 'newUser', $u->name ?: $u->phone, 'users', 'users.show', $u->id, $u->created_at,
            )),
            ...$items['pendingListings']->map(fn ($l) => $this->entry(
                "listing:{$l->id}", 'pendingListing', $l->title, 'listing', 'listings.show', $l->id, $l->created_at,
            )),
            ...$items['pendingVideos']->map(fn ($v) => $this->entry(
                "video:{$v->id}", 'pendingVideo', $v->title, 'video', 'videos.show', $v->id, $v->created_at,
            )),
            ...$items['unreadChats']->map(fn ($u) => $this->entry(
                "chat:{$u->id}", 'unreadChat', $u->name ?: $u->phone, 'chat', 'chat.show', $u->id, now(),
            )),
            ...$items['newComplaints']->map(fn ($c) => $this->entry(
                "complaint:{$c->id}", 'newComplaint', $c->listing?->title ?? Str::limit($c->text, 40), 'flag', 'complaints.index', null, $c->created_at,
            )),
            ...$items['pendingReviews']->map(fn ($r) => $this->entry(
                "review:{$r->id}", 'pendingReview', Str::limit($r->text, 40), 'star', 'reviews.index', null, $r->created_at,
            )),
        ];

        $list = array_values(array_filter($list, fn ($entry) => !isset($dismissed[$entry['key']])));
        usort($list, fn ($a, $b) => $b['createdAt'] <=> $a['createdAt']);

        return $list;
    }

    public function dismiss(User $user, string $key): void
    {
        $this->notificationRepository->dismiss($user->id, $key);
    }

    public function markSectionSeen(User $user, string $section): void
    {
        $this->notificationRepository->markSectionSeen($user->id, $section);
    }

    private function entry(string $key, string $type, ?string $label, string $icon, string $routeName, ?int $routeParam, $createdAt): array
    {
        return [
            'key'        => $key,
            'type'       => $type,
            'label'      => $label,
            'icon'       => $icon,
            'routeName'  => $routeName,
            'routeParam' => $routeParam,
            'createdAt'  => optional($createdAt)->toIso8601String(),
        ];
    }
}
