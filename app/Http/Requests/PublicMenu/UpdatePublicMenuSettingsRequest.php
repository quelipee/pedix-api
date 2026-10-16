<?php

namespace App\Http\Requests\PublicMenu;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublicMenuSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->current_team_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['online_delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2']];
    }
}
