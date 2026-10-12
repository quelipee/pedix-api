<?php

namespace App\Actions\Orders;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    /**
     * @param  array<string, string|null>|null  $deliveryAddress
     * @param  array<int, array{product_id: int, quantity: int, notes?: string|null, option_ids?: array<int, int>}>  $items
     */
    public function handle(
        string $modality,
        ?string $customerName,
        ?string $customerPhone,
        string $paymentMethod,
        bool $needsChange,
        ?string $changeFor,
        ?array $deliveryAddress,
        ?string $deliveryFee,
        array $items,
    ): Order {
        return DB::transaction(function () use (
            $modality,
            $customerName,
            $customerPhone,
            $paymentMethod,
            $needsChange,
            $changeFor,
            $deliveryAddress,
            $deliveryFee,
            $items,
        ): Order {
            $products = Product::query()
                ->with('optionGroups.options')
                ->whereIn('id', collect($items)->pluck('product_id'))
                ->where('active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($products->count() !== count($items)) {
                throw ValidationException::withMessages(['items' => 'Um ou mais produtos não estão mais disponíveis.']);
            }

            $selectedOptionsByItem = collect($items)->map(
                fn (array $item, int $index) => $this->selectedOptions($products->get($item['product_id']), $item['option_ids'] ?? [], $index),
            );

            if ($needsChange && $paymentMethod === PaymentMethod::Cash->value) {
                $totalInCents = collect($items)->sum(function (array $item, int $index) use ($products, $selectedOptionsByItem): int {
                    $unitPriceInCents = (int) str_replace(
                        '.',
                        '',
                        (string) $products->get($item['product_id'])->price,
                    );

                    $optionsInCents = collect($selectedOptionsByItem->get($index))->sum(
                        fn (array $option): int => $this->amountInCents($option['price']),
                    );

                    return ($unitPriceInCents + $optionsInCents) * $item['quantity'];
                }) + ($modality === OrderModality::Delivery->value ? $this->amountInCents($deliveryFee) : 0);
                $changeForInCents = $this->amountInCents($changeFor);

                if ($changeForInCents < $totalInCents) {
                    throw ValidationException::withMessages([
                        'change_for' => 'O valor informado precisa ser igual ou maior que o total do pedido.',
                    ]);
                }
            }

            $order = Order::query()->create([
                'modality' => OrderModality::from($modality),
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'delivery_address' => $modality === OrderModality::Delivery->value ? $deliveryAddress : null,
                'delivery_fee' => $modality === OrderModality::Delivery->value ? $deliveryFee : null,
                'payment_method' => PaymentMethod::from($paymentMethod),
                'change_for' => $needsChange && $paymentMethod === PaymentMethod::Cash->value
                    ? $changeFor
                    : null,
                'status' => OrderStatus::New,
            ]);
            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);
                $selectedOptions = $selectedOptionsByItem->get($index);
                $unitPriceInCents = $this->amountInCents((string) $product->price)
                    + collect($selectedOptions)->sum(fn (array $option): int => $this->amountInCents($option['price']));
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $this->amountFromCents($unitPriceInCents),
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                    'selected_options' => $selectedOptions,
                ]);
            }

            return $order;
        });
    }

    private function amountInCents(?string $amount): int
    {
        [$wholeReais, $fractionalReais] = array_pad(explode('.', (string) $amount, 2), 2, '');

        return (int) $wholeReais * 100 + (int) str_pad($fractionalReais, 2, '0');
    }

    private function amountFromCents(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, int>  $optionIds
     * @return array<int, array{id: int, group: string, name: string, price: string}>
     */
    private function selectedOptions(Product $product, array $optionIds, int $itemIndex): array
    {
        $availableOptions = $product->optionGroups
            ->flatMap(fn ($group) => $group->options->where('active', true))
            ->keyBy('id');

        if (collect($optionIds)->contains(fn (int $optionId): bool => ! $availableOptions->has($optionId))) {
            throw ValidationException::withMessages([
                "items.{$itemIndex}.option_ids" => 'Uma ou mais opções não estão disponíveis para este produto.',
            ]);
        }

        foreach ($product->optionGroups as $group) {
            $selectedCount = collect($optionIds)->filter(
                fn (int $optionId): bool => $group->options->contains('id', $optionId),
            )->count();

            if ($selectedCount < $group->min_selections || $selectedCount > $group->max_selections) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.option_ids" => "Selecione entre {$group->min_selections} e {$group->max_selections} opção(ões) em {$group->name}.",
                ]);
            }
        }

        return collect($optionIds)->map(function (int $optionId) use ($availableOptions, $product): array {
            /** @var ProductOption $option */
            $option = $availableOptions->get($optionId);
            $group = $product->optionGroups->first(
                fn ($group): bool => $group->options->contains('id', $optionId),
            );

            return [
                'id' => $option->id,
                'group' => $group->name,
                'name' => $option->name,
                'price' => $option->price,
            ];
        })->values()->all();
    }
}
