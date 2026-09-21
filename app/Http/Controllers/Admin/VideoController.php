<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveVideoAction;
use App\Actions\RejectVideoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectVideoRequest;
use App\Http\Requests\Admin\StoreVideoRequest;
use App\Http\Requests\Admin\UpdateVideoRequest;
use App\Models\User;
use App\Models\Video;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use App\Services\NotificationService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VideoController extends Controller
{
    public function __construct(
        private readonly VideoService $videoService,
        private readonly ApproveVideoAction $approveAction,
        private readonly RejectVideoAction $rejectAction,
        private readonly ReasonRepositoryInterface $reasons,
        private readonly CategoryRepositoryInterface $categories,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $this->notificationService->markSectionSeen($request->user(), 'videos');

        return Inertia::render('Videos/Index', [
            'videos'           => $this->videoService->list($request->only('status', 'search', 'category_id')),
            'categories'       => $this->categories->roots(),
            'rejectionReasons' => $this->reasons->activeRejectionReasons('video'),
            'filters'          => $request->only('status', 'search', 'category_id'),
            'counts'           => $this->videoService->counts(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Videos/Create', [
            'categories' => $this->categories->roots(),
        ]);
    }

    /**
     * Создание ролика от имени выбранного пользователя (сразу approved).
     */
    public function store(StoreVideoRequest $request)
    {
        $owner = User::query()->findOrFail($request->validated('user_id'));

        $data = $request->safe()->only('title', 'tags', 'category_id');
        $data['video'] = $request->file('video');

        $video = $this->videoService->createFromAdmin($owner, $data, $request->durationSeconds());

        return redirect()->route('videos.show', $video)
            ->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function show(Video $video)
    {
        return Inertia::render('Videos/Show', [
            'video'            => $this->videoService->forAdmin($video),
            'categories'       => $this->categories->roots(),
            'rejectionReasons' => $this->reasons->activeRejectionReasons('video'),
        ]);
    }

    public function update(UpdateVideoRequest $request, Video $video)
    {
        $this->videoService->updateFromAdmin($video, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Video $video)
    {
        $this->videoService->delete($video);

        return redirect()->route('videos.index')
            ->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }

    public function approve(Video $video)
    {
        $this->approveAction->execute($video);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.video_approved')]);
    }

    public function reject(RejectVideoRequest $request, Video $video)
    {
        $this->rejectAction->execute($video, $request->validated('rejection_reason_id'));

        return back()->with('toast', ['type' => 'error', 'message' => __('messages.video_rejected')]);
    }
}
