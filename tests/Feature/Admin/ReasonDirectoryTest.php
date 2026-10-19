<?php

use App\Models\ComplaintReason;
use App\Models\RejectionReason;
use App\Models\User;

/** Справочники причин отказа и жалоб — оба списка живут на странице настроек */
beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('creates, edits and deletes a rejection reason', function () {
    $this->post(route('rejection-reasons.store'), [
        'name_ru' => 'Плохое фото', 'name_tk' => 'Erbet surat', 'type' => 'listing', 'is_active' => true,
    ])->assertRedirect();
    $reason = RejectionReason::where('name_ru', 'Плохое фото')->sole();
    expect($reason->type)->toBe('listing');

    // Частичная правка: выключить, не трогая названия
    $this->put(route('rejection-reasons.update', $reason), ['is_active' => false])->assertRedirect();
    $reason->refresh();
    expect((bool) $reason->is_active)->toBeFalse()
        ->and($reason->name_ru)->toBe('Плохое фото');

    $this->delete(route('rejection-reasons.destroy', $reason))->assertRedirect();
    expect(RejectionReason::find($reason->id))->toBeNull();
});

it('accepts only known moderation types for a rejection reason', function () {
    $before = RejectionReason::count();

    $this->post(route('rejection-reasons.store'), ['name_ru' => 'Спам', 'name_tk' => 'Spam', 'type' => 'news'])
        ->assertSessionHasErrors('type');
    $this->post(route('rejection-reasons.store'), ['type' => 'listing'])
        ->assertSessionHasErrors(['name_ru', 'name_tk']);

    expect(RejectionReason::count())->toBe($before);
});

it('creates, edits and deletes a complaint reason', function () {
    $this->post(route('complaint-reasons.store'), [
        'name_ru' => 'Мошенничество', 'name_tk' => 'Galplyk', 'is_active' => true,
    ])->assertRedirect();
    $reason = ComplaintReason::where('name_ru', 'Мошенничество')->sole();

    $this->put(route('complaint-reasons.update', $reason), ['name_ru' => 'Обман', 'name_tk' => 'Aldaw'])
        ->assertRedirect();
    expect($reason->fresh()->name_ru)->toBe('Обман');

    $this->delete(route('complaint-reasons.destroy', $reason))->assertRedirect();
    expect(ComplaintReason::find($reason->id))->toBeNull();

    $this->post(route('complaint-reasons.store'), [])->assertSessionHasErrors(['name_ru', 'name_tk']);
});

it('closes reason directories to a manager', function () {
    $this->actingAs(User::factory()->manager()->create());

    $this->post(route('rejection-reasons.store'), ['name_ru' => 'Спам', 'name_tk' => 'Spam', 'type' => 'listing'])
        ->assertForbidden();
    $this->post(route('complaint-reasons.store'), ['name_ru' => 'Спам', 'name_tk' => 'Spam'])
        ->assertForbidden();
});
