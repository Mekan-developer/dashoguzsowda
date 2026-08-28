<?php

use App\Events\TariffRequestApproved;
use App\Events\TariffRequestRejected;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\TariffRequest;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function actingAsTariffRequestRole(string $role): User
{
    $user = User::factory()->create(['name' => 'Test '.$role, 'role' => $role]);
    test()->actingAs($user);

    return $user;
}

beforeEach(function () {
    $this->premium = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true,
    ]);

    $this->applicant = User::factory()->create();

    $this->request = TariffRequest::create([
        'user_id'   => $this->applicant->id,
        'tariff_id' => $this->premium->id,
        'amount'    => 250,
        'status'    => 'pending',
    ]);
});

it('grants the tariff only after the admin confirms the payment', function () {
    Event::fake([TariffRequestApproved::class]);
    $admin = actingAsTariffRequestRole('admin');

    expect($this->applicant->fresh()->tariff_id)->toBeNull();

    $this->patch(route('tariff-requests.approve', $this->request))->assertRedirect();

    $applicant = $this->applicant->fresh();
    expect($applicant->tariff_id)->toBe($this->premium->id)
        ->and($applicant->tariff_ends_at->isFuture())->toBeTrue();

    $this->assertDatabaseHas('tariff_requests', [
        'id'           => $this->request->id,
        'status'       => 'approved',
        'processed_by' => $admin->id,
    ]);

    Event::assertDispatched(TariffRequestApproved::class);
});

it('lights up the store when the approved tariff allows one', function () {
    actingAsTariffRequestRole('admin');

    // Витрина погашена, пока тариф не оплачен
    $store = Store::create([
        'user_id' => $this->applicant->id, 'name' => 'Altyn Bazar',
        'status' => 'approved', 'is_active' => false,
    ]);

    $this->patch(route('tariff-requests.approve', $this->request))->assertRedirect();

    expect($store->fresh()->is_active)->toBeTrue();
});

it('rejects a request with a comment and leaves the tariff untouched', function () {
    Event::fake([TariffRequestRejected::class]);
    actingAsTariffRequestRole('admin');

    $this->patch(route('tariff-requests.reject', $this->request), ['comment' => 'Деньги не поступили'])
        ->assertRedirect();

    expect($this->applicant->fresh()->tariff_id)->toBeNull();
    $this->assertDatabaseHas('tariff_requests', [
        'id'      => $this->request->id,
        'status'  => 'rejected',
        'comment' => 'Деньги не поступили',
    ]);

    Event::assertDispatched(TariffRequestRejected::class);
});

it('requires a comment on rejection', function () {
    actingAsTariffRequestRole('admin');

    $this->patch(route('tariff-requests.reject', $this->request), [])
        ->assertSessionHasErrors('comment');
});

it('refuses to process the same request twice', function () {
    actingAsTariffRequestRole('admin');

    $this->patch(route('tariff-requests.approve', $this->request))->assertRedirect();
    $this->patch(route('tariff-requests.approve', $this->request))->assertSessionHasErrors('status');
});

/** Заявки — это деньги, поэтому менеджеру закрыты целиком (CLAUDE.md → «Роли»). */
it('closes tariff requests to a manager', function () {
    actingAsTariffRequestRole('manager');

    $this->get(route('tariff-requests.index'))->assertForbidden();
    $this->patch(route('tariff-requests.approve', $this->request))->assertForbidden();
    $this->patch(route('tariff-requests.reject', $this->request), ['comment' => 'нет'])->assertForbidden();
});
