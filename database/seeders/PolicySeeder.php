<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Policy;
use Carbon\Carbon;

class PolicySeeder extends Seeder
{
    public function run()
    {
        $policies = [
            // Monthly Investment Policies
            [
                'name' => 'Monthly Savings Plan - 1 Year',
                'type' => 'monthly',
                'duration' => 1,
                'interest_rate' => 6.5,
                'min_investment' => 1000,
                'max_investment' => 100000,
                'description' => 'Monthly investment plan with guaranteed returns',
                'benefits' => 'Regular income, tax benefits, life coverage',
                'is_active' => true,
            ],
            [
                'name' => 'Monthly Savings Plan - 2 Years',
                'type' => 'monthly',
                'duration' => 2,
                'interest_rate' => 7.2,
                'min_investment' => 1000,
                'max_investment' => 200000,
                'description' => '2-year monthly investment with higher returns',
                'benefits' => 'Higher returns, financial security',
                'is_active' => true,
            ],
            [
                'name' => 'Monthly Savings Plan - 3 Years',
                'type' => 'monthly',
                'duration' => 3,
                'interest_rate' => 8.0,
                'min_investment' => 2000,
                'max_investment' => 300000,
                'description' => '3-year monthly investment plan',
                'benefits' => 'Best for long-term savings, high returns',
                'is_active' => true,
            ],
            [
                'name' => 'Monthly Savings Plan - 5 Years',
                'type' => 'monthly',
                'duration' => 5,
                'interest_rate' => 9.5,
                'min_investment' => 5000,
                'max_investment' => 500000,
                'description' => '5-year premium monthly investment',
                'benefits' => 'Maximum returns, wealth creation',
                'is_active' => true,
            ],

            // Daily Investment Policies
            [
                'name' => 'Daily Savings Plan - 1 Year',
                'type' => 'daily',
                'duration' => 1,
                'interest_rate' => 7.0,
                'min_investment' => 500,
                'max_investment' => 50000,
                'description' => 'Daily investment with flexible contributions',
                'benefits' => 'Daily compounding, flexible payments',
                'is_active' => true,
            ],
            [
                'name' => 'Daily Savings Plan - 2 Years',
                'type' => 'daily',
                'duration' => 2,
                'interest_rate' => 7.8,
                'min_investment' => 500,
                'max_investment' => 100000,
                'description' => '2-year daily investment plan',
                'benefits' => 'Higher daily returns, financial discipline',
                'is_active' => true,
            ],
            [
                'name' => 'Daily Savings Plan - 3 Years',
                'type' => 'daily',
                'duration' => 3,
                'interest_rate' => 8.5,
                'min_investment' => 1000,
                'max_investment' => 200000,
                'description' => '3-year daily investment strategy',
                'benefits' => 'Best for regular savers, excellent returns',
                'is_active' => true,
            ],
            [
                'name' => 'Daily Savings Plan - 5 Years',
                'type' => 'daily',
                'duration' => 5,
                'interest_rate' => 10.0,
                'min_investment' => 2000,
                'max_investment' => 300000,
                'description' => '5-year premium daily investment',
                'benefits' => 'Maximum daily compounding, wealth growth',
                'is_active' => true,
            ],

            // Digital Gold Policies
            [
                'name' => 'Digital Gold - 1 Year',
                'type' => 'digital_gold',
                'duration' => 1,
                'interest_rate' => 5.5,
                'min_investment' => 1000,
                'max_investment' => 1000000,
                'description' => 'Invest in digital gold with guaranteed returns',
                'benefits' => 'Gold security, inflation protection',
                'is_active' => true,
            ],
            [
                'name' => 'Digital Gold - 2 Years',
                'type' => 'digital_gold',
                'duration' => 2,
                'interest_rate' => 6.2,
                'min_investment' => 1000,
                'max_investment' => 1000000,
                'description' => '2-year digital gold investment',
                'benefits' => 'Long-term gold investment, secure returns',
                'is_active' => true,
            ],
            [
                'name' => 'Digital Gold - 3 Years',
                'type' => 'digital_gold',
                'duration' => 3,
                'interest_rate' => 7.0,
                'min_investment' => 2000,
                'max_investment' => 1000000,
                'description' => '3-year digital gold plan',
                'benefits' => 'Premium gold investment, high security',
                'is_active' => true,
            ],
            [
                'name' => 'Digital Gold - 5 Years',
                'type' => 'digital_gold',
                'duration' => 5,
                'interest_rate' => 8.5,
                'min_investment' => 5000,
                'max_investment' => 1000000,
                'description' => '5-year premium digital gold',
                'benefits' => 'Maximum gold returns, wealth preservation',
                'is_active' => true,
            ],
        ];

        foreach ($policies as $policyData) {
            Policy::create($policyData);
        }

        $this->command->info('Policies seeded successfully!');
    }
}