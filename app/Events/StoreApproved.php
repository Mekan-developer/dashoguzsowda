<?php

namespace App\Events;

use App\Models\Store;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoreApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Store $store) {}
}
