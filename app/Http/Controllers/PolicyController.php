<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use App\Models\PolicySubscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'nullable|in:monthly,daily,digital_gold',
                'duration' => 'nullable|in:1,2,3,5',
                'min_interest' => 'nullable|numeric|min:0',
                'is_active' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->get('per_page', 10);
            $query = Policy::query();

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('duration')) {
                $query->where('duration', $request->duration);
            }

            if ($request->has('min_interest')) {
                $query->where('interest_rate', '>=', $request->min_interest);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            $policies = $query->orderBy('type')
                ->orderBy('duration')
                ->orderBy('interest_rate', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $policies->items(),
                'pagination' => [
                    'current_page' => $policies->currentPage(),
                    'per_page' => $policies->perPage(),
                    'total' => $policies->total(),
                    'last_page' => $policies->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch policies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            // For MongoDB, use find instead of findOrFail
            $policy = Policy::find($id);

            if (!$policy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $policy
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch policy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate lumpsum maturity amount (for digital_gold)
     */
    private function calculateLumpsumMaturityAmount($policy, $investmentAmount): float
    {
        $years = $policy->duration;
        $annualRate = $policy->interest_rate / 100;
        
        // Formula for lumpsum investment with annual compounding:
        // Maturity = Investment * (1 + annual_rate)^years
        return $investmentAmount * pow(1 + $annualRate, $years);
    }

    public function calculateMaturity(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'policy_id' => 'required|exists:policies,_id',
                'investment_amount' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $policy = Policy::findOrFail($request->policy_id);
            $amount = $request->investment_amount;

            if ($policy->type === 'daily') {
                $totalInvestment = $amount * $policy->duration * 365;
                $maturityAmount = $this->calculateDailyMaturityAmount($policy, $amount);
            } elseif ($policy->type === 'monthly') {
                $totalInvestment = $amount * $policy->duration * 12;
                $maturityAmount = $this->calculateMonthlyMaturityAmount($policy, $amount);
            } else {
                // digital_gold - lumpsum
                $totalInvestment = $amount;
                $maturityAmount = $this->calculateLumpsumMaturityAmount($policy, $amount);
            }

            $totalInterest = $maturityAmount - $totalInvestment;

            return response()->json([
                'success' => true,
                'data' => [
                    'policy' => [
                        'name' => $policy->name,
                        'type' => $policy->type,
                        'duration' => $policy->duration,
                        'interest_rate' => $policy->interest_rate
                    ],
                    'investment_amount' => round($totalInvestment, 2),
                    'periodic_investment' => $amount,
                    'maturity_amount' => round($maturityAmount, 2),
                    'total_interest' => round($totalInterest, 2),
                    'effective_return' => round(($totalInterest / $totalInvestment) * 100, 2),
                    'maturity_date' => now()->addYears($policy->duration)->format('Y-m-d')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Calculation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate total investment for daily payments
     */
    private function calculateTotalDailyInvestment($policy, $dailyAmount): float
    {
        $totalDays = $policy->duration * 365;
        return $dailyAmount * $totalDays;
    }

    /**
     * Calculate maturity amount for daily investments with daily compounding
     */
    private function calculateDailyMaturityAmount($policy, $dailyAmount): float
    {
        $years = $policy->duration;
        $annualRate = $policy->interest_rate / 100;
        $dailyRate = $annualRate / 365;
        $totalDays = $years * 365;
        
        // Formula for daily investments with daily compounding:
        // Maturity = Daily_Amount * [((1 + daily_rate)^total_days - 1) / daily_rate]
        $maturityAmount = $dailyAmount * ((pow(1 + $dailyRate, $totalDays) - 1) / $dailyRate);
        
        return $maturityAmount;
    }

    /**
     * Calculate monthly maturity amount (if needed for monthly policies)
     */
    private function calculateMonthlyMaturityAmount($policy, $monthlyAmount): float
    {
        $years = $policy->duration;
        $annualRate = $policy->interest_rate / 100;
        $monthlyRate = $annualRate / 12;
        $totalMonths = $years * 12;
        
        // Formula for monthly investments with monthly compounding:
        // Maturity = Monthly_Amount * [((1 + monthly_rate)^total_months - 1) / monthly_rate]
        $maturityAmount = $monthlyAmount * ((pow(1 + $monthlyRate, $totalMonths) - 1) / $monthlyRate);
        
        return $maturityAmount;
    }

    public function subscribe(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'policy_id' => 'required|exists:policies,_id',
                'investment_amount' => 'required|numeric|min:0',
                'start_date' => 'nullable|date|after_or_equal:today'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $policy = Policy::findOrFail($request->policy_id);

            // Check investment amount limits
            if ($request->investment_amount < $policy->min_investment) {
                return response()->json([
                    'success' => false,
                    'message' => "Minimum investment amount is {$policy->min_investment}"
                ], 422);
            }

            if ($policy->max_investment && $request->investment_amount > $policy->max_investment) {
                return response()->json([
                    'success' => false,
                    'message' => "Maximum investment amount is {$policy->max_investment}"
                ], 422);
            }

            // Calculate maturity details
            $startDate = $request->start_date ?: now();
            $maturityDate = $startDate->copy()->addYears($policy->duration);
            $maturityAmount = $policy->calculateMaturityAmount($request->investment_amount);

            $subscription = PolicySubscription::create([
                'customer_id' => auth()->id(),
                'policy_id' => $policy->id,
                'investment_amount' => $request->investment_amount,
                'start_date' => $startDate,
                'maturity_date' => $maturityDate,
                'expected_maturity_amount' => $maturityAmount,
                'status' => 'active'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => $subscription
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSubscriptions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|in:active,matured,cancelled',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->get('per_page', 10);
            $customerId = auth()->id();

            // For MongoDB, we need to manually join or use aggregation
            $subscriptions = PolicySubscription::where('customer_id', $customerId)
                ->when($request->has('status'), function ($query) use ($request) {
                    return $query->where('status', $request->status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Manually load policy data for each subscription
            $subscriptions->getCollection()->transform(function ($subscription) {
                $policy = Policy::find($subscription->policy_id);
                
                return [
                    'subscription_id' => $subscription->subscription_id,
                    'investment_amount' => $subscription->investment_amount,
                    'start_date' => $subscription->start_date,
                    'maturity_date' => $subscription->maturity_date,
                    'expected_maturity_amount' => $subscription->expected_maturity_amount,
                    'status' => $subscription->status,
                    'created_at' => $subscription->created_at,
                    'policy' => $policy ? [
                        '_id' => $policy->_id,
                        'policy_number' => $policy->policy_number,
                        'name' => $policy->name,
                        'type' => $policy->type,
                        'duration' => $policy->duration,
                        'interest_rate' => $policy->interest_rate,
                        'description' => $policy->description
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $subscriptions->items(),
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                    'last_page' => $subscriptions->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStats(): JsonResponse
    {
        try {
            $totalSubscriptions = PolicySubscription::where('customer_id', auth()->id())->count();
            $activeSubscriptions = PolicySubscription::where('customer_id', auth()->id())
                ->where('status', 'active')->count();
            $totalInvestment = PolicySubscription::where('customer_id', auth()->id())
                ->where('status', 'active')
                ->sum('investment_amount');
            $expectedReturns = PolicySubscription::where('customer_id', auth()->id())
                ->where('status', 'active')
                ->sum('expected_maturity_amount') - $totalInvestment;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_subscriptions' => $totalSubscriptions,
                    'active_subscriptions' => $activeSubscriptions,
                    'total_investment' => $totalInvestment,
                    'expected_returns' => $expectedReturns,
                    'estimated_total' => $totalInvestment + $expectedReturns
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelSubscription(string $id): JsonResponse
    {
        try {
            // For MongoDB, use where instead of findOrFail
            $subscription = PolicySubscription::where('subscription_id', $id)
                ->where('customer_id', auth()->id())
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            if ($subscription->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is not active'
                ], 422);
            }

            $subscription->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}