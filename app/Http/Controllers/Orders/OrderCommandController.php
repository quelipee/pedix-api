<?php

namespace App\Http\Controllers\Orders;

use App\Contracts\OrderCommandFormatter;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderCommandController extends Controller
{
    public function __construct(private readonly OrderCommandFormatter $formatter)
    {
        //
    }

    public function __invoke(Request $request, string $current_team, Order $order): Response
    {
        abort_if($order->team_id !== $request->user()->current_team_id, 404);

        return Inertia::render('orders/Command', [
            'command' => $this->formatter->format($order),
        ]);
    }
}
