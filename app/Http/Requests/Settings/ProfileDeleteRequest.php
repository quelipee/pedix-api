<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /** @return array<int, Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hasBilling = $this->user()->ownedTeams()->get()->contains(
                fn (Team $team): bool => $team->hasOngoingSubscription() || $team->hasPendingCheckout(),
            );

            if ($hasBilling) {
                $validator->errors()->add('password', 'Encerre as assinaturas e os checkouts das suas equipes antes de excluir a conta.');
            }
        }];
    }
}
