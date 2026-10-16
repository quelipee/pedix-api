<?php

namespace App\Http\Requests\Billing;

use App\Enums\BillingPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->current_team_id !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['plan' => ['required', Rule::enum(BillingPlan::class)]];
    }
}
