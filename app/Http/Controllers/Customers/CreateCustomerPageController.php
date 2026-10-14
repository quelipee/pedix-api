<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CreateCustomerPageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('customers/Create');
    }
}
