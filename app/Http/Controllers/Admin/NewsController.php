<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNewsRequest;
use App\Models\News;
use App\Services\NewsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NewsController extends Controller
{
    public function __construct(
        private readonly NewsService $newsService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('News/Index', [
            'news'    => $this->newsService->list($request->only('search', 'type', 'published')),
            'filters' => $request->only('search', 'type', 'published'),
            'counts'  => $this->newsService->counts(),
        ]);
    }

    public function store(StoreNewsRequest $request)
    {
        $data = $request->safe()->except('image', 'crop_x', 'crop_y', 'remove_image');
        // Очищаем рекламные поля если тип regular
        if ($data['type'] === 'regular') {
            $data['ad_link_type'] = null;
            $data['ad_link_id'] = null;
        }
        $this->newsService->store(
            $data,
            $request->user(),
            $request->file('image'),
            $request->safe()->only('crop_x', 'crop_y'),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(StoreNewsRequest $request, News $news)
    {
        $data = $request->safe()->except('image', 'crop_x', 'crop_y', 'remove_image');
        // Очищаем рекламные поля если тип regular
        if ($data['type'] === 'regular') {
            $data['ad_link_type'] = null;
            $data['ad_link_id'] = null;
        }
        $this->newsService->update(
            $news,
            $data,
            $request->file('image'),
            $request->safe()->only('crop_x', 'crop_y'),
            $request->boolean('remove_image'),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(News $news)
    {
        $this->newsService->delete($news);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }

    public function publish(News $news)
    {
        $this->newsService->publish($news);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.published')]);
    }

    public function unpublish(News $news)
    {
        $this->newsService->unpublish($news);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.unpublished')]);
    }
}
