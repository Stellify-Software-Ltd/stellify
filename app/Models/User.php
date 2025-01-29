<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Cashier\Billable;
use function Illuminate\Events\queueable;
use App\Models\Permissions;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'project_id',
        'slug',
        'email',
        'password',
        'branch_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pm_type',
        'pm_last_four',
        'country',
        'email_verified_at',
        'ends_at',
        'facebook_id',
        'created_at',
        'permissions',
        'subscriptions',
        'data',
        'gc_customer_id',
        'gc_mandate_id',
        'gc_mandate_status',
        'github_id',
        'google_id',
        'postal_code',
        'project_id',
        'stripe_id',
        'trial_ends_at',
        'type',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(queueable(function (User $customer) {
            if ($customer->hasStripeId()) {
                $customer->syncStripeCustomerDetails();
            }
        }));
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permissions::class, 'slug', 'slug');
    }
}
