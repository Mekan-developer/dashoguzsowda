<?php

namespace App\Events;

use App\Models\TariffRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TariffRequestRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly TariffRequest $tariffRequest) {}
}
