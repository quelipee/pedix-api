<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'categoryId' => $this->category_id,
            'category' => $this->category,
            'description' => $this->description,
            'ingredients' => $this->ingredients ?? [],
            'price' => $this->price,
            'active' => $this->active,
            'optionGroups' => $this->optionGroups->map(fn ($group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'minSelections' => $group->min_selections,
                'maxSelections' => $group->max_selections,
                'options' => $group->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price' => $option->price,
                    'active' => $option->active,
                ])->values(),
            ])->values(),
        ];
    }
}
