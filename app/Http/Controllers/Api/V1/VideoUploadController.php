<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteVideoUploadRequest;
use App\Http\Requests\Api\V1\InitVideoUploadRequest;
use App\Http\Requests\Api\V1\UploadVideoChunkRequest;
use App\Http\Resources\Api\V1\VideoResource;
use App\Services\VideoService;

/**
 * Chunked / streaming-загрузка ролика: init → chunk(*) → complete.
 * Позволяет загрузить видео любого размера — каждая часть маленькая,
 * лимиты PHP на один запрос не мешают, файл собирается на сервере потоком.
 */
class VideoUploadController extends Controller
{
    public function __construct(
        private readonly VideoService $videoService,
    ) {}

    /**
     * Открыть сессию загрузки. Квота тарифа проверяется сразу (fail-fast).
     * POST /api/v1/videos/upload/init
     *
     * @authenticated
     */
    public function init(InitVideoUploadRequest $request)
    {
        $session = $this->videoService->initChunkedUpload($request->user(), $request->validated());

        return response()->json([
            'data'    => $session,
            'message' => 'Success',
        ], 201);
    }

    /**
     * Дописать очередную часть. Тело — сырой бинарный поток (octet-stream).
     * POST /api/v1/videos/upload/{uploadId}/chunk?index=N
     *
     * @authenticated
     */
    public function chunk(UploadVideoChunkRequest $request, string $uploadId)
    {
        // Поток тела запроса — не грузим часть целиком в память
        $input = $request->getContent(asResource: true);
        $index = $request->query('index') !== null ? (int) $request->query('index') : null;

        $result = $this->videoService->appendChunk($request->user(), $uploadId, $input, $index);

        return response()->json([
            'data'    => $result,
            'message' => 'Success',
        ]);
    }

    /**
     * Финализировать: собрать файл, проверить (≤60 сек) и создать ролик (pending).
     * POST /api/v1/videos/upload/{uploadId}/complete
     *
     * @authenticated
     */
    public function complete(CompleteVideoUploadRequest $request, string $uploadId)
    {
        $video = $this->videoService->completeChunkedUpload($request->user(), $uploadId);

        return response()->json([
            'data'    => new VideoResource($video),
            'message' => __('messages.video_uploaded'),
        ], 201);
    }

    /**
     * Отменить незавершённую загрузку.
     * DELETE /api/v1/videos/upload/{uploadId}
     *
     * @authenticated
     */
    public function destroy(CompleteVideoUploadRequest $request, string $uploadId)
    {
        $this->videoService->abortChunkedUpload($request->user(), $uploadId);

        return response()->json([
            'data'    => null,
            'message' => 'Success',
        ]);
    }
}
