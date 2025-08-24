<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Portfolio extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'portfolios';
    
    protected $fillable = [
        'customer_id',
        'policy_subscription_id',
        'policy_id',
        'policy_number',
        'policy_name',
        'policy_type',
        'investment_amount',
        'current_value',
        'total_gain',
        'interest_earned',
        'start_date',
        'maturity_date',
        'duration',
        'interest_rate',
        'status',
        'last_updated'
    ];
    
    protected $casts = [
        'investment_amount' => 'decimal:2',
        'current_value' => 'decimal:2',
        'total_gain' => 'decimal:2',
        'interest_earned' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'last_updated' => 'datetime',
        'duration' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function policy()
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function subscription()
    {
        return $this->belongsTo(PolicySubscription::class, 'policy_subscription_id');
    }

    public function calculateCurrentValue(): float
    {
        $startDate = $this->start_date;
        $maturityDate = $this->maturity_date;
        $now = now();
        
        if ($now >= $maturityDate) {
            // Policy matured, return maturity value
            return $this->investment_amount * pow(1 + ($this->interest_rate / 100), $this->duration);
        }
        
        // Calculate partial interest based on time elapsed
        $elapsedMonths = $startDate->diffInMonths($now);
        $totalMonths = $this->duration * 12;
        
        $partialInterest = ($this->interest_rate / 100) * ($elapsedMonths / $totalMonths);
        
        return $this->investment_amount * (1 + $partialInterest);
    }

    public function calculateGain(): float
    {
        return $this->current_value - $this->investment_amount;
    }

    public function getDaysRemaining(): int
    {
        return now()->diffInDays($this->maturity_date, false);
    }

    public function getProgressPercentage(): float
    {
        $totalDays = $this->start_date->diffInDays($this->maturity_date);
        $elapsedDays = $this->start_date->diffInDays(now());
        
        return min(100, max(0, ($elapsedDays / $totalDays) * 100));
    }

    public function isMatured(): bool
    {
        return now() >= $this->maturity_date;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMatured($query)
    {
        return $query->where('maturity_date', '<=', now());
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}