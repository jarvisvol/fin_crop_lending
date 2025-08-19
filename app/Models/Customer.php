<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Customer extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'customers';
    
    protected $fillable = [
        'name',
        'phone_number',
        'address',
        'pan',
        'policy_number',
        'policy_type'
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * The attributes that should be unique.
     */
    public static function getUniqueFields(): array
    {
        return ['pan', 'policy_number'];
    }
}