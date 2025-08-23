<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Support\Str;

class PolicySubscription extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'policy_subscriptions';
    
    protected $fillable = [
        'subscription_id',
        'customer_id',
        'policy_id',
        'investment_amount',
        'start_date',
        'maturity_date',
        'expected_maturity_amount',
        'status',
        'notes'
    ];
    
    protected $casts = [
        'investment_amount' => 'decimal:2',
        'expected_maturity_amount' => 'decimal:2',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            if (empty($subscription->subscription_id)) {
                $subscription->subscription_id = static::generateSubscriptionId();
            }
        });
    }

    public static function generateSubscriptionId(): string
    {
        $prefix = 'SUB';
        $random = Str::upper(Str::random(6));
        $timestamp = now()->format('mdHis');
        
        return $prefix . $random . $timestamp;
    }

    /**
     * Get the customer that owns the subscription
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the policy that owns the subscription
     */
    public function policy()
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function getRemainingDaysAttribute(): int
    {
        return now()->diffInDays($this->maturity_date, false);
    }

    public function getIsMaturedAttribute(): bool
    {
        return now() >= $this->maturity_date;
    }

    public function markAsMatured()
    {
        $this->update(['status' => 'matured']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMatured($query)
    {
        return $query->where('status', 'matured');
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}