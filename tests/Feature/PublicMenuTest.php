<?php

namespace Tests\Feature;

use App\Contracts\CashRegisterCalculator;
use App\Contracts\SalesReport;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TeamRole;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function storeTeam(): Team
    {
        $team = User::factory()->create()->currentTeam;
        $team->forceFill(['is_open' => true, 'online_delivery_fee' => '5.00'])->save();
        $team->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_public_'.$team->id, 'stripe_status' => 'trialing', 'trial_ends_at' => now()->addDays(2)]);

        return $team;
    }

    /** @return array<string, mixed> */
    private function payload(Team $team, Product $product): array
    {
        $token = (string) Str::uuid();
        $this->withSession(['public_checkout_tokens' => [$token => $team->id]]);

        return ['checkout_token' => $token, 'customer_name' => 'Ana', 'customer_phone' => '(11) 99999-9999', 'modality' => 'retirada', 'payment_method' => 'pix', 'items' => [['product_id' => $product->id, 'quantity' => 2, 'option_ids' => []]]];
    }

    public function test_public_menu_only_exposes_active_products_and_no_account_data_even_when_logged_in(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id]);
        Product::factory()->inactive()->create(['team_id' => $team->id]);
        Product::factory()->create(['team_id' => Team::factory()->create()->id]);
        Product::factory()->create(['team_id' => $team->id])->delete();
        $this->actingAs(User::factory()->create())->get(route('public-menu.show', $team->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('public-menu/Index')
            ->where('store.name', $team->name)->where('store.acceptingOrders', true)
            ->has('products', 1)->where('products.0.id', $product->id)->has('checkoutToken')
            ->missing('auth')->missing('teams')->missing('permissions')->missing('currentTeam'));
    }

    public function test_guest_order_uses_server_prices_enters_new_and_is_idempotent(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id, 'price' => '18.00']);
        $data = $this->payload($team, $product);
        $data['team_id'] = User::factory()->create()->current_team_id;
        $data['status'] = 'finalizado';
        $data['total'] = '0.01';
        $data['items'][0]['unit_price'] = '0.01';
        $url = route('public-menu.store', $team->slug);
        $this->postJson($url, $data)->assertCreated()->assertJsonPath('number', 1)->assertJsonPath('total', '36.00')->assertJsonMissingPath('customerId');
        $this->postJson($url, $data)->assertCreated()->assertJsonPath('number', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['team_id' => $team->id, 'status' => 'novo', 'is_online' => true]);
    }

    public function test_closed_or_unsubscribed_stores_cannot_receive_orders(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id]);
        $data = $this->payload($team, $product);
        $team->forceFill(['is_open' => false])->save();
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertUnprocessable()->assertJsonValidationErrors('store');
        $team->forceFill(['is_open' => true])->save();
        $team->subscriptions()->update(['trial_ends_at' => now()->subMinute()]);
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertUnprocessable()->assertJsonValidationErrors('store');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_fee_cannot_be_changed_by_customer_and_unconfigured_delivery_is_blocked(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id, 'price' => '18.00']);
        $data = $this->payload($team, $product);
        $data['modality'] = 'entrega';
        $data['delivery_fee'] = '0.01';
        $data['delivery_address'] = ['postal_code' => '01001-000', 'street' => 'Rua A', 'number' => '10', 'neighborhood' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP'];
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertCreated()->assertJsonPath('total', '41.00');
        $this->assertDatabaseHas('orders', ['team_id' => $team->id, 'delivery_fee' => '5.00']);
        $team->forceFill(['online_delivery_fee' => null])->save();
        $data = [...$data, ...['checkout_token' => $this->payload($team, $product)['checkout_token']]];
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertUnprocessable()->assertJsonValidationErrors('modality');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_foreign_inactive_and_deleted_products_and_foreign_checkout_tokens_are_rejected(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id]);
        $data = $this->payload($team, $product);
        $foreign = Product::factory()->create(['team_id' => Team::factory()->create()->id]);
        $data['items'][0]['product_id'] = $foreign->id;
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $data['items'][0]['product_id'] = $product->id;
        $product->update(['active' => false]);
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $product->update(['active' => true]);
        $data['checkout_token'] = (string) Str::uuid();
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertUnprocessable()->assertJsonValidationErrors('checkout_token');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_public_customer_details_do_not_overwrite_an_existing_customer(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id]);
        $customer = Customer::factory()->create(['team_id' => $team->id, 'name' => 'Nome original', 'phone' => '(11) 99999-9999', 'phone_normalized' => '11999999999']);
        $data = $this->payload($team, $product);
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertCreated();
        $this->assertSame('Nome original', $customer->refresh()->name);
        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'customer_name' => 'Ana']);
    }

    public function test_settings_require_permission_and_validate_fee(): void
    {
        $owner = User::factory()->create();
        $url = route('online-menu.update', $owner->currentTeam->slug);
        $this->actingAs($owner)->patch($url, ['online_delivery_fee' => '7.50'])->assertRedirect();
        $this->assertSame('7.50', $owner->currentTeam->refresh()->online_delivery_fee);
        $this->patchJson($url, ['online_delivery_fee' => '-1'])->assertUnprocessable();
        $owner->currentTeam->members()->updateExistingPivot($owner->id, ['role' => TeamRole::Member->value]);
        $this->patchJson($url, ['online_delivery_fee' => '0'])->assertForbidden();
    }

    public function test_addons_and_change_are_validated_and_priced_on_the_server(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id, 'price' => '18.00']);
        $group = $product->optionGroups()->create(['name' => 'Adicionais', 'min_selections' => 1, 'max_selections' => 1]);
        $bacon = $group->options()->create(['name' => 'Bacon', 'price' => '4.00', 'active' => true]);
        $disabled = $group->options()->create(['name' => 'Indisponível', 'price' => '1.00', 'active' => false]);
        $data = $this->payload($team, $product);
        $url = route('public-menu.store', $team->slug);
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.option_ids');
        $data['items'][0]['option_ids'] = [$disabled->id];
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.option_ids');
        $data['items'][0]['option_ids'] = [$bacon->id];
        $data['payment_method'] = 'dinheiro';
        $data['needs_change'] = true;
        $data['change_for'] = '40.00';
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('change_for');
        $data['change_for'] = '50.00';
        $this->postJson($url, $data)->assertCreated()->assertJsonPath('total', '44.00');
        $this->assertSame('22.00', Order::query()->firstOrFail()->items()->firstOrFail()->unit_price);
    }

    public function test_public_checkout_is_rate_limited_and_requires_same_site_csrf(): void
    {
        $team = $this->storeTeam();
        $url = route('public-menu.store', $team->slug);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson($url, [])->assertUnprocessable();
        }
        $this->postJson($url, [])->assertTooManyRequests();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cross_site_checkout_cannot_bypass_csrf_protection(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id]);
        $data = $this->payload($team, $product);
        $this->app->instance('env', 'local');
        try {
            $this->withHeader('Sec-Fetch-Site', 'cross-site')->postJson(route('public-menu.store', $team->slug), $data)->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_public_card_payment_is_recorded_without_guessing_credit_or_debit(): void
    {
        $team = $this->storeTeam();
        $product = Product::factory()->create(['team_id' => $team->id, 'price' => '18.00']);
        $data = $this->payload($team, $product);
        $data['payment_method'] = 'cartao';
        $this->postJson(route('public-menu.store', $team->slug), $data)->assertCreated()->assertJsonPath('total', '36.00');
        $order = Order::query()->firstOrFail();
        $this->assertSame(PaymentMethod::Card, $order->payment_method);
        $this->assertSame('Cartão', $order->payment_method->label());
        $register = CashRegister::factory()->create(['team_id' => $team->id, 'opened_by' => $team->owner()->id]);
        $order->update(['status' => OrderStatus::Finalized, 'finalized_at' => now(), 'cash_register_id' => $register->id]);
        $sales = app(CashRegisterCalculator::class)->salesTotals($register);
        $this->assertSame('36.00', $sales['cartao']);
        $this->assertSame('0.00', $sales['credito']);
        $this->assertSame('0.00', $sales['debito']);
        $report = app(SalesReport::class)->generate($team->id, CarbonImmutable::today(), CarbonImmutable::tomorrow());
        $this->assertSame('36.00', collect($report['payments'])->firstWhere('value', 'cartao')['total']);
    }

    public function test_free_delivery_can_be_enabled_and_disabled_through_menu_settings(): void
    {
        $team = $this->storeTeam();
        $this->actingAs($team->owner())->patch(route('online-menu.update', $team->slug), ['online_delivery_fee' => '0'])->assertRedirect();
        $this->assertSame('0.00', $team->refresh()->online_delivery_fee);
        $this->get(route('public-menu.show', $team->slug))->assertInertia(fn (Assert $page) => $page->where('store.deliveryFee', '0.00'));
        $this->patch(route('online-menu.update', $team->slug), ['online_delivery_fee' => null])->assertRedirect();
        $this->assertNull($team->refresh()->online_delivery_fee);
    }
}
