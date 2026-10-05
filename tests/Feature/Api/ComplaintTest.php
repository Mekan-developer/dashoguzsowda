<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city     = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'transport', 'level' => 1]);

    $this->user  = User::factory()->create();
    $this->owner = User::factory()->create();

    $this->listing = Listing::create([
        'user_id'     => $this->owner->id,
        'category_id' => $this->category->id,
        'region_id'   => $this->region->id,
        'city_id'     => $this->city->id,
        'title'       => 'Продам велосипед',
        'type'        => 'goods',
        'phone'       => $this->owner->phone,
        'status'      => 'approved',
    ]);

    $this->reason = ComplaintReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'is_active' => true]);
});

it('lists only active complaint reasons', function () {
    actingAsClient();

    ComplaintReason::create(['name_ru' => 'Скрытая', 'name_tk' => 'Gizlin', 'is_active' => false]);

    $this->getJson('/api/v1/complaint-reasons')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name_ru', 'Спам');
});

it('requires auth to file a complaint', function () {
    $this->postJson('/api/v1/complaints', [
        'listing_id'          => $this->listing->id,
        'complaint_reason_id' => $this->reason->id,
    ])->assertUnauthorized();
});

it('creates a complaint with new status', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/complaints', [
        'listing_id'          => $this->listing->id,
        'complaint_reason_id' => $this->reason->id,
        'text'                => 'Это объявление — спам',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('message', __('messages.complaint_submitted'));

    $complaint = Complaint::sole();
    expect($complaint->user_id)->toBe($this->user->id)
        ->and($complaint->listing_id)->toBe($this->listing->id)
        ->and($complaint->complaint_reason_id)->toBe($this->reason->id);
});

it('rejects an inactive complaint reason', function () {
    Sanctum::actingAs($this->user);

    $inactive = ComplaintReason::create(['name_ru' => 'Старая', 'name_tk' => 'Köne', 'is_active' => false]);

    $this->postJson('/api/v1/complaints', [
        'listing_id'          => $this->listing->id,
        'complaint_reason_id' => $inactive->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('complaint_reason_id');
});

it('forbids blocked user from filing a complaint', function () {
    Sanctum::actingAs(User::factory()->blocked()->create());

    $this->postJson('/api/v1/complaints', [
        'listing_id'          => $this->listing->id,
        'complaint_reason_id' => $this->reason->id,
    ])->assertForbidden();
});

it('renders admin complaints page with paginator, counts and reason filter', function () {
    $admin = User::factory()->admin()->create();
    $otherReason = ComplaintReason::create(['name_ru' => 'Дубликат', 'name_tk' => 'Dublikat', 'is_active' => true]);

    Complaint::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'complaint_reason_id' => $this->reason->id, 'status' => 'new']);
    Complaint::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'complaint_reason_id' => $otherReason->id, 'status' => 'resolved']);

    $this->actingAs($admin)->get(route('complaints.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Complaints/Index')
            ->has('complaints.data', 2)
            ->has('complaints.links')
            ->where('counts.pending', 1)
            ->where('counts.resolved', 1)
            ->has('reasons', 2));

    // Фильтр по причине уходит на сервер
    $this->actingAs($admin)->get(route('complaints.index', ['reason_id' => $otherReason->id]))
        ->assertInertia(fn ($page) => $page->has('complaints.data', 1));
});

it('lets admin resolve a complaint recording resolver and note', function () {
    $admin = User::factory()->admin()->create();

    $complaint = Complaint::create([
        'user_id'             => $this->user->id,
        'listing_id'          => $this->listing->id,
        'complaint_reason_id' => $this->reason->id,
        'status'              => 'new',
    ]);

    $this->actingAs($admin)
        ->patch(route('complaints.resolve', $complaint), ['resolution_note' => 'Объявление снято'])
        ->assertRedirect();

    $complaint->refresh();
    expect($complaint->status)->toBe('resolved')
        ->and($complaint->resolved_by)->toBe($admin->id)
        ->and($complaint->resolution_note)->toBe('Объявление снято');
});
