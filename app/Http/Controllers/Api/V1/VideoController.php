<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MyVideosRequest;
use App\Http\Requests\Api\V1\SearchVideosRequest;
use App\Http\Requests\Api\V1\StoreVideoRequest;
use App\Http\Resources\Api\V1\VideoResource;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function __construct(
        private readonly VideoService $videoService,
    ) {}

    /**
     * Публичная вертикальная лента одобренных роликов (ТЗ §7, экран 9).
     * GET /api/v1/videos
     *
     * Фильтры: search (по названию), category_id (корень), tag, page, limit.
     * С Bearer-токеном у каждого ролика появляется is_liked.
     */
    public function index(SearchVideosRequest $request)
    {
        $videos = $this->videoService->feedForApi(
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
            $request->user('sanctum'),
        );

        return $this->paginated($videos);
    }

    /**
     * Ролики текущего пользователя (все статусы модерации).
     * GET /api/v1/videos/my?status=pending|approved|rejected
     *
     * @authenticated
     */
    public function my(MyVideosRequest $request)
    {
        $videos = $this->videoService->myVideos(
            $request->user(),
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
        );

        return $this->paginated($videos);
    }

    /**
     * Карточка ролика. Чужие непромодерированные ролики недоступны.
     * GET /api/v1/videos/{id}
     */
    public function show(Request $request, Video $video)
    {
        $viewer = $request->user('sanctum');

        abort_unless($video->status === 'approved' || $viewer?->id === $video->user_id, 404);

        $this->videoService->loadLikeFlag($video, $viewer);

        return response()->json([
            'data'    => new VideoResource($video->load('user', 'rejectionReason', 'category')),
            'message' => 'Success',
        ]);
    }

    /**
     * Загрузка ролика (multipart, ≤60 сек, квота тарифа). Статус — pending.
     * POST /api/v1/videos
     *
     * @authenticated
     */
    public function store(StoreVideoRequest $request)
    {
        $video = $this->videoService->createFromApi(
            $request->user(),
            $request->validated(),
            $request->durationSeconds(),
        );

        return response()->json([
            'data'    => new VideoResource($video),
            'message' => __('messages.video_uploaded'),
        ], 201);
    }

    /**
     * Лайк-переключатель: один лайк на пользователя на ролик (ТЗ §7.3).
     * POST /api/v1/videos/{id}/like
     *
     * @authenticated
     */
    public function like(Request $request, Video $video)
    {
        abort_unless($video->status === 'approved', 404);

        $result = $this->videoService->toggleLike($video, $request->user());

        return response()->json([
            'data'    => $result,
            'message' => __($result['is_liked'] ? 'messages.video_liked' : 'messages.video_unliked'),
        ]);
    }

    /**
     * Просмотр из ленты: атомарный инкремент счётчика (кроме автора).
     * POST /api/v1/videos/{id}/view
     */
    public function view(Request $request, Video $video)
    {
        abort_unless($video->status === 'approved', 404);

        $this->videoService->registerView($video, $request->user('sanctum'));

        return response()->json([
            'data'    => null,
            'message' => 'Success',
        ]);
    }

    /**
     * Удаление своего ролика вместе с файлами — освобождает квоту тарифа.
     * DELETE /api/v1/videos/{id}
     *
     * @authenticated
     */
    public function destroy(Video $video)
    {
        $this->videoService->delete($video);

        return response()->json([
            'data'    => null,
            'message' => __('messages.video_deleted'),
        ]);
    }

    private function paginated(LengthAwarePaginator $videos)
    {
        return response()->json([
            'data' => VideoResource::collection($videos->items()),
            'meta' => [
                'current_page' => $videos->currentPage(),
                'last_page'    => $videos->lastPage(),
                'per_page'     => $videos->perPage(),
                'total'        => $videos->total(),
            ],
            'links' => [
                'first' => $videos->url(1),
                'last'  => $videos->url($videos->lastPage()),
                'prev'  => $videos->previousPageUrl(),
                'next'  => $videos->nextPageUrl(),
            ],
        ]);
    }
}
