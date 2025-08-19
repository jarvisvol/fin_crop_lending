<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Policy extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'policies';
    
    protected $fillable = [
        'policy_number',
        'policy_name',
        'term_plan',
        'rate_of_interest',
        'investment_type',
        'min_investment',
        'max_investment',
        'description',
        'benefits',
        'is_active',
        'valid_from',
        'valid_to'
    ];
    
    protected $casts = [
        'rate_of_interest' => 'decimal:2',
        'investment_type' => 'boolean',
        'min_investment' => 'decimal:2',
        'max_investment' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get investment type as text
     */
    public function getInvestmentTypeTextAttribute(): string
    {
        return $this->investment_type ? 'Monthly' : 'Daily';
    }

    /**
     * Check if policy is currently valid
     */
    public function getIsValidAttribute(): bool
    {
        $now = now();
        $validFrom = $this->valid_from ? $this->valid_from->lte($now) : true;
        $validTo = $this->valid_to ? $this->valid_to->gte($now) : true;
        
        return $this->is_active && $validFrom && $validTo;
    }

    /**
     * Scope active policies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope valid policies (active and within date range)
     */
    public function scopeValid($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where(function($q) use ($now) {
                $q->whereNull('valid_from')
                  ->orWhere('valid_from', '<=', $now);
            })
            ->where(function($q) use ($now) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', $now);
            });
    }

    /**
     * Scope by investment type
     */
    public function scopeByInvestmentType($query, $type)
    {
        return $query->where('investment_type', $type);
    }
}