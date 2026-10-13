<?php

namespace App\Http\Requests\Couriers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->current_team_id !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('couriers')->where('team_id', $this->user()->current_team_id),
            ],
        ];
    }
}
