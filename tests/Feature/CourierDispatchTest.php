<?php

namespace Tests\Feature;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_courier_can_be_registered_for_the_current_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('couriers.store'), [
            'name' => 'João Silva',
            'phone' => '(11) 99999-0000',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'João Silva');

        $this->assertDatabaseHas('couriers', [
            'team_id' => $user->current_team_id,
            'name' => 'João Silva',
            'active' => true,
        ]);
    }

    public function test_a_ready_delivery_can_be_dispatched_to_an_available_courier(): void
    {
        $user = User::factory()->create();
        $courier = Courier::factory()->create(['team_id' => $user->current_team_id]);
        $order = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::Ready,
        ]);

        $this->actingAs($user)->patchJson(route('orders.dispatch', $order), [
            'courier_id' => $courier->id,
        ])->assertOk()
            ->assertJsonPath('data.courier.id', $courier->id)
            ->assertJsonPath('data.courier.name', $courier->name);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'courier_id' => $courier->id,
        ]);
        $this->assertNotNull($order->refresh()->dispatched_at);
    }

    public function test_only_deliveries_in_preparation_or_ready_can_have_a_courier_selected(): void
    {
        $user = User::factory()->create();
        $courier = Courier::factory()->create(['team_id' => $user->current_team_id]);
        $takeaway = Order::factory()->create([
            'modality' => OrderModality::Takeaway,
            'status' => OrderStatus::Ready,
        ]);
        $newDelivery = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::New,
        ]);

        foreach ([$takeaway, $newDelivery] as $order) {
            $this->actingAs($user)->patchJson(route('orders.dispatch', $order), [
                'courier_id' => $courier->id,
            ])->assertUnprocessable()->assertJsonValidationErrors('order');
        }
    }

    public function test_a_courier_must_be_active_and_belong_to_the_current_team(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::Ready,
        ]);
        $inactiveCourier = Courier::factory()->create([
            'team_id' => $user->current_team_id,
            'active' => false,
        ]);

        $this->actingAs($user)->patchJson(route('orders.dispatch', $order), [
            'courier_id' => $inactiveCourier->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('courier_id');

        $otherUser = User::factory()->create();
        $otherCourier = Courier::factory()->create(['team_id' => $otherUser->current_team_id]);

        $this->patchJson(route('orders.dispatch', $order), [
            'courier_id' => $otherCourier->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('courier_id');

    }

    public function test_a_courier_can_receive_multiple_deliveries_at_the_same_time(): void
    {
        $user = User::factory()->create();
        $courier = Courier::factory()->create(['team_id' => $user->current_team_id]);
        $firstOrder = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::Ready,
        ]);
        $secondOrder = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::Ready,
        ]);

        $this->actingAs($user)->patchJson(route('orders.dispatch', $firstOrder), [
            'courier_id' => $courier->id,
        ])->assertOk();

        $this->patchJson(route('orders.dispatch', $secondOrder), [
            'courier_id' => $courier->id,
        ])->assertOk();

        $this->assertSame(2, $courier->orders()->whereNotNull('dispatched_at')->count());
    }

    public function test_the_courier_can_be_changed_without_replacing_the_dispatch_time(): void
    {
        $user = User::factory()->create();
        $firstCourier = Courier::factory()->create(['team_id' => $user->current_team_id]);
        $secondCourier = Courier::factory()->create(['team_id' => $user->current_team_id]);
        $dispatchedAt = now()->subMinutes(10)->startOfSecond();
        $order = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::Ready,
            'courier_id' => $firstCourier->id,
            'dispatched_at' => $dispatchedAt,
        ]);

        $this->actingAs($user)->patchJson(route('orders.dispatch', $order), [
            'courier_id' => $secondCourier->id,
        ])->assertOk()
            ->assertJsonPath('data.courier.id', $secondCourier->id);

        $order->refresh();

        $this->assertSame($secondCourier->id, $order->courier_id);
        $this->assertTrue($order->dispatched_at?->equalTo($dispatchedAt));
    }

    public function test_a_delivery_gets_a_courier_during_preparation_then_advances_to_ready(): void
    {
        $user = User::factory()->create();
        $firstCourier = Courier::factory()->create(['team_id' => $user->current_team_id]);
        $secondCourier = Courier::factory()->create(['team_id' => $user->current_team_id]);
        $order = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::InPreparation,
        ]);

        $this->actingAs($user)->patchJson(route('orders.advance', $order), [
            'status' => OrderStatus::Ready->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->patchJson(route('orders.dispatch', $order), [
            'courier_id' => $firstCourier->id,
        ])->assertOk()->assertJsonPath('data.courier.id', $firstCourier->id);

        $this->patchJson(route('orders.dispatch', $order), [
            'courier_id' => $secondCourier->id,
        ])->assertOk()->assertJsonPath('data.courier.id', $secondCourier->id);

        $this->patchJson(route('orders.advance', $order), [
            'status' => OrderStatus::Ready->value,
        ])->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Ready->value)
            ->assertJsonPath('data.courier.id', $secondCourier->id);
    }

    public function test_a_new_delivery_can_start_preparation_without_a_courier(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::New,
        ]);

        $this->actingAs($user)->patchJson(route('orders.advance', $order), [
            'status' => OrderStatus::InPreparation->value,
        ])->assertOk()
            ->assertJsonPath('data.status', OrderStatus::InPreparation->value);

        $this->assertNull($order->refresh()->courier_id);
    }

    public function test_a_delivery_must_be_dispatched_before_it_can_be_finalized(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::Ready,
        ]);

        $this->actingAs($user)->patchJson(route('orders.advance', $order), [
            'status' => OrderStatus::Finalized->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $courier = Courier::factory()->create(['team_id' => $user->current_team_id]);
        $order->update(['courier_id' => $courier->id, 'dispatched_at' => now()]);

        $this->patchJson(route('orders.advance', $order), [
            'status' => OrderStatus::Finalized->value,
        ])->assertOk()->assertJsonPath('data.status', OrderStatus::Finalized->value);
    }

    public function test_courier_availability_can_only_be_changed_inside_the_current_team(): void
    {
        $user = User::factory()->create();
        $courier = Courier::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)->patchJson(route('couriers.availability', $courier), [
            'active' => false,
        ])->assertOk()->assertJsonPath('data.active', false);

        $otherUser = User::factory()->create();
        $otherCourier = Courier::factory()->create(['team_id' => $otherUser->current_team_id]);

        $this->patchJson(route('couriers.availability', $otherCourier), [
            'active' => false,
        ])->assertNotFound();
    }
}
