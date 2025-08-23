<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        $customers = [
            [
                'name' => 'Ankit Sharma',
                'email' => 'ankit.sharma@example.com',
                'phone_number' => '+91 98765 43210',
                'date_of_birth' => '1990-08-15',
                'pan_number' => 'ABCDE1234F',
                'aadhaar_number' => '123456789012',
                'bank_account_number' => '12345678901234',
                'bank_name' => 'HDFC Bank',
                'bank_ifsc' => 'HDFC0001234',
                'address' => '123, Green Park',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'pincode' => '110016',
                'kyc_status' => 'verified',
                'kyc_verified_at' => now()->subMonths(6),
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::create($customerData);
        }

        $this->command->info('Sample customers with auth data seeded successfully!');
    }
}