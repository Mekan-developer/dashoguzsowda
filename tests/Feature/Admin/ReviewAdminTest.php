<?php

use App\Models\RejectionReason;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    $this->review = Review::create([
        'user_id'        => User::factory()->create()->id,
        'target_user_id' => User::factory()->create()->id,
        'text'           => 'Продавец не отвечает',
        'rating'         => 2,
        'status'         => 'pending',
    ]);
    $this->reason = RejectionReason::create([
        'name_ru' => 'Оскорбления', 'name_tk' => 'Kemsitmeler', 'type' => 'review', 'is_active' => true,
    ]);
});

it('lets a manager reject a review with a reason and approve it later', function () {
    $this->actingAs(User::factory()->manager()->create());

    $this->patch(route('reviews.reject', $this->review), ['rejection_reason_id' => $this->reason->id])
        ->assertRedirect();

    $this->review->refresh();
    expect($this->review->status)->toBe('rejected')
        ->and($this->review->rejection_reason_id)->toBe($this->reason->id);

    // Одобренный отзыв не тащит за собой прошлую причину отказа
    $this->patch(route('reviews.approve', $this->review))->assertRedirect();

    $this->review->refresh();
    expect($this->review->status)->toBe('approved')
        ->and($this->review->rejection_reason_id)->toBeNull();
});

it('requires an existing reason to reject a review', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('reviews.reject', $this->review), [])->assertSessionHasErrors('rejection_reason_id');
    $this->patch(route('reviews.reject', $this->review), ['rejection_reason_id' => 999])
        ->assertSessionHasErrors('rejection_reason_id');

    expect($this->review->fresh()->status)->toBe('pending');
});

it('forbids a regular user from moderating reviews', function () {
    $this->actingAs(User::factory()->create());

    $this->patch(route('reviews.approve', $this->review))->assertForbidden();

    expect($this->review->fresh()->status)->toBe('pending');
});
