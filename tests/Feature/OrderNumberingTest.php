<?php

namespace Tests\Feature;

use App\Actions\Teams\UpdateStoreStatus;
use App\Enums\TeamRole;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class OrderNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_store_has_an_independent_sequence_and_internal_ids_remain_unique(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $first = Order::factory()->create(['team_id' => $firstOwner->current_team_id]);
        $second = Order::factory()->create(['team_id' => $secondOwner->current_team_id]);
        $third = Order::factory()->create(['team_id' => $firstOwner->current_team_id]);

        $this->assertSame([1, 1, 2], [$first->number, $second->number, $third->number]);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($second->id, (new OrderResource($second))->resolve()['id']);
        $this->assertSame(1, (new OrderResource($second))->resolve()['number']);
    }

    public function test_closing_resets_only_that_stores_next_sequence_and_preserves_old_orders(): void
    {
        $team = User::factory()->create()->currentTeam;
        $otherTeam = User::factory()->create()->currentTeam;
        $status = app(UpdateStoreStatus::class);
        $status->handle($team, true);
        $old = Order::factory()->create(['team_id' => $team->id]);
        Order::factory()->create(['team_id' => $team->id]);
        Order::factory()->create(['team_id' => $otherTeam->id]);

        $status->handle($team, true);
        $this->assertSame(3, $team->refresh()->next_order_number);
        $status->handle($team, false);
        $status->handle($team, false);
        $status->handle($team, true);
        $new = Order::factory()->create(['team_id' => $team->id]);
        $other = Order::factory()->create(['team_id' => $otherTeam->id]);

        $this->assertSame(1, $new->number);
        $this->assertSame(2, $new->numbering_cycle);
        $this->assertSame(1, $old->refresh()->number);
        $this->assertSame(1, $old->numbering_cycle);
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(2, $other->number);
        $this->assertSame(1, $other->numbering_cycle);
    }

    public function test_members_of_the_same_team_share_a_sequence_and_search_uses_the_displayed_number(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        // Make the global IDs differ from this store's displayed numbers.
        $other = User::factory()->create();
        Order::factory()->count(3)->create(['team_id' => $other->current_team_id]);
        $team = $owner->currentTeam;
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member]);
        $member->switchTeam($team);
        $first = Order::factory()->create(['team_id' => $team->id]);
        $second = Order::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)->getJson(route('orders.index'))->assertOk()
            ->assertJsonFragment(['id' => $second->id, 'number' => 2]);
        $this->get(route('orders.history', ['current_team' => $team->slug, 'q' => '1']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)
                ->where('orders.data.0.id', $first->id)->where('orders.data.0.number', 1));
        $this->get(route('orders.command', ['current_team' => $team->slug, 'order' => $second->id]))
            ->assertInertia(fn (Assert $page) => $page->where('command.number', 2));
        $this->get(route('dashboard', $team->slug))->assertInertia(fn (Assert $page) => $page
            ->where('recentOrders', fn ($orders): bool => collect($orders)->contains(fn ($order): bool => $order['id'] === $second->id && $order['number'] === 2)));
    }

    public function test_number_reservation_rolls_back_with_failed_order_transactions(): void
    {
        $team = User::factory()->create()->currentTeam;
        try {
            DB::transaction(function () use ($team): void {
                Order::factory()->create(['team_id' => $team->id]);
                throw new RuntimeException('Rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback', $exception->getMessage());
        }
        $order = Order::factory()->create(['team_id' => $team->id]);
        $this->assertSame(1, $order->number);
        $this->assertSame(2, $team->refresh()->next_order_number);
    }

    public function test_migration_backfills_existing_orders_per_store_without_changing_ids(): void
    {
        $firstTeam = User::factory()->create()->currentTeam;
        $secondTeam = User::factory()->create()->currentTeam;
        $first = Order::factory()->create(['team_id' => $firstTeam->id]);
        $second = Order::factory()->create(['team_id' => $secondTeam->id]);
        $third = Order::factory()->create(['team_id' => $firstTeam->id]);
        $migration = require database_path('migrations/2026_10_16_000005_add_store_order_numbering.php');
        $migration->down();
        $migration->up();

        $this->assertSame(1, $first->refresh()->number);
        $this->assertSame(1, $second->refresh()->number);
        $this->assertSame(2, $third->refresh()->number);
        $this->assertSame(3, $firstTeam->refresh()->next_order_number);
        $this->assertSame(2, $secondTeam->refresh()->next_order_number);
        $this->assertDatabaseCount('orders', 3);
    }
}
