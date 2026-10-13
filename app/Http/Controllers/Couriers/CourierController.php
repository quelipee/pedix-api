<?php

namespace App\Http\Controllers\Couriers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Couriers\StoreCourierRequest;
use App\Http\Requests\Couriers\UpdateCourierAvailabilityRequest;
use App\Models\Courier;
use Illuminate\Http\JsonResponse;

class CourierController extends Controller
{
    public function store(StoreCourierRequest $request): JsonResponse
    {
        $courier = Courier::query()->create([
            ...$request->validated(),
            'team_id' => $request->user()->current_team_id,
        ]);

        return response()->json(['data' => $courier], 201);
    }

    public function updateAvailability(UpdateCourierAvailabilityRequest $request, Courier $courier): JsonResponse
    {
        abort_unless($courier->team_id === $request->user()->current_team_id, 404);

        $courier->update(['active' => $request->boolean('active')]);

        return response()->json(['data' => $courier->refresh()]);
    }
}
