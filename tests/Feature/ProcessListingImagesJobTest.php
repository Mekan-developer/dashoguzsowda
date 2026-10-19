<?php

use App\Jobs\ProcessListingImagesJob;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $region = Region::create(['name_ru' => 'Дашогуз', 'name_tk' => 'Daşoguz']);
    $city   = City::create(['region_id' => $region->id, 'name_ru' => 'Дашогуз', 'name_tk' => 'Daşoguz']);
    $root   = Category::create(['name_ru' => 'Дом', 'name_tk' => 'Öý', 'slug' => 'home-img', 'level' => 1]);
    $leaf   = Category::create(['parent_id' => $root->id, 'name_ru' => 'Мебель', 'name_tk' => 'Mebel', 'slug' => 'furniture-img', 'level' => 2]);
    $owner  = User::factory()->create();

    $this->listing = Listing::create([
        'user_id' => $owner->id, 'category_id' => $leaf->id,
        'region_id' => $region->id, 'city_id' => $city->id,
        'title' => 'Шкаф', 'description' => 'Описание', 'type' => 'goods',
        'phone' => $owner->phone, 'status' => 'pending',
    ]);
});

/** Загрузка так, как её кладёт AttachListingPhotosAction: оригинал в uploads/ */
function uploadListingPhoto(Listing $listing, int $width, int $height): ListingMedia
{
    $path = UploadedFile::fake()->image('photo.jpg', $width, $height)
        ->store("listings/{$listing->id}/photos/uploads", 'public');

    return ListingMedia::create(['listing_id' => $listing->id, 'path' => $path, 'type' => 'image', 'order' => 1]);
}

function imageSize(string $path): array
{
    return array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2);
}

it('runs on the media queue', function () {
    expect((new ProcessListingImagesJob(1, 'x.jpg', 1))->queue)->toBe('media');
});

it('converts the upload into three webp sizes and drops the original', function () {
    $media    = uploadListingPhoto($this->listing, 2000, 1500);
    $uploaded = $media->path;

    (new ProcessListingImagesJob($media->id, $uploaded, $this->listing->id))->handle();

    $media->refresh();
    $base = "listings/{$this->listing->id}/photos";
    $name = pathinfo($uploaded, PATHINFO_FILENAME);

    expect($media->path)->toBe("{$base}/original/{$name}.webp")
        ->and($media->medium_path)->toBe("{$base}/medium/{$name}.webp")
        ->and($media->thumb_path)->toBe("{$base}/thumb/{$name}.webp");

    // Оригинал ужат до 1080 по длинной стороне с сохранением пропорций,
    // medium и thumb — квадратный кроп
    expect(imageSize($media->path))->toBe([1080, 810])
        ->and(imageSize($media->medium_path))->toBe([480, 480])
        ->and(imageSize($media->thumb_path))->toBe([120, 120]);

    foreach ([$media->path, $media->medium_path, $media->thumb_path] as $path) {
        expect(Storage::disk('public')->mimeType($path))->toBe('image/webp');
    }

    Storage::disk('public')->assertMissing($uploaded);
});

it('does not upscale a small photo', function () {
    $media = uploadListingPhoto($this->listing, 600, 400);

    (new ProcessListingImagesJob($media->id, $media->path, $this->listing->id))->handle();

    expect(imageSize($media->fresh()->path))->toBe([600, 400]);
});
