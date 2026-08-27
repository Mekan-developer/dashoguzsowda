<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AssignTariffAction;
use App\Actions\BlockUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignTariffRequest;
use App\Http\Requests\Admin\BlockUserRequest;
use App\Http\Requests\Admin\CheckUserPhoneRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Repositories\Interfaces\TariffRepositoryInterface;
use App\Services\RegionService;
use App\Services\UserService;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly RegionService $regionService,
        private readonly BlockUserAction $blockAction,
        private readonly AssignTariffAction $assignTariffAction,
        private readonly TariffRepositoryInterface $tariffs,
    ) {}

    public function index(\Illuminate\Http\Request $request)
    {
        return Inertia::render('Users/Index', [
            'users'   => $this->userService->list($request->only('search', 'status', 'region_id')),
            'regions' => $this->regionService->activeListWithDistricts(),
            'tariffs' => $this->tariffs->active(),
            'filters' => $request->only('search', 'status', 'region_id'),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->userService->store($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function checkPhone(CheckUserPhoneRequest $request)
    {
        return response()->json($this->userService->phoneStatus($request->validated('phone')));
    }

    public function show(User $user)
    {
        $overview = $this->userService->profileOverview($user);

        return Inertia::render('Users/Show', [
            'user'         => $user->load('region', 'city', 'district', 'tariff'),
            'userListings' => $overview['listings'],
            'stats'        => $overview['stats'],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userService->update($user, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(User $user)
    {
        $this->userService->delete($user);

        return redirect()->route('users.index')
            ->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }

    public function block(BlockUserRequest $request, User $user)
    {
        $this->blockAction->execute($user, $request->validated('reason'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.user_blocked')]);
    }

    public function unblock(User $user)
    {
        $this->userService->unblock($user);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.user_unblocked')]);
    }

    public function assignTariff(AssignTariffRequest $request, User $user)
    {
        $tariff = $this->tariffs->find($request->validated('tariff_id'));
        $this->assignTariffAction->execute($user, $tariff);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.tariff_assigned')]);
    }
}
