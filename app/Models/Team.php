<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $stripe_id
 * @property Carbon|null $trial_ends_at
 * @property string|null $billing_checkout_id
 * @property string|null $billing_checkout_token
 * @property string|null $billing_checkout_plan
 * @property Carbon|null $billing_checkout_expires_at
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Courier> $couriers
 * @property-read Collection<int, CashRegister> $cashRegisters
 * @property-read Collection<int, Customer> $customers
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, ProductCategory> $productCategories
 * @property-read Collection<int, Order> $orders
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 */
#[Fillable(['name', 'slug', 'is_personal'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use Billable, GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    protected $hidden = [
        'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at',
        'billing_checkout_id', 'billing_checkout_token',
        'billing_checkout_plan', 'billing_checkout_expires_at',
    ];

    public function stripeEmail(): ?string
    {
        $owner = $this->owner();

        return $owner instanceof User ? $owner->email : null;
    }

    public function hasOngoingSubscription(): bool
    {
        return $this->subscriptions()->where(function ($query): void {
            $query->whereNotIn('stripe_status', ['canceled', 'incomplete_expired'])
                ->orWhere('ends_at', '>', now());
        })->exists();
    }

    public function hasPendingCheckout(): bool
    {
        return $this->billing_checkout_token !== null && $this->billing_checkout_expires_at?->isFuture() === true;
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** @return HasMany<Courier, $this> */
    public function couriers(): HasMany
    {
        return $this->hasMany(Courier::class);
    }

    /** @return HasMany<CashRegister, $this> */
    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<ProductCategory, $this> */
    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'trial_ends_at' => 'datetime',
            'billing_checkout_expires_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
