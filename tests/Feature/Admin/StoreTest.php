<?php

use App\Models\Category;
use App\Models\Store;
use App\Models\StorePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function actingAsStoreRole(string $role): User
{
    $user = User::factory()->create(['name' => 'Test ' . $role, 'role' => $role]);
    test()->actingAs($user);

    return $user;
}

function makeAdminStore(array $overrides = []): Store
{
    $owner = User::factory()->create();

    return Store::create(array_merge([
        'user_id' => $owner->id,
        'name'    => 'Altyn Bazar',
    ], $overrides));
}

beforeEach(function () {
    Storage::fake('public');
});

it('lets admin and manager view the store list', function () {
    makeAdminStore();

    actingAsStoreRole('admin');
    $this->get(route('stores.index'))->assertOk();

    actingAsStoreRole('manager');
    $this->get(route('stores.index'))->assertOk();
});

it('forbids manager from managing stores', function () {
    $store = makeAdminStore();
    actingAsStoreRole('manager');

    $this->put(route('stores.update', $store), ['name' => 'Renamed'])->assertForbidden();
    $this->patch(route('stores.toggle', $store))->assertForbidden();
    $this->patch(route('stores.move', $store), ['direction' => 'up'])->assertForbidden();
    $this->delete(route('stores.destroy', $store))->assertForbidden();
});

it('lets admin update store fields and category', function () {
    $category = Category::create(['name_ru' => 'Электроника', 'name_tk' => 'Elektronika', 'slug' => 'electronics', 'level' => 1]);
    $store = makeAdminStore();
    actingAsStoreRole('admin');

    $this->put(route('stores.update', $store), [
        'name' => 'Yenilenen', 'description' => 'Täze beýan', 'category_id' => $category->id,
    ])->assertRedirect();

    $store->refresh();
    expect($store->name)->toBe('Yenilenen')
        ->and($store->category_id)->toBe($category->id);
});

it('lets admin upload a logo converted to webp', function () {
    $store = makeAdminStore();
    actingAsStoreRole('admin');

    $this->put(route('stores.update', $store), [
        'name' => $store->name,
        'logo' => UploadedFile::fake()->image('logo.jpg', 500, 500),
    ])->assertRedirect();

    $store->refresh();
    expect($store->logo)->toEndWith('.webp');
    Storage::disk('public')->assertExists($store->logo);
});

it('lets admin toggle popularity, assigning and clearing sort_order', function () {
    $store = makeAdminStore();
    actingAsStoreRole('admin');

    $this->patch(route('stores.toggle', $store))->assertRedirect();
    $store->refresh();
    expect($store->is_popular)->toBeTrue()
        ->and($store->sort_order)->not->toBeNull();

    $this->patch(route('stores.toggle', $store))->assertRedirect();
    $store->refresh();
    expect($store->is_popular)->toBeFalse()
        ->and($store->sort_order)->toBeNull();
});

it('lets admin swap sort_order between two popular stores on move', function () {
    $first  = makeAdminStore(['name' => 'A', 'is_popular' => true, 'sort_order' => 1]);
    $second = makeAdminStore(['name' => 'B', 'is_popular' => true, 'sort_order' => 2]);
    actingAsStoreRole('admin');

    $this->patch(route('stores.move', $second), ['direction' => 'up'])->assertRedirect();

    expect($first->fresh()->sort_order)->toBe(2)
        ->and($second->fresh()->sort_order)->toBe(1);
});

it('lets admin delete a store and its files', function () {
    $store = makeAdminStore();
    Storage::disk('public')->put("stores/{$store->id}/logo.webp", 'x');
    $store->update(['logo' => "stores/{$store->id}/logo.webp"]);
    actingAsStoreRole('admin');

    $this->delete(route('stores.destroy', $store))->assertRedirect();

    expect(Store::find($store->id))->toBeNull();
    Storage::disk('public')->assertMissing($store->logo);
});

it('lets admin remove a single gallery photo without touching the rest', function () {
    $store = makeAdminStore();
    Storage::disk('public')->put('stores/x/1.webp', 'x');
    Storage::disk('public')->put('stores/x/2.webp', 'x');
    $keep = StorePhoto::create(['store_id' => $store->id, 'path' => 'stores/x/1.webp', 'order' => 0]);
    $remove = StorePhoto::create(['store_id' => $store->id, 'path' => 'stores/x/2.webp', 'order' => 1]);
    actingAsStoreRole('admin');

    $this->delete(route('stores.photos.destroy', [$store, $remove]))->assertRedirect();

    expect(StorePhoto::find($remove->id))->toBeNull();
    expect(StorePhoto::find($keep->id))->not->toBeNull();
    Storage::disk('public')->assertMissing('stores/x/2.webp');
    Storage::disk('public')->assertExists('stores/x/1.webp');
});
