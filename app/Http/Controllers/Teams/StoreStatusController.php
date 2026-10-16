<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\UpdateStoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateStoreStatusRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StoreStatusController extends Controller
{
    public function __invoke(UpdateStoreStatusRequest $request, Team $current_team, UpdateStoreStatus $updateStoreStatus): RedirectResponse
    {
        $isOpen = $request->boolean('is_open');
        $updateStoreStatus->handle($current_team, $isOpen);

        Inertia::flash('toast', ['type' => 'success', 'message' => $isOpen ? 'Loja aberta.' : 'Loja fechada.']);

        return back();
    }
}
