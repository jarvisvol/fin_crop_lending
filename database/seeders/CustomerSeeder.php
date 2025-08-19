<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $customers = [
            [
                'name' => 'Rajesh Kumar',
                'phone_number' => '+91-9876543210',
                'address' => '123 Main Street, Bangalore, Karnataka',
                'pan' => 'ABCDE1234F',
                'policy_number' => 'POL0012024',
                'policy_type' => 'Life Insurance'
            ],
            [
                'name' => 'Priya Sharma',
                'phone_number' => '+91-8765432109',
                'address' => '456 Oak Avenue, Mumbai, Maharashtra',
                'pan' => 'BCDEF2345G',
                'policy_number' => 'POL0022024',
                'policy_type' => 'Health Insurance'
            ],
            [
                'name' => 'Amit Patel',
                'phone_number' => '+91-7654321098',
                'address' => '789 Pine Road, Ahmedabad, Gujarat',
                'pan' => 'CDEFG3456H',
                'policy_number' => 'POL0032024',
                'policy_type' => 'Motor Insurance'
            ],
            [
                'name' => 'Sneha Reddy',
                'phone_number' => '+91-6543210987',
                'address' => '321 Elm Street, Hyderabad, Telangana',
                'pan' => 'DEFGH4567I',
                'policy_number' => 'POL0042024',
                'policy_type' => 'Term Insurance'
            ],
            [
                'name' => 'Vikram Singh',
                'phone_number' => '+91-9432109876',
                'address' => '654 Maple Lane, Delhi, NCR',
                'pan' => 'EFGHI5678J',
                'policy_number' => 'POL0052024',
                'policy_type' => 'Travel Insurance'
            ]
        ];

        foreach ($customers as $customerData) {
            Customer::create($customerData);
        }

        $this->command->info('Sample customers data seeded successfully!');
    }
}