<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveListingAction;
use App\Actions\BoostListingAction;
use App\Actions\RejectListingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectListingRequest;
use App\Http\Requests\Admin\UpdateListingRequest;
use App\Models\Listing;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use App\Services\ListingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
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
        ]);
    }

    public function show(Listing $listing)
    {
        return Inertia::render('Listings/Show', [
            'listing'          => $listing->load('user', 'category.parent.parent', 'region', 'city', 'media', 'rejectionReason'),
            'categories'       => $this->categories->activeTree(),
            'rejectionReasons' => $this->reasons->activeRejectionReasons('listing'),
        ]);
    }

    /** Частичная правка текста, цены и категории модератором. */
    public function update(UpdateListingRequest $request, Listing $listing)
    {
        $this->listingService->updateFromAdmin($listing, $request->validated());

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
        $this->boostAction->execute($listing);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.listing_boosted')]);
    }
}
