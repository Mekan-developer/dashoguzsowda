<?php

use App\Models\Category;
use App\Models\CategoryIcon;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// User::factory() assumes an `email_verified_at` column that this project's
// users table does not have — build the row directly with real columns instead.
function actingAdmin(): User
{
    $admin = User::factory()->admin()->create(['name' => 'Test Admin']);
    test()->actingAs($admin);

    return $admin;
}

beforeEach(function () {
    Storage::fake('public');
});

it('creates a root category and computes level automatically', function () {
    actingAdmin();

    $this->post(route('categories.store'), ['name_ru' => 'Электроника'])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', ['name_ru' => 'Электроника', 'level' => 1, 'parent_id' => null]);
});

it('rejects creating a category under a level-3 parent', function () {
    actingAdmin();

    $l1 = Category::create(['name_ru' => 'L1', 'slug' => 'l1', 'level' => 1, 'order' => 1]);
    $l2 = Category::create(['name_ru' => 'L2', 'slug' => 'l2', 'level' => 2, 'order' => 1, 'parent_id' => $l1->id]);
    $l3 = Category::create(['name_ru' => 'L3', 'slug' => 'l3', 'level' => 3, 'order' => 1, 'parent_id' => $l2->id]);

    $this->post(route('categories.store'), ['name_ru' => 'Слишком глубоко', 'parent_id' => $l3->id])
        ->assertSessionHasErrors('parent_id');

    $this->assertDatabaseMissing('categories', ['name_ru' => 'Слишком глубоко']);
});

it('cascades delete to the whole subtree', function () {
    actingAdmin();

    $root = Category::create(['name_ru' => 'Корень', 'slug' => 'root', 'level' => 1, 'order' => 1]);
    $child = Category::create(['name_ru' => 'Реб', 'slug' => 'child', 'level' => 2, 'order' => 1, 'parent_id' => $root->id]);
    $grand = Category::create(['name_ru' => 'Внук', 'slug' => 'grand', 'level' => 3, 'order' => 1, 'parent_id' => $child->id]);

    $this->delete(route('categories.destroy', $root->id))->assertRedirect();

    $this->assertDatabaseMissing('categories', ['id' => $root->id]);
    $this->assertDatabaseMissing('categories', ['id' => $child->id]);
    $this->assertDatabaseMissing('categories', ['id' => $grand->id]);
});

it('uploads a custom svg icon and adds it to the shared icon library', function () {
    actingAdmin();

    $svg = UploadedFile::fake()->createWithContent('custom.svg', '<svg xmlns="http://www.w3.org/2000/svg"><circle r="5"/></svg>');

    $this->post(route('categories.store'), ['name_ru' => 'С иконкой', 'icon' => $svg])
        ->assertRedirect();

    $category = Category::where('name_ru', 'С иконкой')->firstOrFail();
    expect($category->icon_path)->not->toBeNull();
    expect(CategoryIcon::where('path', $category->icon_path)->exists())->toBeTrue();
    Storage::disk('public')->assertExists($category->icon_path);
});

it('accepts picking an existing icon from the library', function () {
    actingAdmin();

    Storage::disk('public')->put('categories/icons/home.svg', '<svg></svg>');
    $icon = CategoryIcon::create(['slug' => 'home', 'path' => 'categories/icons/home.svg', 'is_system' => true]);

    $this->post(route('categories.store'), ['name_ru' => 'Из библиотеки', 'icon_path' => $icon->path])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', ['name_ru' => 'Из библиотеки', 'icon_path' => $icon->path]);
});

it('rejects an icon_path that is not a known library entry', function () {
    actingAdmin();

    $this->post(route('categories.store'), ['name_ru' => 'Плохой путь', 'icon_path' => 'categories/icons/does-not-exist.svg'])
        ->assertSessionHasErrors('icon_path');
});

it('uploads an image for a root category', function () {
    actingAdmin();

    $image = UploadedFile::fake()->image('cover.jpg', 900, 900);

    $this->post(route('categories.store'), ['name_ru' => 'С картинкой', 'image' => $image])
        ->assertRedirect();

    $category = Category::where('name_ru', 'С картинкой')->firstOrFail();
    expect($category->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($category->image_path);
});

it('rejects an image on a non-root category', function () {
    actingAdmin();

    $root = Category::create(['name_ru' => 'Родитель', 'slug' => 'parent-img', 'level' => 1, 'order' => 1]);
    $image = UploadedFile::fake()->image('cover.jpg', 900, 900);

    $this->post(route('categories.store'), ['name_ru' => 'Дочерняя', 'parent_id' => $root->id, 'image' => $image])
        ->assertSessionHasErrors('image');

    $this->assertDatabaseMissing('categories', ['name_ru' => 'Дочерняя']);
});

it('clears the image when a root category is reparented under another category', function () {
    actingAdmin();

    Storage::disk('public')->put('categories/images/existing.webp', 'fake');
    $root = Category::create(['name_ru' => 'Корень с картинкой', 'slug' => 'root-img', 'level' => 1, 'order' => 1, 'image_path' => 'categories/images/existing.webp']);
    $newParent = Category::create(['name_ru' => 'Новый родитель', 'slug' => 'new-parent', 'level' => 1, 'order' => 2]);

    $this->put(route('categories.update', $root->id), ['name_ru' => $root->name_ru, 'parent_id' => $newParent->id])
        ->assertRedirect();

    expect($root->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing('categories/images/existing.webp');
});

it('swaps sort order between siblings on move', function () {
    actingAdmin();

    $first = Category::create(['name_ru' => 'Первый', 'slug' => 'first', 'level' => 1, 'order' => 1]);
    $second = Category::create(['name_ru' => 'Второй', 'slug' => 'second', 'level' => 1, 'order' => 2]);

    $this->patch(route('categories.move', $second->id), ['direction' => 'up'])->assertRedirect();

    expect($first->fresh()->order)->toBe(2);
    expect($second->fresh()->order)->toBe(1);
});

it('hides all descendants of an inactive category from the mobile API even if they are individually active', function () {
    actingAsClient();

    $root = Category::create(['name_ru' => 'Родитель', 'slug' => 'parent-hidden', 'level' => 1, 'order' => 1, 'is_active' => false]);
    $child = Category::create(['name_ru' => 'Ребёнок', 'slug' => 'child-active', 'level' => 2, 'order' => 1, 'parent_id' => $root->id, 'is_active' => true]);

    $response = $this->getJson('/api/v1/categories');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->not->toContain($root->id);

    // child's own is_active stays untouched in the DB (no cascading mutation)
    expect($child->fresh()->is_active)->toBeTrue();
});
