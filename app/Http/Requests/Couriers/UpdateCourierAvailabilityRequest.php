<?php

namespace App\Http\Requests\Couriers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourierAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['active' => ['required', 'boolean']];
    }
}
