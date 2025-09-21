<?php

namespace App\Http\Controllers;

use App\Models\PolicySubscription;
use App\Models\Policy;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get dashboard essential data
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $customerId = auth()->id();
            
            // Get profile data
            $profile = $this->getProfileData($customerId);
            
            // Get current subscribed policies
            $currentPolicies = $this->getCurrentPolicies($customerId);
            
            // Get available policies
            $availablePolicies = $this->getAvailablePolicies();
            
            // Get portfolio value
            $portfolioValue = $this->getPortfolioValue($customerId);

            return response()->json([
                'success' => true,
                'data' => [
                    'profile' => $profile,
                    'current_policies' => $currentPolicies,
                    'available_policies' => $availablePolicies,
                    'portfolio_value' => $portfolioValue
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer profile data
     */
    private function getProfileData($customerId): array
    {
        $customer = Customer::find($customerId);
        
        return [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone_number' => $customer->phone_number,
            'kyc_status' => $customer->kyc_status,
            'kyc_verified_at' => $customer->kyc_verified_at?->format('d M Y'),
            'account_created' => $customer->created_at->format('d M Y'),
            'last_login' => $customer->last_login_at?->format('d M Y H:i'),
            'is_active' => $customer->is_active
        ];
    }

    /**
     * Get current subscribed policies
     */
    private function getCurrentPolicies($customerId): array
    {
        $subscriptions = PolicySubscription::where('customer_id', $customerId)
            ->with('policy')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        $policies = [];
        $totalInvestment = 0;
        $totalCurrentValue = 0;

        foreach ($subscriptions as $subscription) {
            $policy = $subscription->policy;
            $currentValue = $this->calculateCurrentValue($subscription);
            
            $totalInvestment += $subscription->investment_amount;
            $totalCurrentValue += $currentValue;

            $policies[] = [
                'subscription_id' => $subscription->subscription_id,
                'policy_id' => $policy->id,
                'policy_number' => $policy->policy_number,
                'policy_name' => $policy->name,
                'policy_type' => $policy->type,
                'investment_amount' => $subscription->investment_amount,
                'current_value' => $currentValue,
                'gain' => $currentValue - $subscription->investment_amount,
                'interest_rate' => $policy->interest_rate,
                'start_date' => $subscription->start_date->format('d M Y'),
                'maturity_date' => $subscription->maturity_date->format('d M Y'),
                'duration' => $policy->duration,
                'days_remaining' => now()->diffInDays($subscription->maturity_date),
                'progress_percentage' => $this->calculateProgress($subscription)
            ];
        }

        return [
            'policies' => $policies,
            'summary' => [
                'total_policies' => count($policies),
                'total_investment' => round($totalInvestment, 2),
                'total_current_value' => round($totalCurrentValue, 2),
                'total_gain' => round($totalCurrentValue - $totalInvestment, 2),
                'average_return' => $totalInvestment > 0 ? 
                    round((($totalCurrentValue - $totalInvestment) / $totalInvestment) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get available policies for investment
     */
    private function getAvailablePolicies(): array
    {
        $policies = Policy::where('is_active', true)
            ->orderBy('interest_rate', 'desc')
            ->get()
            ->map(function ($policy) {
                return [
                    'id' => $policy->id,
                    'policy_number' => $policy->policy_number,
                    'name' => $policy->name,
                    'type' => $policy->type,
                    'duration' => $policy->duration,
                    'interest_rate' => $policy->interest_rate,
                    'min_investment' => $policy->min_investment,
                    'max_investment' => $policy->max_investment,
                    'description' => $policy->description,
                    'is_active' => $policy->is_active
                ];
            });

        return [
            'total_available' => $policies->count(),
            'policies' => $policies,
            'by_type' => $policies->groupBy('type')->map(function ($group) {
                return $group->count();
            })
        ];
    }

    /**
     * Get portfolio value summary
     */
    private function getPortfolioValue($customerId): array
    {
        $subscriptions = PolicySubscription::where('customer_id', $customerId)
            ->with('policy')
            ->where('status', 'active')
            ->get();

        $totalInvestment = 0;
        $totalCurrentValue = 0;
        $byType = [];

        foreach ($subscriptions as $subscription) {
            $policy = $subscription->policy;
            $currentValue = $this->calculateCurrentValue($subscription);
            
            $totalInvestment += $subscription->investment_amount;
            $totalCurrentValue += $currentValue;

            // Group by policy type
            $type = $policy->type;
            if (!isset($byType[$type])) {
                $byType[$type] = [
                    'investment' => 0,
                    'current_value' => 0,
                    'count' => 0
                ];
            }
            
            $byType[$type]['investment'] += $subscription->investment_amount;
            $byType[$type]['current_value'] += $currentValue;
            $byType[$type]['count']++;
        }

        $totalGain = $totalCurrentValue - $totalInvestment;

        return [
            'total_investment' => round($totalInvestment, 2),
            'total_current_value' => round($totalCurrentValue, 2),
            'total_gain' => round($totalGain, 2),
            'total_return_percentage' => $totalInvestment > 0 ? 
                round(($totalGain / $totalInvestment) * 100, 2) : 0,
            'by_type' => $byType,
            'policy_count' => $subscriptions->count()
        ];
    }

    /**
     * Calculate current policy value
     */
    private function calculateCurrentValue($subscription): float
    {
        $policy = $subscription->policy;
        $investmentAmount = $subscription->investment_amount;
        $startDate = $subscription->start_date;
        $maturityDate = $subscription->maturity_date;
        $now = now();

        if ($now >= $maturityDate) {
            return $subscription->expected_maturity_amount;
        }

        // Calculate progress percentage
        $totalDays = $startDate->diffInDays($maturityDate);
        $elapsedDays = $startDate->diffInDays($now);
        $progress = min(1, $elapsedDays / $totalDays);

        // Calculate current value based on policy type
        $annualRate = $policy->interest_rate / 100;
        
        switch ($policy->type) {
            case 'daily':
                $dailyRate = $annualRate / 365;
                return $investmentAmount * pow(1 + $dailyRate, $elapsedDays);
            
            case 'monthly':
                $monthlyRate = $annualRate / 12;
                $elapsedMonths = $startDate->diffInMonths($now);
                return $investmentAmount * pow(1 + $monthlyRate, $elapsedMonths);
            
            case 'digital_gold':
            default:
                $elapsedYears = $startDate->diffInYears($now);
                return $investmentAmount * pow(1 + $annualRate, $elapsedYears);
        }
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress($subscription): float
    {
        $startDate = $subscription->start_date;
        $maturityDate = $subscription->maturity_date;
        $now = now();

        if ($now >= $maturityDate) {
            return 100;
        }

        $totalDays = $startDate->diffInDays($maturityDate);
        $elapsedDays = $startDate->diffInDays($now);

        return min(100, ($elapsedDays / $totalDays) * 100);
    }
}