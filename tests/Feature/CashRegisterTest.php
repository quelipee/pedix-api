<?php

namespace Tests\Feature;

use App\Contracts\CashRegisterCalculator;
use App\Enums\OrderStatus;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\User;
use App\Services\EloquentCashRegisterCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_calculator_contract_is_resolved_by_dependency_injection(): void
    {
        $this->assertInstanceOf(
            EloquentCashRegisterCalculator::class,
            $this->app->make(CashRegisterCalculator::class),
        );
    }

    public function test_a_cash_register_can_be_opened_only_once_for_the_current_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('cash-registers.open'), [
            'opening_balance' => '100.00',
        ])->assertCreated()->assertJsonPath('data.id', 1);

        $this->assertDatabaseHas('cash_registers', [
            'team_id' => $user->current_team_id,
            'opened_by' => $user->id,
            'opening_balance' => '100.00',
            'closed_at' => null,
        ]);

        $this->postJson(route('cash-registers.open'), [
            'opening_balance' => '50.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('cash_register');
    }

    public function test_finalized_sales_movements_and_closing_are_calculated_in_the_open_register(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('cash-registers.open'), ['opening_balance' => '100.00'])
            ->assertCreated();
        $cashRegister = CashRegister::query()->firstOrFail();

        $cashOrder = $this->orderReadyForFinalization('dinheiro', '20.00');
        $pixOrder = $this->orderReadyForFinalization('pix', '15.00');
        $debitOrder = $this->orderReadyForFinalization('debito', '25.00');
        $creditOrder = $this->orderReadyForFinalization('credito', '25.00');

        foreach ([$cashOrder, $pixOrder, $debitOrder, $creditOrder] as $order) {
            $this->patchJson(route('orders.advance', $order), [
                'status' => OrderStatus::Finalized->value,
            ])->assertOk();
        }

        $this->postJson(route('cash-registers.movements.store', $cashRegister), [
            'type' => 'suprimento',
            'amount' => '50.00',
            'description' => 'Troco adicional',
        ])->assertCreated()->assertJsonStructure(['data' => ['id']]);
        $this->postJson(route('cash-registers.movements.store', $cashRegister), [
            'type' => 'sangria',
            'amount' => '10.00',
            'description' => 'Pagamento de fornecedor',
        ])->assertCreated()->assertJsonStructure(['data' => ['id']]);

        $this->get(route('cash-registers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cash-registers/Index')
                ->where('cashRegister.id', $cashRegister->id)
                ->where('cashRegister.totalSales', '85.00')
                ->where('cashRegister.expectedCash', '160.00')
                ->where('cashRegister.supplies', '50.00')
                ->where('cashRegister.withdrawals', '10.00')
                ->has('cashRegister.movements', 2),
            );

        $this->patchJson(route('cash-registers.close', $cashRegister), [
            'closing_balance' => '155.00',
        ])->assertOk()
            ->assertJsonPath('data.id', $cashRegister->id)
            ->assertJsonPath('data.expected_cash', '160.00')
            ->assertJsonPath('data.closing_balance', '155.00')
            ->assertJsonPath('data.difference', '-5.00');

        $this->assertDatabaseHas('cash_registers', [
            'id' => $cashRegister->id,
            'closed_by' => $user->id,
            'expected_cash' => '160.00',
            'closing_balance' => '155.00',
            'difference' => '-5.00',
        ]);

        $this->get(route('cash-registers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recentRegisters.0.id', $cashRegister->id)
                ->where('recentRegisters.0.totalSales', '85.00')
                ->where('recentRegisters.0.sales.0.total', '20.00')
                ->where('recentRegisters.0.sales.1.total', '15.00')
                ->where('recentRegisters.0.sales.2.total', '25.00')
                ->where('recentRegisters.0.sales.3.total', '25.00')
                ->where('recentRegisters.0.supplies', '50.00')
                ->where('recentRegisters.0.withdrawals', '10.00')
                ->has('recentRegisters.0.movements', 2),
            );
    }

    public function test_cash_registers_and_movements_cannot_be_changed_by_another_team(): void
    {
        $owner = User::factory()->create();
        $cashRegister = CashRegister::factory()->create([
            'team_id' => $owner->current_team_id,
            'opened_by' => $owner->id,
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->postJson(route('cash-registers.movements.store', $cashRegister), [
            'type' => 'suprimento',
            'amount' => '10.00',
            'description' => 'Inválido',
        ])->assertUnprocessable()->assertJsonValidationErrors('cash_register');

        $this->patchJson(route('cash-registers.close', $cashRegister), [
            'closing_balance' => '100.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('cash_register');
    }

    public function test_a_cash_register_can_be_closed_with_a_negative_balance(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('cash-registers.open'), [
            'opening_balance' => '0.00',
        ])->assertCreated();

        $cashRegister = CashRegister::query()->firstOrFail();

        $this->postJson(route('cash-registers.movements.store', $cashRegister), [
            'type' => 'sangria',
            'amount' => '50.50',
            'description' => 'Retirada acima do saldo disponível',
        ])->assertCreated();

        $this->patchJson(route('cash-registers.close', $cashRegister), [
            'closing_balance' => '-50.50',
        ])->assertOk()
            ->assertJsonPath('data.expected_cash', '-50.50')
            ->assertJsonPath('data.closing_balance', '-50.50')
            ->assertJsonPath('data.difference', '0.00');

        $this->assertDatabaseHas('cash_registers', [
            'id' => $cashRegister->id,
            'expected_cash' => '-50.50',
            'closing_balance' => '-50.50',
            'difference' => '0.00',
        ]);
    }

    private function orderReadyForFinalization(string $paymentMethod, string $price): Order
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Ready,
            'payment_method' => $paymentMethod,
        ]);
        $order->items()->create([
            'product_name' => 'Item teste',
            'unit_price' => $price,
            'quantity' => 1,
        ]);

        return $order;
    }
}
