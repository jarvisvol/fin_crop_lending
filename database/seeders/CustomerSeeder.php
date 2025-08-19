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
                'name' => 'Rajesh Kumar',
                'email' => 'rajesh@example.com',
                'phone_number' => '+91-9876543210',
                'address' => '123 Main Street, Bangalore, Karnataka',
                'pan' => 'ABCDE1234F',
                'policy_number' => 'POL0012024',
                'policy_type' => 'Life Insurance',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
            [
                'name' => 'Priya Sharma',
                'email' => 'priya@example.com',
                'phone_number' => '+91-8765432109',
                'address' => '456 Oak Avenue, Mumbai, Maharashtra',
                'pan' => 'BCDEF2345G',
                'policy_number' => 'POL0022024',
                'policy_type' => 'Health Insurance',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
            [
                'name' => 'Amit Patel',
                'email' => 'amit@example.com',
                'phone_number' => '+91-7654321098',
                'address' => '789 Pine Road, Ahmedabad, Gujarat',
                'pan' => 'CDEFG3456H',
                'policy_number' => 'POL0032024',
                'policy_type' => 'Motor Insurance',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
            [
                'name' => 'Sneha Reddy',
                'email' => 'sneha@example.com',
                'phone_number' => '+91-6543210987',
                'address' => '321 Elm Street, Hyderabad, Telangana',
                'pan' => 'DEFGH4567I',
                'policy_number' => 'POL0042024',
                'policy_type' => 'Term Insurance',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
            [
                'name' => 'Vikram Singh',
                'email' => 'vikram@example.com',
                'phone_number' => '+91-9432109876',
                'address' => '654 Maple Lane, Delhi, NCR',
                'pan' => 'EFGHI5678J',
                'policy_number' => 'POL0052024',
                'policy_type' => 'Travel Insurance',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        ];

        foreach ($customers as $customerData) {
            Customer::create($customerData);
        }

        $this->command->info('Sample customers with auth data seeded successfully!');
    }
}