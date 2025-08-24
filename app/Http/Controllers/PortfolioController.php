<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\PolicySubscription;
use App\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PortfolioController extends Controller
{
    /**
     * Get customer portfolio summary
     */
    public function getPortfolio(Request $request): JsonResponse
    {
        try {
            $customerId = auth()->id();

            // Get all active subscriptions
            $subscriptions = PolicySubscription::where('customer_id', $customerId)
                ->with('policy')
                ->where('status', 'active')
                ->get();

            $portfolioData = [];
            $totalInvestment = 0;
            $totalCurrentValue = 0;
            $totalGain = 0;
            $totalInterest = 0;
            $activePolicies = 0;

            foreach ($subscriptions as $subscription) {
                $policy = $subscription->policy;
                $currentValue = $this->calculateCurrentPolicyValue($subscription);
                $totalInvestment += $subscription->investment_amount;
                $totalCurrentValue += $currentValue;
                $gain = $currentValue - $subscription->investment_amount;
                $totalGain += $gain;
                $totalInterest += $gain;
                $activePolicies++;

                $portfolioData[] = [
                    'subscription_id' => $subscription->subscription_id,
                    'policy_id' => $policy->id,
                    'policy_number' => $policy->policy_number,
                    'policy_name' => $policy->name,
                    'policy_type' => $policy->type,
                    'investment_amount' => $subscription->investment_amount,
                    'current_value' => $currentValue,
                    'total_gain' => $gain,
                    'interest_earned' => $gain,
                    'interest_rate' => $policy->interest_rate,
                    'start_date' => $subscription->start_date,
                    'maturity_date' => $subscription->maturity_date,
                    'duration' => $policy->duration,
                    'status' => 'active',
                    'days_remaining' => $subscription->start_date->diffInDays($subscription->maturity_date),
                    'progress_percentage' => $this->calculateProgressPercentage($subscription),
                    'is_matured' => now() >= $subscription->maturity_date
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_investment' => round($totalInvestment, 2),
                        'total_current_value' => round($totalCurrentValue, 2),
                        'total_gain' => round($totalGain, 2),
                        'total_interest_earned' => round($totalInterest, 2),
                        'active_policies' => $activePolicies,
                        'overall_return' => $totalInvestment > 0 ? 
                            round(($totalGain / $totalInvestment) * 100, 2) : 0
                    ],
                    'breakdown' => [
                        'by_type' => collect($portfolioData)->groupBy('policy_type')->map(function ($group) {
                            return [
                                'count' => $group->count(),
                                'investment' => $group->sum('investment_amount'),
                                'current_value' => $group->sum('current_value'),
                                'gain' => $group->sum('total_gain')
                            ];
                        }),
                        'by_duration' => collect($portfolioData)->groupBy('duration')->map(function ($group) {
                            return [
                                'count' => $group->count(),
                                'investment' => $group->sum('investment_amount'),
                                'current_value' => $group->sum('current_value')
                            ];
                        })
                    ],
                    'policies' => $portfolioData
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch portfolio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate current value of a policy based on its type
     */
    private function calculateCurrentPolicyValue($subscription): float
    {
        $policy = $subscription->policy;
        $investmentAmount = $subscription->investment_amount;
        $startDate = $subscription->start_date;
        $maturityDate = $subscription->maturity_date;
        $now = now();

        if ($now >= $maturityDate) {
            // Policy matured, return full maturity amount
            return $this->calculateMaturityAmount($policy, $investmentAmount);
        }

        // Calculate based on policy type
        switch ($policy->type) {
            case 'daily':
                return $this->calculateDailyCurrentValue($policy, $investmentAmount, $startDate, $maturityDate);
            
            case 'monthly':
                return $this->calculateMonthlyCurrentValue($policy, $investmentAmount, $startDate, $maturityDate);
            
            case 'digital_gold':
            default:
                return $this->calculateLumpsumCurrentValue($policy, $investmentAmount, $startDate, $maturityDate);
        }
    }

    /**
     * Calculate current value for daily investments
     */
    private function calculateDailyCurrentValue($policy, $investmentAmount, $startDate, $maturityDate): float
    {
        $now = now();
        $annualRate = $policy->interest_rate / 100;
        $dailyRate = $annualRate / 365;
        
        $totalDays = $startDate->diffInDays($maturityDate);
        $elapsedDays = $startDate->diffInDays($now);
        
        // For daily investments, the total investment is already the sum of all daily payments
        // Calculate partial interest based on elapsed days
        $partialInterest = $investmentAmount * (pow(1 + $dailyRate, $elapsedDays) - 1);
        
        return $investmentAmount + $partialInterest;
    }

    /**
     * Calculate current value for monthly investments
     */
    private function calculateMonthlyCurrentValue($policy, $investmentAmount, $startDate, $maturityDate): float
    {
        $now = now();
        $annualRate = $policy->interest_rate / 100;
        $monthlyRate = $annualRate / 12;
        
        $totalMonths = $startDate->diffInMonths($maturityDate);
        $elapsedMonths = $startDate->diffInMonths($now);
        
        // For monthly investments, calculate partial interest
        $partialInterest = $investmentAmount * (pow(1 + $monthlyRate, $elapsedMonths) - 1);
        
        return $investmentAmount + $partialInterest;
    }

    /**
     * Calculate current value for lumpsum investments
     */
    private function calculateLumpsumCurrentValue($policy, $investmentAmount, $startDate, $maturityDate): float
    {
        $now = now();
        $annualRate = $policy->interest_rate / 100;
        
        $totalYears = $startDate->diffInYears($maturityDate);
        $elapsedYears = $startDate->diffInYears($now);
        
        // For lumpsum investments, use compound interest formula
        return $investmentAmount * pow(1 + $annualRate, $elapsedYears);
    }

    /**
     * Calculate maturity amount for a policy
     */
    private function calculateMaturityAmount($policy, $investmentAmount): float
    {
        $annualRate = $policy->interest_rate / 100;
        
        switch ($policy->type) {
            case 'daily':
                $dailyRate = $annualRate / 365;
                $totalDays = $policy->duration * 365;
                return $investmentAmount * pow(1 + $dailyRate, $totalDays);
            
            case 'monthly':
                $monthlyRate = $annualRate / 12;
                $totalMonths = $policy->duration * 12;
                return $investmentAmount * pow(1 + $monthlyRate, $totalMonths);
            
            case 'digital_gold':
            default:
                return $investmentAmount * pow(1 + $annualRate, $policy->duration);
        }
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgressPercentage($subscription): float
    {
        $startDate = $subscription->start_date;
        $maturityDate = $subscription->maturity_date;
        $now = now();
        
        $totalDays = $startDate->diffInDays($maturityDate);
        $elapsedDays = $startDate->diffInDays($now);
        
        return min(100, max(0, ($elapsedDays / $totalDays) * 100));
    }

    /**
     * Get portfolio performance over time
     */
    public function getPerformance(Request $request): JsonResponse
    {
        try {
            $customerId = auth()->id();
            $months = $request->get('months', 12);

            $subscriptions = PolicySubscription::where('customer_id', $customerId)
                ->with('policy')
                ->where('status', 'active')
                ->get();

            $performance = [];
            $currentDate = now();
            
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = $currentDate->copy()->subMonths($i);
                $monthName = $date->format('M Y');
                
                $monthInvestment = 0;
                $monthValue = 0;
                
                foreach ($subscriptions as $subscription) {
                    if ($subscription->start_date <= $date) {
                        $monthInvestment += $subscription->investment_amount;
                        $monthValue += $this->calculatePolicyValueAtDate($subscription, $date);
                    }
                }
                
                $performance[] = [
                    'month' => $monthName,
                    'investment' => round($monthInvestment, 2),
                    'value' => round($monthValue, 2),
                    'gain' => round($monthValue - $monthInvestment, 2)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $performance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch performance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate policy value at a specific date
     */
    private function calculatePolicyValueAtDate($subscription, $date): float
    {
        $policy = $subscription->policy;
        $investmentAmount = $subscription->investment_amount;
        $startDate = $subscription->start_date;
        $maturityDate = $subscription->maturity_date;

        if ($date >= $maturityDate) {
            return $this->calculateMaturityAmount($policy, $investmentAmount);
        }

        if ($date < $startDate) {
            return 0;
        }

        // Calculate based on policy type
        switch ($policy->type) {
            case 'daily':
                $annualRate = $policy->interest_rate / 100;
                $dailyRate = $annualRate / 365;
                $elapsedDays = $startDate->diffInDays($date);
                return $investmentAmount * pow(1 + $dailyRate, $elapsedDays);
            
            case 'monthly':
                $annualRate = $policy->interest_rate / 100;
                $monthlyRate = $annualRate / 12;
                $elapsedMonths = $startDate->diffInMonths($date);
                return $investmentAmount * pow(1 + $monthlyRate, $elapsedMonths);
            
            case 'digital_gold':
            default:
                $annualRate = $policy->interest_rate / 100;
                $elapsedYears = $startDate->diffInYears($date);
                return $investmentAmount * pow(1 + $annualRate, $elapsedYears);
        }
    }

    /**
     * Get upcoming maturities
     */
    public function getUpcomingMaturities(Request $request): JsonResponse
    {
        try {
            $customerId = auth()->id();
            $limit = $request->get('limit', 5);

            $subscriptions = PolicySubscription::where('customer_id', $customerId)
                ->with('policy')
                ->where('status', 'active')
                ->where('maturity_date', '>=', now())
                ->orderBy('maturity_date')
                ->limit($limit)
                ->get();

            $upcoming = $subscriptions->map(function ($subscription) {
                $currentValue = $this->calculateCurrentPolicyValue($subscription);
                $gain = $currentValue - $subscription->investment_amount;
                
                return [
                    'policy_name' => $subscription->policy->name,
                    'policy_number' => $subscription->policy->policy_number,
                    'policy_type' => $subscription->policy->type,
                    'maturity_date' => $subscription->maturity_date->format('d M Y'),
                    'days_remaining' => now()->diffInDays($subscription->maturity_date),
                    'investment_amount' => $subscription->investment_amount,
                    'current_value' => $currentValue,
                    'expected_gain' => $gain,
                    'interest_rate' => $subscription->policy->interest_rate
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $upcoming
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upcoming maturities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get portfolio details for a specific policy
     */
    public function getPolicyDetails(string $subscriptionId): JsonResponse
    {
        try {
            $customerId = auth()->id();

            $subscription = PolicySubscription::where('customer_id', $customerId)
                ->with('policy')
                ->where('subscription_id', $subscriptionId)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy subscription not found'
                ], 404);
            }

            $currentValue = $this->calculateCurrentPolicyValue($subscription);
            $gain = $currentValue - $subscription->investment_amount;

            $policyData = [
                'subscription_id' => $subscription->subscription_id,
                'policy_number' => $subscription->policy->policy_number,
                'policy_name' => $subscription->policy->name,
                'policy_type' => $subscription->policy->type,
                'investment_amount' => $subscription->investment_amount,
                'current_value' => $currentValue,
                'total_gain' => $gain,
                'interest_earned' => $gain,
                'interest_rate' => $subscription->policy->interest_rate,
                'start_date' => $subscription->start_date->format('d M Y'),
                'maturity_date' => $subscription->maturity_date->format('d M Y'),
                'duration' => $subscription->policy->duration,
                'days_remaining' => now()->diffInDays($subscription->maturity_date),
                'progress_percentage' => $this->calculateProgressPercentage($subscription),
                'is_matured' => now() >= $subscription->maturity_date
            ];

            // Calculate projections
            $projections = $this->calculateProjections($subscription);

            return response()->json([
                'success' => true,
                'data' => [
                    'policy' => $policyData,
                    'projections' => $projections,
                    'performance_metrics' => [
                        'annualized_return' => $this->calculateAnnualizedReturn($subscription),
                        'current_yield' => $subscription->investment_amount > 0 ? 
                            ($gain / $subscription->investment_amount) * 100 : 0,
                        'days_to_maturity' => now()->diffInDays($subscription->maturity_date),
                        'progress_percentage' => $this->calculateProgressPercentage($subscription)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch policy details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate projected values for a policy
     */
    private function calculateProjections($subscription): array
    {
        $projections = [];
        $policy = $subscription->policy;
        $currentDate = now();
        $maturityDate = $subscription->maturity_date;
        
        $months = $currentDate->diffInMonths($maturityDate);
        
        for ($i = 0; $i <= min($months, 12); $i++) {
            $projectDate = $currentDate->copy()->addMonths($i);
            $projectedValue = $this->calculatePolicyValueAtDate($subscription, $projectDate);
            
            $projections[] = [
                'date' => $projectDate->format('M Y'),
                'projected_value' => round($projectedValue, 2),
                'projected_gain' => round($projectedValue - $subscription->investment_amount, 2)
            ];
        }

        return $projections;
    }

    /**
     * Calculate annualized return
     */
    private function calculateAnnualizedReturn($subscription): float
    {
        $startDate = $subscription->start_date;
        $years = $startDate->diffInYears(now());
        if ($years === 0) return 0;

        $currentValue = $this->calculateCurrentPolicyValue($subscription);
        $totalReturn = ($currentValue / $subscription->investment_amount) - 1;
        
        return pow(1 + $totalReturn, 1 / $years) - 1;
    }

    /**
     * Sync portfolio with latest subscriptions
     */
    public function syncPortfolio(): JsonResponse
    {
        try {
            // This method is now redundant since we calculate everything real-time
            // But keeping it for backward compatibility
            return response()->json([
                'success' => true,
                'message' => 'Portfolio data is calculated real-time. No sync needed.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync portfolio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}