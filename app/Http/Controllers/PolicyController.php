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
            $policy = Policy::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $policy
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Policy not found',
                'error' => $e->getMessage()
            ], 404);
        }
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
            $maturityAmount = $policy->calculateMaturityAmount($request->investment_amount);

            return response()->json([
                'success' => true,
                'data' => [
                    'policy' => $policy->only(['name', 'type', 'duration', 'interest_rate']),
                    'investment_amount' => $request->investment_amount,
                    'maturity_amount' => $maturityAmount,
                    'total_interest' => $maturityAmount - $request->investment_amount
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
            $query = PolicySubscription::where('customer_id', auth()->id())
                ->with('policy');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $subscriptions = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

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
            $subscription = PolicySubscription::where('subscription_id', $id)
                ->where('customer_id', auth()->id())
                ->firstOrFail();

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