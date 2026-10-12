<?php

namespace Tests\Feature;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\TeamRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $product = Product::factory()->create(['name' => 'X-Salada']);
        Product::factory()->inactive()->create(['name' => 'Produto indisponível']);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.todayOrders', 0)
            ->where('stats.inPreparation', 0)
            ->where('stats.ready', 0)
            ->has('recentOrders', 0)
            ->has('products', 1)
            ->where('products.0.id', $product->id)
            ->where('products.0.name', 'X-Salada'),
        );
    }

    public function test_dashboard_summarizes_todays_orders_and_lists_recent_orders(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'modality' => OrderModality::DineIn,
            'status' => OrderStatus::New,
            'created_at' => now()->subMinutes(20),
        ]);
        Order::factory()->create([
            'status' => OrderStatus::InPreparation,
            'created_at' => now()->subMinutes(15),
        ]);
        Order::factory()->create([
            'modality' => OrderModality::Takeaway,
            'status' => OrderStatus::Ready,
            'created_at' => now()->subMinutes(10),
        ]);
        $latestOrder = Order::factory()->create([
            'modality' => OrderModality::Delivery,
            'status' => OrderStatus::Finalized,
            'customer_name' => 'Mariana Costa',
            'customer_phone' => '(11) 99999-0000',
            'payment_method' => 'dinheiro',
            'change_for' => '60.00',
            'delivery_fee' => '6.00',
            'delivery_address' => [
                'postal_code' => '01001-000',
                'street' => 'Praça da Sé',
                'number' => '100',
                'complement' => 'Apto 2',
                'neighborhood' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
            ],
            'created_at' => now()->subMinutes(5),
        ]);
        $latestOrder->items()->create([
            'product_name' => 'X-Bacon',
            'unit_price' => '24.50',
            'quantity' => 2,
            'notes' => 'Sem cebola',
        ]);
        Order::factory()->create([
            'status' => OrderStatus::InPreparation,
            'created_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.todayOrders', 4)
            ->where('stats.newOrders', 1)
            ->where('stats.inPreparation', 1)
            ->where('stats.ready', 1)
            ->where('stats.finalized', 1)
            ->has('recentOrders', 5)
            ->where('recentOrders.0.id', $latestOrder->id)
            ->where('recentOrders.0.modalityLabel', 'Entrega')
            ->where('recentOrders.0.statusLabel', 'Finalizado')
            ->where('recentOrders.0.customerName', 'Mariana Costa')
            ->where('recentOrders.0.customerPhone', '(11) 99999-0000')
            ->where('recentOrders.0.paymentMethodLabel', 'Dinheiro')
            ->where('recentOrders.0.changeFor', '60.00')
            ->where('recentOrders.0.changeAmount', '5.00')
            ->where('recentOrders.0.deliveryFee', '6.00')
            ->where('recentOrders.0.deliveryAddress.postalCode', '01001-000')
            ->where('recentOrders.0.deliveryAddress.street', 'Praça da Sé')
            ->where('recentOrders.0.total', '55.00')
            ->has('recentOrders.0.items', 1)
            ->where('recentOrders.0.items.0.productName', 'X-Bacon')
            ->where('recentOrders.0.items.0.quantity', 2)
            ->where('recentOrders.0.items.0.notes', 'Sem cebola'),
        );
    }

    public function test_dashboard_includes_pending_invitations_for_the_authenticated_user()
    {
        $owner = User::factory()->create(['name' => 'Taylor Otwell']);
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create(['name' => 'Laravel Team']);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.code', $invitation->code)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.team.name', 'Laravel Team')
            ->where('pendingInvitations.0.team.slug', $team->slug)
            ->missing('pendingInvitations.0.teamName'),
        );
    }

    public function test_dashboard_does_not_include_accepted_invitations()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        TeamInvitation::factory()->accepted()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 0),
        );
    }

    public function test_dashboard_excludes_expired_invitations_without_deleting_them()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_dashboard_does_not_include_or_delete_other_users_invitations()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'someone@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }
}
