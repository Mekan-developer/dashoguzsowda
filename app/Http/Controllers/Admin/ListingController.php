<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveListingAction;
use App\Actions\BoostListingAction;
use App\Actions\RejectListingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectListingRequest;
use App\Http\Requests\Admin\StoreListingRequest;
use App\Http\Requests\Admin\UpdateListingRequest;
use App\Models\Listing;
use App\Models\User;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use App\Repositories\Interfaces\RegionRepositoryInterface;
use App\Services\ListingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService $listingService,
        private readonly ApproveListingAction $approveAction,
        private readonly RejectListingAction $rejectAction,
        private readonly BoostListingAction $boostAction,
        private readonly ReasonRepositoryInterface $reasons,
        private readonly CategoryRepositoryInterface $categories,
        private readonly RegionRepositoryInterface $regions,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $this->notificationService->markSectionSeen($request->user(), 'listings');

        return Inertia::render('Listings/Index', [
            'listings'         => $this->listingService->list($request->only('status', 'category_id', 'search')),
            'categories'       => $this->categories->roots(),
            'rejectionReasons' => $this->reasons->activeRejectionReasons('listing'),
            'filters'          => $request->only('status', 'category_id', 'search'),
            'counts'           => $this->listingService->counts(),
            // Справочники панели создания — грузятся только при её открытии
            'createForm'       => Inertia::optional(fn () => [
                'categories' => $this->categories->activeTree(),
                'regions'    => $this->regions->activeListWithDistricts(),
            ]),
        ]);
    }

    // Создание — панель поверх списка; старый адрес /create открывает её
    public function create()
    {
        return redirect()->route('listings.index', ['create' => 1]);
    }

    /**
     * Создание объявления от имени выбранного пользователя (сразу approved).
     */
    public function store(StoreListingRequest $request)
    {
        $owner = User::query()->findOrFail($request->validated('user_id'));

        $data = $request->safe()->except('user_id');
        $data['photos'] = $request->file('photos', []);

        $this->listingService->createFromAdmin($owner, $data);

        // Как у пользователей: панель закрывается, новое объявление — первым в списке
        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function show(Listing $listing)
    {
        return Inertia::render('Listings/Show', [
            'listing'          => $listing->load('user.store', 'category.parent.parent', 'region', 'city', 'district', 'media', 'rejectionReason'),
            'categories'       => $this->categories->activeTree(),
            'regions'          => $this->regions->activeListWithDistricts(),
            'rejectionReasons' => $this->reasons->activeRejectionReasons('listing'),
        ]);
    }

    public function update(UpdateListingRequest $request, Listing $listing)
    {
        $data = $request->safe()->except('photos');
        if ($request->hasFile('photos')) {
            $data['photos'] = $request->file('photos', []);
        }

        $this->listingService->updateFromAdmin($listing, $data);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Listing $listing)
    {
        $this->listingService->delete($listing);

        return redirect()->route('listings.index')
            ->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }

    public function approve(Listing $listing)
    {
        $this->approveAction->execute($listing);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.listing_approved')]);
    }

    public function reject(RejectListingRequest $request, Listing $listing)
    {
        $this->rejectAction->execute($listing, $request->validated('rejection_reason_id'));

        return back()->with('toast', ['type' => 'error', 'message' => __('messages.listing_rejected')]);
    }

    public function boost(Listing $listing)
    {
        // Отказ («интервал не прошёл») не привязан ни к одному полю формы —
        // без тоста кнопка просто молчала бы.
        try {
            $this->boostAction->execute($listing, ignoreTariffLimit: true);
        } catch (ValidationException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.listing_boosted')]);
    }
}
