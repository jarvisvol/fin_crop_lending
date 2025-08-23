<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Document;
use App\Models\Customer;

class DocumentSeeder extends Seeder
{
    public function run()
    {
        $customers = Customer::all();

        foreach ($customers as $customer) {
            // Aadhaar Card
            Document::create([
                'customer_id' => $customer->id,
                'document_type' => 'aadhaar',
                'document_number' => $customer->aadhaar_number,
                'file_path' => 'documents/sample/aadhaar.pdf',
                'file_name' => 'aadhaar_card.pdf',
                'file_size' => 102400,
                'mime_type' => 'application/pdf',
                'verification_status' => 'verified',
                'verified_at' => now()->subMonths(6),
            ]);

            // PAN Card
            Document::create([
                'customer_id' => $customer->id,
                'document_type' => 'pan',
                'document_number' => $customer->pan_number,
                'file_path' => 'documents/sample/pan.pdf',
                'file_name' => 'pan_card.pdf',
                'file_size' => 51200,
                'mime_type' => 'application/pdf',
                'verification_status' => 'verified',
                'verified_at' => now()->subMonths(6),
            ]);

            // Bank Passbook
            Document::create([
                'customer_id' => $customer->id,
                'document_type' => 'bank_passbook',
                'document_number' => $customer->bank_account_number,
                'file_path' => 'documents/sample/passbook.pdf',
                'file_name' => 'bank_passbook.pdf',
                'file_size' => 204800,
                'mime_type' => 'application/pdf',
                'verification_status' => 'verified',
                'verified_at' => now()->subMonths(6),
            ]);
        }

        $this->command->info('Sample documents seeded successfully!');
    }
}