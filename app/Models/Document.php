<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Document extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'documents';
    
    protected $fillable = [
        'customer_id',
        'document_type',
        'document_number',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'verification_status',
        'verified_at',
        'verified_by',
        'rejection_reason'
    ];
    
    protected $casts = [
        'verified_at' => 'datetime',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const TYPE_AADHAAR = 'aadhaar';
    const TYPE_PAN = 'pan';
    const TYPE_PASSBOOK = 'bank_passbook';
    const TYPE_PHOTO = 'photo';
    const TYPE_SIGNATURE = 'signature';

    public static function getDocumentTypes(): array
    {
        return [
            self::TYPE_AADHAAR => 'Aadhaar Card',
            self::TYPE_PAN => 'PAN Card',
            self::TYPE_PASSBOOK => 'Bank Passbook',
            self::TYPE_PHOTO => 'Photograph',
            self::TYPE_SIGNATURE => 'Signature'
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function getDocumentTypeNameAttribute(): string
    {
        return self::getDocumentTypes()[$this->document_type] ?? $this->document_type;
    }
}