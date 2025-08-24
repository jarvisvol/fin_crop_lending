<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\PolicySubscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PortfolioController extends Controller
{
    /**
     * Get customer portfolio summary
     */
    public function getPortfolio(Request $request): JsonResponse
    {
        try {
            $customerId = auth()->id();

            $portfolio = Portfolio::where('customer_id', $customerId)
                ->orderBy('maturity_date')
                ->get();

            // Calculate totals
            $totalInvestment = $portfolio->sum('investment_amount');
            $totalCurrentValue = $portfolio->sum('current_value');
            $totalGain = $portfolio->sum('total_gain');
            $totalInterest = $portfolio->sum('interest_earned');

            $activePolicies = $portfolio->where('status', 'active')->count();
            $maturedPolicies = $portfolio->where('status', 'matured')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_investment' => $totalInvestment,
                        'total_current_value' => $totalCurrentValue,
                        'total_gain' => $totalGain,
                        'total_interest_earned' => $totalInterest,
                        'active_policies' => $activePolicies,
                        'matured_policies' => $maturedPolicies,
                        'overall_return' => $totalInvestment > 0 ? 
                            round(($totalGain / $totalInvestment) * 100, 2) : 0
                    ],
                    'breakdown' => [
                        'by_type' => $portfolio->groupBy('policy_type')->map(function ($group) {
                            return [
                                'count' => $group->count(),
                                'investment' => $group->sum('investment_amount'),
                                'current_value' => $group->sum('current_value'),
                                'gain' => $group->sum('total_gain')
                            ];
                        }),
                        'by_duration' => $portfolio->groupBy('duration')->map(function ($group) {
                            return [
                                'count' => $group->count(),
                                'investment' => $group->sum('investment_amount'),
                                'current_value' => $group->sum('current_value')
                            ];
                        })
                    ],
                    'policies' => $portfolio->map(function ($item) {
                        return $this->transformPortfolioItem($item);
                    })
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
     * Get portfolio performance over time
     */
    public function getPerformance(Request $request): JsonResponse
    {
        try {
            $customerId = auth()->id();
            $months = $request->get('months', 12);

            $performance = [];
            $currentDate = now();
            
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = $currentDate->copy()->subMonths($i);
                $monthName = $date->format('M Y');
                
                // Simulate portfolio growth (in real app, you'd use historical data)
                $portfolio = Portfolio::where('customer_id', $customerId)
                    ->where('start_date', '<=', $date)
                    ->get();

                $investment = $portfolio->sum('investment_amount');
                $value = $portfolio->sum(function ($item) use ($date) {
                    return $item->calculateValueAtDate($date);
                });

                $performance[] = [
                    'month' => $monthName,
                    'investment' => $investment,
                    'value' => $value,
                    'gain' => $value - $investment
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
     * Get upcoming maturities
     */
    public function getUpcomingMaturities(Request $request): JsonResponse
    {
        try {
            $customerId = auth()->id();
            $limit = $request->get('limit', 5);

            $upcoming = Portfolio::where('customer_id', $customerId)
                ->where('status', 'active')
                ->where('maturity_date', '>=', now())
                ->orderBy('maturity_date')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'policy_name' => $item->policy_name,
                        'policy_number' => $item->policy_number,
                        'maturity_date' => $item->maturity_date->format('d M Y'),
                        'days_remaining' => $item->getDaysRemaining(),
                        'investment_amount' => $item->investment_amount,
                        'expected_value' => $item->current_value,
                        'expected_gain' => $item->total_gain
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
    public function getPolicyDetails(string $policyId): JsonResponse
    {
        try {
            $customerId = auth()->id();

            $portfolio = Portfolio::where('customer_id', $customerId)
                ->where('policy_subscription_id', $policyId)
                ->first();

            if (!$portfolio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy not found in portfolio'
                ], 404);
            }

            // Calculate projected values
            $projections = $this->calculateProjections($portfolio);

            return response()->json([
                'success' => true,
                'data' => [
                    'portfolio' => $this->transformPortfolioItem($portfolio),
                    'projections' => $projections,
                    'performance_metrics' => [
                        'annualized_return' => $this->calculateAnnualizedReturn($portfolio),
                        'current_yield' => $portfolio->investment_amount > 0 ? 
                            ($portfolio->interest_earned / $portfolio->investment_amount) * 100 : 0,
                        'days_to_maturity' => $portfolio->getDaysRemaining(),
                        'progress_percentage' => $portfolio->getProgressPercentage()
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
     * Sync portfolio with latest subscriptions
     */
    public function syncPortfolio(): JsonResponse
    {
        try {
            $customerId = auth()->id();
            $subscriptions = PolicySubscription::where('customer_id', $customerId)->get();

            foreach ($subscriptions as $subscription) {
                $portfolio = Portfolio::updateOrCreate(
                    [
                        'customer_id' => $customerId,
                        'policy_subscription_id' => $subscription->subscription_id
                    ],
                    [
                        'policy_id' => $subscription->policy_id,
                        'policy_number' => $subscription->policy->policy_number,
                        'policy_name' => $subscription->policy->name,
                        'policy_type' => $subscription->policy->type,
                        'investment_amount' => $subscription->investment_amount,
                        'current_value' => $subscription->expected_maturity_amount,
                        'total_gain' => $subscription->expected_maturity_amount - $subscription->investment_amount,
                        'interest_earned' => $subscription->expected_maturity_amount - $subscription->investment_amount,
                        'interest_rate' => $subscription->policy->interest_rate,
                        'start_date' => $subscription->start_date,
                        'maturity_date' => $subscription->maturity_date,
                        'duration' => $subscription->policy->duration,
                        'status' => $subscription->status === 'active' ? 'active' : 'matured',
                        'last_updated' => now()
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Portfolio synced successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync portfolio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate projected values for a policy
     */
    private function calculateProjections($portfolio): array
    {
        $projections = [];
        $currentDate = now();
        $maturityDate = $portfolio->maturity_date;
        
        $months = $currentDate->diffInMonths($maturityDate);
        $monthlyRate = $portfolio->interest_rate / 12 / 100;

        for ($i = 0; $i <= min($months, 12); $i++) {
            $projectDate = $currentDate->copy()->addMonths($i);
            $elapsedMonths = $portfolio->start_date->diffInMonths($projectDate);
            $totalMonths = $portfolio->duration * 12;

            if ($projectDate >= $maturityDate) {
                $value = $portfolio->investment_amount * pow(1 + ($portfolio->interest_rate / 100), $portfolio->duration);
            } else {
                $value = $portfolio->investment_amount * pow(1 + $monthlyRate, $elapsedMonths);
            }

            $projections[] = [
                'date' => $projectDate->format('M Y'),
                'projected_value' => round($value, 2),
                'projected_gain' => round($value - $portfolio->investment_amount, 2)
            ];
        }

        return $projections;
    }

    /**
     * Calculate annualized return
     */
    private function calculateAnnualizedReturn($portfolio): float
    {
        $years = $portfolio->start_date->diffInYears(now());
        if ($years === 0) return 0;

        $totalReturn = ($portfolio->current_value / $portfolio->investment_amount) - 1;
        return pow(1 + $totalReturn, 1 / $years) - 1;
    }

    /**
     * Transform portfolio item for response
     */
    private function transformPortfolioItem($item): array
    {
        return [
            'id' => $item->id,
            'policy_subscription_id' => $item->policy_subscription_id,
            'policy_number' => $item->policy_number,
            'policy_name' => $item->policy_name,
            'policy_type' => $item->policy_type,
            'investment_amount' => $item->investment_amount,
            'current_value' => $item->current_value,
            'total_gain' => $item->total_gain,
            'interest_earned' => $item->interest_earned,
            'interest_rate' => $item->interest_rate,
            'start_date' => $item->start_date->format('d M Y'),
            'maturity_date' => $item->maturity_date->format('d M Y'),
            'duration' => $item->duration,
            'status' => $item->status,
            'days_remaining' => $item->getDaysRemaining(),
            'progress_percentage' => $item->getProgressPercentage(),
            'is_matured' => $item->isMatured(),
            'last_updated' => $item->last_updated->format('d M Y H:i')
        ];
    }
}