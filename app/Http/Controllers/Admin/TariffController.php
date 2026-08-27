<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTariffRequest;
use App\Models\Tariff;
use App\Services\TariffService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TariffController extends Controller
{
    public function __construct(
        private readonly TariffService $tariffService,
    ) {}

    public function index()
    {
        return Inertia::render('Tariffs/Index', [
            'tariffs' => $this->tariffService->all(),
        ]);
    }

    public function store(StoreTariffRequest $request)
    {
        $this->tariffService->store($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(StoreTariffRequest $request, Tariff $tariff)
    {
        $this->tariffService->update($tariff, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Tariff $tariff)
    {
        $this->tariffService->delete($tariff);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }

    public function toggle(Tariff $tariff)
    {
        $this->tariffService->update($tariff, ['is_active' => ! $tariff->is_active]);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }
}
