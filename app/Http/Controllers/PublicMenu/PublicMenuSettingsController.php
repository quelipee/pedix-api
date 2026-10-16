<?php

namespace App\Http\Controllers\PublicMenu;

use App\Actions\Teams\ConfigurePublicMenu;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMenu\UpdatePublicMenuSettingsRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PublicMenuSettingsController extends Controller
{
    public function __invoke(UpdatePublicMenuSettingsRequest $request, Team $current_team, ConfigurePublicMenu $action): RedirectResponse
    {
        $data = $request->validated();
        $action->handle($current_team, isset($data['online_delivery_fee']) ? (string) $data['online_delivery_fee'] : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configuração do cardápio atualizada.']);

        return back();
    }
}
