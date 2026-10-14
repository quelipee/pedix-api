<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): JsonResponse
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $customer = $createCustomer->handle($teamId, $request->validated());

        return CustomerResource::make($customer)->response()->setStatusCode(201);
    }
}
