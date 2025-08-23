<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Support\Str;

class Policy extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'policies';
    
    protected $fillable = [
        'policy_number',
        'name',
        'type',
        'duration',
        'interest_rate',
        'min_investment',
        'max_investment',
        'description',
        'benefits',
        'is_active'
    ];
    
    protected $casts = [
        'interest_rate' => 'decimal:2',
        'min_investment' => 'decimal:2',
        'max_investment' => 'decimal:2',
        'duration' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($policy) {
            if (empty($policy->policy_number)) {
                $policy->policy_number = static::generatePolicyNumber();
            }
        });
    }

    public static function generatePolicyNumber(): string
    {
        $prefix = 'POL';
        $random = Str::upper(Str::random(6));
        $timestamp = now()->format('mdHis');
        
        return $prefix . $random . $timestamp;
    }

    /**
     * Get subscriptions for this policy
     * For MongoDB, we use hasMany with foreign key
     */
    public function subscriptions()
    {
        return $this->hasMany(PolicySubscription::class, 'policy_id');
    }

    public function activeSubscriptions()
    {
        return $this->subscriptions()->where('status', 'active');
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'monthly' => 'Monthly Investment',
            'daily' => 'Daily Investment',
            'digital_gold' => 'Digital Gold',
            default => ucfirst($this->type)
        };
    }

    public function calculateMaturityAmount($investmentAmount): float
    {
        $years = $this->duration;
        $rate = $this->interest_rate / 100;
        
        // Compound interest calculation
        $maturityAmount = $investmentAmount * pow(1 + $rate, $years);
        
        return round($maturityAmount, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDuration($query, $duration)
    {
        return $query->where('duration', $duration);
    }

    public function scopeWithHigherInterest($query, $minRate = 0)
    {
        return $query->where('interest_rate', '>', $minRate);
    }
}