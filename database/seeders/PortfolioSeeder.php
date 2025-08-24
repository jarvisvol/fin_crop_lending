<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Portfolio;
use App\Models\Customer;
use App\Models\PolicySubscription;
use Carbon\Carbon;

class PortfolioSeeder extends Seeder
{
    public function run()
    {
        $customers = Customer::all();

        foreach ($customers as $customer) {
            $subscriptions = PolicySubscription::where('customer_id', $customer->id)
                ->where('status', 'active')
                ->get();

            foreach ($subscriptions as $subscription) {
                $policy = $subscription->policy;
                
                $currentValue = $subscription->investment_amount * 
                    pow(1 + ($policy->interest_rate / 100), 
                    $policy->duration * (min(1, $subscription->start_date->diffInYears(now()))));

                Portfolio::create([
                    'customer_id' => $customer->id,
                    'policy_subscription_id' => $subscription->subscription_id,
                    'policy_id' => $policy->id,
                    'policy_number' => $policy->policy_number,
                    'policy_name' => $policy->name,
                    'policy_type' => $policy->type,
                    'investment_amount' => $subscription->investment_amount,
                    'current_value' => round($currentValue, 2),
                    'total_gain' => round($currentValue - $subscription->investment_amount, 2),
                    'interest_earned' => round($currentValue - $subscription->investment_amount, 2),
                    'interest_rate' => $policy->interest_rate,
                    'start_date' => $subscription->start_date,
                    'maturity_date' => $subscription->maturity_date,
                    'duration' => $policy->duration,
                    'status' => 'active',
                    'last_updated' => now()
                ]);
            }
        }

        $this->command->info('Portfolio data seeded successfully!');
    }
}