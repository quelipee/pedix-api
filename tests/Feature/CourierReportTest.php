<?php

namespace Tests\Feature;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\TeamRole;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourierReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
    }

    public function test_report_summarizes_only_finalized_deliveries_and_keeps_inactive_couriers(): void
    {
        $owner = User::factory()->create();
        $courier = Courier::factory()->create(['team_id' => $owner->current_team_id, 'name' => 'Wesley', 'active' => false]);
        foreach (['4.50', '7.50'] as $index => $fee) {
            $order = Order::factory()->create([
                'team_id' => $owner->current_team_id, 'courier_id' => $courier->id,
                'modality' => OrderModality::Delivery, 'status' => OrderStatus::Finalized,
                'delivery_fee' => $fee, 'finalized_at' => now(),
                'dispatched_at' => now()->subMinutes(($index + 1) * 10),
            ]);
            $order->items()->create(['product_name' => 'X-Salada', 'unit_price' => '20.00', 'quantity' => 1]);
        }
        foreach ([OrderStatus::New, OrderStatus::InPreparation, OrderStatus::Ready, OrderStatus::Cancelled] as $status) {
            Order::factory()->create([
                'team_id' => $owner->current_team_id, 'courier_id' => $courier->id,
                'modality' => OrderModality::Delivery, 'status' => $status,
                'delivery_fee' => '100.00', 'finalized_at' => now(),
            ]);
        }
        Order::factory()->create([
            'team_id' => $owner->current_team_id, 'courier_id' => $courier->id,
            'modality' => OrderModality::Takeaway, 'status' => OrderStatus::Finalized, 'finalized_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('reports.index', ['current_team' => $owner->currentTeam->slug, 'period' => 'today']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.couriers.deliveries', 2)->where('report.couriers.deliveryFees', '12.00')
            ->where('report.couriers.unassignedDeliveries', 0)->has('report.couriers.rows', 1)
            ->where('report.couriers.rows.0.name', 'Wesley')->where('report.couriers.rows.0.active', false)
            ->where('report.couriers.rows.0.averageFee', '6.00')->where('report.couriers.rows.0.ordersTotal', '52.00')
            ->where('report.couriers.rows.0.averageTicket', '26.00')->where('report.couriers.rows.0.averageDeliveryMinutes', 15)
            ->where('report.couriers.rows.0.timedDeliveries', 2)->has('report.couriers.rows.0.recentDeliveries', 2));
    }

    public function test_period_team_isolation_and_missing_or_invalid_times(): void
    {
        $owner = User::factory()->create();
        $courier = Courier::factory()->create(['team_id' => $owner->current_team_id]);
        $foreignCourier = Courier::factory()->create();
        foreach ([null, now()->addHour()] as $dispatch) {
            Order::factory()->create([
                'team_id' => $owner->current_team_id, 'courier_id' => $courier->id,
                'modality' => OrderModality::Delivery, 'status' => OrderStatus::Finalized,
                'delivery_fee' => null, 'dispatched_at' => $dispatch, 'finalized_at' => now(),
            ]);
        }
        foreach ([null, $foreignCourier->id] as $courierId) {
            Order::factory()->create([
                'team_id' => $owner->current_team_id, 'courier_id' => $courierId,
                'modality' => OrderModality::Delivery, 'status' => OrderStatus::Finalized, 'finalized_at' => now(),
            ]);
        }
        Order::factory()->create([
            'team_id' => $owner->current_team_id, 'courier_id' => $courier->id,
            'modality' => OrderModality::Delivery, 'status' => OrderStatus::Finalized, 'finalized_at' => now()->subDays(2),
        ]);
        Order::factory()->create([
            'team_id' => $foreignCourier->team_id, 'courier_id' => $foreignCourier->id,
            'modality' => OrderModality::Delivery, 'status' => OrderStatus::Finalized, 'finalized_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('reports.index', ['current_team' => $owner->currentTeam->slug,
            'period' => 'custom', 'datetime_from' => now()->startOfHour()->format('Y-m-d\TH:i'),
            'datetime_to' => now()->endOfHour()->format('Y-m-d\TH:i')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.couriers.deliveries', 2)->where('report.couriers.unassignedDeliveries', 2)
            ->where('report.couriers.deliveryFees', '0.00')->has('report.couriers.rows', 1)
            ->where('report.couriers.rows.0.id', $courier->id)
            ->where('report.couriers.rows.0.averageDeliveryMinutes', null)
            ->where('report.couriers.rows.0.timedDeliveries', 0));
    }

    public function test_recent_deliveries_are_limited_sorted_and_use_store_numbers(): void
    {
        $owner = User::factory()->create();
        $courier = Courier::factory()->create(['team_id' => $owner->current_team_id]);
        for ($index = 0; $index < 7; $index++) {
            Order::factory()->create([
                'team_id' => $owner->current_team_id, 'courier_id' => $courier->id,
                'modality' => OrderModality::Delivery, 'status' => OrderStatus::Finalized,
                'finalized_at' => now()->subMinutes(7 - $index),
            ]);
        }
        $this->actingAs($owner)->get(route('reports.index', ['current_team' => $owner->currentTeam->slug]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.couriers.rows.0.deliveries', 7)->has('report.couriers.rows.0.recentDeliveries', 5)
            ->where('report.couriers.rows.0.recentDeliveries.0.number', 7)
            ->where('report.couriers.rows.0.recentDeliveries.4.number', 3));
    }

    public function test_unauthorized_members_cannot_access_courier_report(): void
    {
        $member = User::factory()->create();
        $team = $member->currentTeam;
        $team->members()->updateExistingPivot($member->id, ['role' => TeamRole::Member->value]);
        $this->actingAs($member)->get(route('reports.index', $team->slug))->assertForbidden();
    }
}
