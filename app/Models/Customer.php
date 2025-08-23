<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class Customer extends Model implements JWTSubject, AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'customers';
    
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'date_of_birth',
        'pan_number',
        'aadhaar_number',
        'bank_account_number',
        'bank_name',
        'bank_ifsc',
        'address',
        'city',
        'state',
        'pincode',
        'kyc_status',
        'kyc_verified_at',
        'password',
        'device_token',
        'last_login_at',
        'last_login_ip',
        'is_active',
        'documents' // For storing document references
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
        'aadhaar_number',
        'pan_number',
        'bank_account_number'
    ];
    
    protected $casts = [
        'date_of_birth' => 'date',
        'kyc_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'documents' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'kyc_status' => $this->kyc_status
        ];
    }

    /**
     * Check if KYC is verified
     */
    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified' && $this->kyc_verified_at !== null;
    }

    /**
     * Get masked Aadhaar number
     */
    public function getMaskedAadhaarAttribute(): string
    {
        return $this->aadhaar_number ? 'XXXX-XXXX-' . substr($this->aadhaar_number, -4) : '';
    }

    /**
     * Get masked PAN number
     */
    public function getMaskedPanAttribute(): string
    {
        return $this->pan_number ? substr($this->pan_number, 0, 5) . 'XXXX' . substr($this->pan_number, -1) : '';
    }

    /**
     * Get masked bank account number
     */
    public function getMaskedBankAccountAttribute(): string
    {
        return $this->bank_account_number ? 'XXXX-XXXX-' . substr($this->bank_account_number, -4) : '';
    }

    /**
     * Get age from date of birth
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    /**
     * Get complete address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->pincode
        ]);

        return implode(', ', $parts);
    }

    /**
     * Relationship with policy subscriptions
     */
    public function subscriptions()
    {
        return $this->hasMany(PolicySubscription::class, 'customer_id');
    }

    /**
     * Get active subscriptions
     */
    public function activeSubscriptions()
    {
        return $this->subscriptions()->where('status', 'active');
    }
}