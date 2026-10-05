<?php

use App\Events\ListingSubmitted;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([ListingSubmitted::class]);

    $region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city   = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $root   = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'transport-rt', 'level' => 1]);
    $leaf   = Category::create([
        'parent_id' => $root->id, 'name_ru' => 'Велосипеды', 'name_tk' => 'Tigirler',
        'slug' => 'bikes-rt', 'level' => 2,
    ]);
    $owner = User::factory()->create();

    $this->attrs = [
        'user_id' => $owner->id, 'category_id' => $leaf->id,
        'region_id' => $region->id, 'city_id' => $city->id,
        'title' => 'Велосипед', 'type' => 'goods', 'phone' => $owner->phone,
    ];
});

it('broadcasts a new pending listing to the admin channel', function () {
    $listing = Listing::create($this->attrs);

    Event::assertDispatched(ListingSubmitted::class, function (ListingSubmitted $e) use ($listing) {
        return $e->listing->is($listing)
            && $e->broadcastOn() == [new PrivateChannel('admin')]
            && $e->broadcastWith() === ['id' => $listing->id, 'title' => 'Велосипед'];
    });
});

it('does not broadcast a listing created already approved', function () {
    Listing::create([...$this->attrs, 'status' => 'approved']);

    Event::assertNotDispatched(ListingSubmitted::class);
});
