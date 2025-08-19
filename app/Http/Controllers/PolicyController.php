<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PolicyController extends Controller
{
    /**
     * Display a listing of policies with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'investment_type' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
                'search' => 'nullable|string|min:2',
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

            // Filter by investment type
            if ($request->has('investment_type')) {
                $query->where('investment_type', $request->investment_type);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Search functionality
            if ($request->has('search')) {
                $searchTerm = $request->get('search');
                $query->where(function($q) use ($searchTerm) {
                    $q->where('policy_number', 'like', "%{$searchTerm}%")
                      ->orWhere('policy_name', 'like', "%{$searchTerm}%")
                      ->orWhere('term_plan', 'like', "%{$searchTerm}%");
                });
            }

            // Get only valid policies if requested
            if ($request->get('valid_only', false)) {
                $query->valid();
            }

            $policies = $query->orderBy('policy_name')->paginate($perPage);

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

    /**
     * Store a newly created policy.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'policy_number' => 'required|string|unique:policies,policy_number',
                'policy_name' => 'required|string|max:255',
                'term_plan' => 'required|string|max:50',
                'rate_of_interest' => 'required|numeric|min:0|max:100',
                'investment_type' => 'required|boolean',
                'min_investment' => 'nullable|numeric|min:0',
                'max_investment' => 'nullable|numeric|min:0|gt:min_investment',
                'description' => 'nullable|string',
                'benefits' => 'nullable|string',
                'is_active' => 'boolean',
                'valid_from' => 'nullable|date',
                'valid_to' => 'nullable|date|after_or_equal:valid_from'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $policy = Policy::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Policy created successfully',
                'data' => $policy
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create policy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified policy.
     */
    public function show(string $id): JsonResponse
    {
        try {
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
     * Update the specified policy.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $policy = Policy::find($id);
            
            if (!$policy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'policy_number' => 'sometimes|string|unique:policies,policy_number,' . $id,
                'policy_name' => 'sometimes|string|max:255',
                'term_plan' => 'sometimes|string|max:50',
                'rate_of_interest' => 'sometimes|numeric|min:0|max:100',
                'investment_type' => 'sometimes|boolean',
                'min_investment' => 'nullable|numeric|min:0',
                'max_investment' => 'nullable|numeric|min:0|gt:min_investment',
                'description' => 'nullable|string',
                'benefits' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
                'valid_from' => 'nullable|date',
                'valid_to' => 'nullable|date|after_or_equal:valid_from'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $policy->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Policy updated successfully',
                'data' => $policy->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update policy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified policy.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $policy = Policy::find($id);
            
            if (!$policy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy not found'
                ], 404);
            }

            $policy->delete();

            return response()->json([
                'success' => true,
                'message' => 'Policy deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete policy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get policy statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $totalPolicies = Policy::count();
            $activePolicies = Policy::where('is_active', true)->count();
            $dailyPolicies = Policy::where('investment_type', false)->count();
            $monthlyPolicies = Policy::where('investment_type', true)->count();

            $averageInterest = Policy::where('is_active', true)->avg('rate_of_interest');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_policies' => $totalPolicies,
                    'active_policies' => $activePolicies,
                    'daily_investment_policies' => $dailyPolicies,
                    'monthly_investment_policies' => $monthlyPolicies,
                    'average_interest_rate' => round($averageInterest, 2),
                    'policy_distribution' => [
                        'daily' => $dailyPolicies,
                        'monthly' => $monthlyPolicies
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch policy statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}