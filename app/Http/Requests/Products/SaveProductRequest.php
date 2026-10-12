<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'ingredients' => ['nullable', 'array', 'max:30'],
            'ingredients.*' => ['required', 'string', 'max:80', 'distinct'],
            'price' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'active' => ['required', 'boolean'],
            'option_groups' => ['sometimes', 'array', 'max:10'],
            'option_groups.*.name' => ['required', 'string', 'max:120'],
            'option_groups.*.min_selections' => ['required', 'integer', 'min:0', 'max:20'],
            'option_groups.*.max_selections' => ['required', 'integer', 'min:1', 'max:20', 'gte:option_groups.*.min_selections'],
            'option_groups.*.options' => ['required', 'array', 'min:1', 'max:30'],
            'option_groups.*.options.*.name' => ['required', 'string', 'max:120'],
            'option_groups.*.options.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],
            'option_groups.*.options.*.active' => ['required', 'boolean'],
        ];
    }
}
