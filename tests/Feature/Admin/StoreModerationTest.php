<?php

use App\Events\StoreApproved;
use App\Events\StoreRejected;
use App\Models\RejectionReason;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function actingAsModerationRole(string $role): User
{
    $user = User::factory()->create(['name' => 'Test '.$role, 'role' => $role]);
    test()->actingAs($user);

    return $user;
}

beforeEach(function () {
    $this->store = Store::create([
        'user_id' => User::factory()->create()->id,
        'name'    => 'Altyn Bazar',
        'status'  => 'pending',
    ]);

    $this->reason = RejectionReason::create([
        'name_ru' => 'Некорректное название', 'name_tk' => 'Ady nädogry',
        'type' => 'store', 'is_active' => true,
    ]);
});

it('approves a store and clears the previous rejection reason', function () {
    Event::fake([StoreApproved::class]);
    actingAsModerationRole('admin');

    $this->store->update(['status' => 'rejected', 'rejection_reason_id' => $this->reason->id]);

    $this->patch(route('stores.approve', $this->store))->assertRedirect();

    $store = $this->store->fresh();
    expect($store->status)->toBe('approved')
        ->and($store->rejection_reason_id)->toBeNull();

    Event::assertDispatched(StoreApproved::class);
});

it('rejects a store with a reason from the dictionary', function () {
    Event::fake([StoreRejected::class]);
    actingAsModerationRole('admin');

    $this->patch(route('stores.reject', $this->store), ['rejection_reason_id' => $this->reason->id])
        ->assertRedirect();

    $store = $this->store->fresh();
    expect($store->status)->toBe('rejected')
        ->and($store->rejection_reason_id)->toBe($this->reason->id);

    Event::assertDispatched(StoreRejected::class);
});

it('refuses a rejection reason of another type', function () {
    actingAsModerationRole('admin');

    $listingReason = RejectionReason::create([
        'name_ru' => 'Причина объявления', 'name_tk' => 'Bildiriş sebäbi',
        'type' => 'listing', 'is_active' => true,
    ]);

    $this->patch(route('stores.reject', $this->store), ['rejection_reason_id' => $listingReason->id])
        ->assertSessionHasErrors('rejection_reason_id');
});

/** Магазин не входит в список сущностей, модерируемых менеджером (CLAUDE.md → «Роли»). */
it('closes store moderation to a manager', function () {
    actingAsModerationRole('manager');

    $this->patch(route('stores.approve', $this->store))->assertForbidden();
    $this->patch(route('stores.reject', $this->store), ['rejection_reason_id' => $this->reason->id])
        ->assertForbidden();
});
