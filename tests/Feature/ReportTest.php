<?php

namespace Tests\Feature;

use App\Contracts\SalesReport;
use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\EloquentSalesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_contract_uses_the_eloquent_implementation(): void
    {
        $this->assertInstanceOf(EloquentSalesReport::class, $this->app->make(SalesReport::class));
    }

    public function test_guests_cannot_open_reports(): void
    {
        $this->get(route('reports.index', ['current_team' => 'lanchonete']))
            ->assertRedirect(route('login'));
    }

    public function test_report_summarizes_finalized_sales_for_the_selected_period(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create([
            'team_id' => $user->current_team_id,
            'created_at' => now(),
        ]);

        $todayOrder = Order::factory()->create([
            'status' => OrderStatus::Finalized,
            'modality' => OrderModality::Delivery,
            'payment_method' => 'dinheiro',
            'delivery_fee' => '5.00',
            'finalized_at' => now(),
        ]);
        $todayOrder->items()->create([
            'product_name' => 'X-Bacon',
            'unit_price' => '20.00',
            'quantity' => 2,
        ]);

        $olderOrder = Order::factory()->create([
            'status' => OrderStatus::Finalized,
            'modality' => OrderModality::Takeaway,
            'payment_method' => 'pix',
            'finalized_at' => now()->subDays(3),
        ]);
        $olderOrder->items()->create([
            'product_name' => 'Batata frita',
            'unit_price' => '10.00',
            'quantity' => 1,
        ]);

        $outsidePeriod = Order::factory()->create([
            'status' => OrderStatus::Finalized,
            'payment_method' => 'credito',
            'finalized_at' => now()->subDays(10),
        ]);
        $outsidePeriod->items()->create([
            'product_name' => 'Produto antigo',
            'unit_price' => '50.00',
            'quantity' => 1,
        ]);
        Order::factory()->create(['status' => OrderStatus::Ready]);

        $this->actingAs($user)
            ->get(route('reports.index', ['period' => '7d']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Index')
                ->where('filters.period', '7d')
                ->where('report.summary.revenue', '55.00')
                ->where('report.summary.orders', 2)
                ->where('report.summary.averageTicket', '27.50')
                ->where('report.summary.deliveryFees', '5.00')
                ->where('report.summary.newCustomers', 1)
                ->has('report.dailySales', 7)
                ->where('report.dailySales.3.revenue', '10.00')
                ->where('report.dailySales.6.revenue', '45.00')
                ->where('report.payments.0.total', '45.00')
                ->where('report.payments.1.total', '10.00')
                ->where('report.modalities.1.orders', 1)
                ->where('report.modalities.2.orders', 1)
                ->where('report.topProducts.0.name', 'X-Bacon')
                ->where('report.topProducts.0.quantity', 2));
    }

    public function test_report_can_be_filtered_to_today_or_a_custom_period(): void
    {
        $user = User::factory()->create();
        $insidePeriod = now()->subDays(2)->setTime(14, 30);
        $order = Order::factory()->create([
            'status' => OrderStatus::Finalized,
            'payment_method' => 'pix',
            'finalized_at' => $insidePeriod,
        ]);
        $order->items()->create([
            'product_name' => 'Suco',
            'unit_price' => '9.00',
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('reports.index', ['period' => 'today']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 'today')
                ->where('report.summary.orders', 0));

        $outsideOrder = Order::factory()->create([
            'status' => OrderStatus::Finalized,
            'payment_method' => 'dinheiro',
            'finalized_at' => $insidePeriod->copy()->setTime(9, 0),
        ]);
        $outsideOrder->items()->create([
            'product_name' => 'Café',
            'unit_price' => '5.00',
            'quantity' => 1,
        ]);

        $this->get(route('reports.index', [
            'period' => 'custom',
            'datetime_from' => $insidePeriod->copy()->setTime(14, 0)->format('Y-m-d\TH:i'),
            'datetime_to' => $insidePeriod->copy()->setTime(15, 0)->format('Y-m-d\TH:i'),
        ]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 'custom')
                ->where('filters.dateTimeFrom', $insidePeriod->copy()->setTime(14, 0)->format('Y-m-d\TH:i'))
                ->where('filters.dateTimeTo', $insidePeriod->copy()->setTime(15, 0)->format('Y-m-d\TH:i'))
                ->where('report.summary.orders', 1)
                ->where('report.summary.revenue', '9.00'));
    }
}
