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
            [
                'policy_number' => 'POL-LIFE-001',
                'policy_name' => 'Golden Life Plan',
                'term_plan' => '15 Years',
                'rate_of_interest' => 8.5,
                'investment_type' => 0, // Daily
                'min_investment' => 1000,
                'max_investment' => 100000,
                'description' => 'A comprehensive life insurance plan with daily investment options.',
                'benefits' => 'Life coverage, maturity benefits, tax savings',
                'is_active' => true,
                'valid_from' => Carbon::now()->subYear(),
                'valid_to' => Carbon::now()->addYears(5),
            ],
            [
                'policy_number' => 'POL-HEALTH-001',
                'policy_name' => 'Health Shield Plan',
                'term_plan' => '10 Years',
                'rate_of_interest' => 7.2,
                'investment_type' => 1, // Monthly
                'min_investment' => 2000,
                'max_investment' => 50000,
                'description' => 'Health insurance plan with monthly premium payments.',
                'benefits' => 'Health coverage, hospitalization benefits, annual checkups',
                'is_active' => true,
                'valid_from' => Carbon::now()->subMonths(6),
                'valid_to' => Carbon::now()->addYears(3),
            ],
            [
                'policy_number' => 'POL-INVEST-001',
                'policy_name' => 'Wealth Builder',
                'term_plan' => '20 Years',
                'rate_of_interest' => 9.1,
                'investment_type' => 0, // Daily
                'min_investment' => 500,
                'max_investment' => 200000,
                'description' => 'Long-term wealth creation plan with daily investment flexibility.',
                'benefits' => 'High returns, flexible payments, loan facility',
                'is_active' => true,
                'valid_from' => Carbon::now()->subYear(),
                'valid_to' => Carbon::now()->addYears(10),
            ],
            [
                'policy_number' => 'POL-RETIRE-001',
                'policy_name' => 'Retirement Plus',
                'term_plan' => '25 Years',
                'rate_of_interest' => 8.8,
                'investment_type' => 1, // Monthly
                'min_investment' => 3000,
                'max_investment' => 150000,
                'description' => 'Retirement planning policy with monthly contributions.',
                'benefits' => 'Pension, life coverage, tax benefits',
                'is_active' => true,
                'valid_from' => Carbon::now(),
                'valid_to' => Carbon::now()->addYears(7),
            ],
            [
                'policy_number' => 'POL-CHILD-001',
                'policy_name' => 'Child Future Plan',
                'term_plan' => '18 Years',
                'rate_of_interest' => 7.9,
                'investment_type' => 0, // Daily
                'min_investment' => 1000,
                'max_investment' => 100000,
                'description' => 'Education and future planning for children with daily savings.',
                'benefits' => 'Education fund, insurance coverage, maturity benefits',
                'is_active' => true,
                'valid_from' => Carbon::now()->subMonths(3),
                'valid_to' => Carbon::now()->addYears(15),
            ]
        ];

        foreach ($policies as $policyData) {
            Policy::create($policyData);
        }

        $this->command->info('Sample policies seeded successfully!');
    }
}