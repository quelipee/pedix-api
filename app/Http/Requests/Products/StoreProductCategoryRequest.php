<?php

namespace App\Http\Requests\Products;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->current_team_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:80',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = DB::table('product_categories')
                        ->where('team_id', $this->user()->current_team_id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                        ->exists();

                    if ($exists) {
                        $fail('Esta categoria já está cadastrada.');
                    }
                },
            ],
        ];
    }
}
