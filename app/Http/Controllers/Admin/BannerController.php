<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Models\Banner;
use App\Services\BannerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BannerController extends Controller
{
    public function __construct(
        private readonly BannerService $bannerService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Banners/Index', [
            'banners' => $this->bannerService->list($request->only('search', 'active')),
            'filters' => $request->only('search', 'active'),
        ]);
    }

    public function store(StoreBannerRequest $request)
    {
        $data = $this->clearIrrelevantLinkFields($request->safe()->except('image', 'crop_x', 'crop_y'));

        $this->bannerService->store($data, $request->file('image'), $request->safe()->only('crop_x', 'crop_y'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(StoreBannerRequest $request, Banner $banner)
    {
        $data = $this->clearIrrelevantLinkFields($request->safe()->except('image', 'crop_x', 'crop_y'));

        $this->bannerService->update($banner, $data, $request->file('image'), $request->safe()->only('crop_x', 'crop_y'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Banner $banner)
    {
        $this->bannerService->delete($banner);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }

    public function toggle(Banner $banner)
    {
        $this->bannerService->toggleActive($banner);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function move(Request $request, Banner $banner)
    {
        $request->validate(['direction' => 'required|in:up,down']);
        $this->bannerService->move($banner, $request->input('direction'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    private function clearIrrelevantLinkFields(array $data): array
    {
        if (($data['link_type'] ?? null) !== 'url') {
            $data['link_url'] = null;
        }
        if (($data['link_type'] ?? null) !== 'listing') {
            $data['listing_id'] = null;
        }

        return $data;
    }
}
