<?php

namespace App\Contracts;

use App\Data\OrderCommandData;
use App\Models\Order;

interface OrderCommandFormatter
{
    public function format(Order $order): OrderCommandData;
}
