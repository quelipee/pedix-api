<?php

namespace App\Observers;

use App\Actions\Orders\AllocateOrderNumber;
use App\Models\Order;

class OrderObserver
{
    public function __construct(private readonly AllocateOrderNumber $allocateOrderNumber) {}

    public function creating(Order $order): void
    {
        $order->forceFill($this->allocateOrderNumber->handle($order->team_id));
    }
}
