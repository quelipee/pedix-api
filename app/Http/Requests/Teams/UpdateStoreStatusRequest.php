<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamPermission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->currentTeam !== null
            && $user->hasTeamPermission($user->currentTeam, TeamPermission::ManageStoreStatus);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['is_open' => ['required', 'boolean']];
    }
}
