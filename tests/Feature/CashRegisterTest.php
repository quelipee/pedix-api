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

    public function test_pix_withdrawals_are_separate_from_cash_and_preserved_in_closing_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('cash-registers.open'), ['opening_balance' => '100.00'])->assertCreated();
        $register = CashRegister::query()->firstOrFail();
        foreach ([$this->orderReadyForFinalization('dinheiro', '50.00'), $this->orderReadyForFinalization('pix', '100.00')] as $order) {
            $this->patchJson(route('orders.advance', $order), ['status' => OrderStatus::Finalized->value])->assertOk();
        }
        foreach ([['dinheiro', '20.55'], ['pix', '30.25']] as [$method, $amount]) {
            $this->postJson(route('cash-registers.movements.store', $register), [
                'type' => 'sangria', 'payment_method' => $method, 'amount' => $amount, 'description' => 'Pagamento de fornecedor',
            ])->assertCreated();
            $this->assertDatabaseHas('cash_movements', ['cash_register_id' => $register->id, 'payment_method' => $method, 'amount' => $amount]);
        }
        $this->get(route('cash-registers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cashRegister.expectedCash', '129.45')->where('cashRegister.netPix', '69.75')
            ->where('cashRegister.totalSales', '150.00')->where('cashRegister.withdrawals', '50.80')
            ->where('cashRegister.cashWithdrawals', '20.55')->where('cashRegister.pixWithdrawals', '30.25')
            ->where('cashRegister.movements.0.paymentMethod', 'pix')->where('cashRegister.movements.0.paymentMethodLabel', 'Pix'));

        $this->patchJson(route('cash-registers.close', $register), ['closing_balance' => '129.45'])
            ->assertOk()->assertJsonPath('data.expected_cash', '129.45')->assertJsonPath('data.difference', '0.00');
        $this->get(route('cash-registers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('recentRegisters.0.netPix', '69.75')->where('recentRegisters.0.pixWithdrawals', '30.25')
            ->where('recentRegisters.0.cashWithdrawals', '20.55')->where('recentRegisters.0.movements.0.paymentMethod', 'pix'));
        $this->postJson(route('cash-registers.movements.store', $register), [
            'type' => 'sangria', 'payment_method' => 'pix', 'amount' => '10.00', 'description' => 'Caixa fechado',
        ])->assertUnprocessable()->assertJsonValidationErrors('cash_register');
        $this->assertDatabaseCount('cash_movements', 2);
    }

    public function test_movement_method_validation_and_team_isolation_apply_to_pix(): void
    {
        $owner = User::factory()->create();
        $register = CashRegister::factory()->create(['team_id' => $owner->current_team_id, 'opened_by' => $owner->id]);
        $url = route('cash-registers.movements.store', $register);
        foreach (['credito', 'debito', 'invalid', '', null] as $method) {
            $this->actingAs($owner)->postJson($url, [
                'type' => 'sangria', 'payment_method' => $method, 'amount' => '10.00', 'description' => 'Inválido',
            ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        }
        $this->postJson($url, [
            'type' => 'suprimento', 'payment_method' => 'pix', 'amount' => '10.00', 'description' => 'Inválido',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->actingAs(User::factory()->create())->postJson($url, [
            'type' => 'sangria', 'payment_method' => 'pix', 'amount' => '10.00', 'description' => 'Outra equipe',
        ])->assertUnprocessable()->assertJsonValidationErrors('cash_register');
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_negative_net_pix_does_not_reduce_the_physical_cash_balance(): void
    {
        $owner = User::factory()->create();
        $register = CashRegister::factory()->create(['team_id' => $owner->current_team_id, 'opened_by' => $owner->id]);
        $this->actingAs($owner)->postJson(route('cash-registers.movements.store', $register), [
            'type' => 'sangria', 'payment_method' => 'pix', 'amount' => '50.50', 'description' => 'Retirada da conta',
        ])->assertCreated();
        $this->get(route('cash-registers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('cashRegister.expectedCash', '100.00')->where('cashRegister.netPix', '-50.50')
            ->where('cashRegister.cashWithdrawals', '0.00')->where('cashRegister.pixWithdrawals', '50.50'));
    }

    public function test_cash_accepts_decimal_boundaries_and_preserves_negative_closing(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson(route('cash-registers.open'), ['opening_balance' => '99999999.99'])->assertCreated();
        $register = CashRegister::query()->firstOrFail();
        $this->patchJson(route('cash-registers.close', $register), ['closing_balance' => '99999999.99'])
            ->assertOk()->assertJsonPath('data.difference', '0.00');

        $this->postJson(route('cash-registers.open'), ['opening_balance' => '0.00'])->assertCreated();
        $register = CashRegister::query()->whereNull('closed_at')->firstOrFail();
        $this->patchJson(route('cash-registers.close', $register), ['closing_balance' => '-99999999.99'])
            ->assertOk()->assertJsonPath('data.closing_balance', '-99999999.99');
    }

    public function test_closing_rejects_calculated_values_outside_decimal_bounds(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson(route('cash-registers.open'), ['opening_balance' => '99999999.99'])->assertCreated();
        $register = CashRegister::query()->firstOrFail();
        $this->patchJson(route('cash-registers.close', $register), ['closing_balance' => '-99999999.99'])
            ->assertUnprocessable()->assertJsonValidationErrors('closing_balance');
        $this->postJson(route('cash-registers.movements.store', $register), [
            'type' => 'suprimento', 'amount' => '0.01', 'description' => 'Limite do saldo calculado',
        ])->assertCreated();
        $this->patchJson(route('cash-registers.close', $register), ['closing_balance' => '0.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('closing_balance');
        $this->assertNull($register->fresh()->closed_at);
        $this->assertNull($register->fresh()->closing_balance);
    }

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
