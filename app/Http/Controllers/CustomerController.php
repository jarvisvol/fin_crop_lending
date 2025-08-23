<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer|min:1|max:100',
                'search' => 'nullable|string|min:2',
                'kyc_status' => 'nullable|in:pending,verified,rejected',
                'is_active' => 'nullable|boolean',
                'policy_type' => 'nullable|in:monthly,daily,digital_gold'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->get('per_page', 10);
            $query = Customer::query();

            // Search functionality
            if ($request->has('search')) {
                $searchTerm = $request->get('search');
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('email', 'like', "%{$searchTerm}%")
                      ->orWhere('phone_number', 'like', "%{$searchTerm}%")
                      ->orWhere('policy_number', 'like', "%{$searchTerm}%")
                      ->orWhere('pan_number', 'like', "%{$searchTerm}%");
                });
            }

            // Filter by KYC status
            if ($request->has('kyc_status')) {
                $query->where('kyc_status', $request->kyc_status);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Filter by policy type (if you have this field)
            if ($request->has('policy_type')) {
                $query->where('policy_type', $request->policy_type);
            }

            $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Transform the data to include masked sensitive information
            $customers->getCollection()->transform(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone_number' => $customer->phone_number,
                    'policy_number' => $customer->policy_number,
                    'policy_type' => $customer->policy_type,
                    'kyc_status' => $customer->kyc_status,
                    'kyc_verified_at' => $customer->kyc_verified_at,
                    'is_active' => $customer->is_active,
                    'created_at' => $customer->created_at,
                    'masked_pan' => $customer->masked_pan,
                    'masked_aadhaar' => $customer->masked_aadhaar,
                    'masked_bank_account' => $customer->masked_bank_account,
                    'bank_name' => $customer->bank_name,
                    'city' => $customer->city,
                    'state' => $customer->state
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $customers->items(),
                'pagination' => [
                    'current_page' => $customers->currentPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'last_page' => $customers->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:customers,email',
                'phone_number' => 'required|string|max:15',
                'date_of_birth' => 'required|date|before:today',
                'pan_number' => 'required|string|size:10|unique:customers,pan_number',
                'aadhaar_number' => 'required|string|max:12|unique:customers,aadhaar_number',
                'bank_account_number' => 'required|string|max:20|unique:customers,bank_account_number',
                'bank_name' => 'required|string|max:100',
                'bank_ifsc' => 'required|string|max:20',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'pincode' => 'required|string|max:10',
                'policy_number' => 'required|string|unique:customers,policy_number',
                'policy_type' => 'required|string|in:monthly,daily,digital_gold',
                'password' => 'required|string|min:8|confirmed',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $customerData = $validator->validated();
            $customerData['password'] = Hash::make($customerData['password']);
            $customerData['kyc_status'] = 'pending'; // Default KYC status

            $customer = Customer::create($customerData);

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $this->transformCustomer($customer)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Get customer documents
            $documents = Document::where('customer_id', $id)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => $this->transformCustomer($customer),
                    'documents' => $documents->map(function ($doc) {
                        return [
                            'id' => $doc->id,
                            'document_type' => $doc->document_type,
                            'document_type_name' => $doc->document_type_name,
                            'document_number' => $doc->document_number,
                            'file_name' => $doc->file_name,
                            'verification_status' => $doc->verification_status,
                            'verified_at' => $doc->verified_at,
                            'rejection_reason' => $doc->rejection_reason,
                            'created_at' => $doc->created_at
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:customers,email,' . $id,
                'phone_number' => 'sometimes|string|max:15',
                'date_of_birth' => 'sometimes|date|before:today',
                'pan_number' => 'sometimes|string|size:10|unique:customers,pan_number,' . $id,
                'aadhaar_number' => 'sometimes|string|max:12|unique:customers,aadhaar_number,' . $id,
                'bank_account_number' => 'sometimes|string|max:20|unique:customers,bank_account_number,' . $id,
                'bank_name' => 'sometimes|string|max:100',
                'bank_ifsc' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'pincode' => 'sometimes|string|max:10',
                'policy_number' => 'sometimes|string|unique:customers,policy_number,' . $id,
                'policy_type' => 'sometimes|string|in:monthly,daily,digital_gold',
                'kyc_status' => 'sometimes|in:pending,verified,rejected',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $customer->update($validator->validated());

            // Update KYC verified timestamp if status changed to verified
            if ($request->has('kyc_status') && $request->kyc_status === 'verified') {
                $customer->update(['kyc_verified_at' => now()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $this->transformCustomer($customer->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Delete associated documents first
            Document::where('customer_id', $id)->delete();

            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer and associated documents deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer KYC status
     */
    public function updateKycStatus(Request $request, string $id): JsonResponse
    {
        try {
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'kyc_status' => 'required|in:pending,verified,rejected',
                'rejection_reason' => 'required_if:kyc_status,rejected|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = ['kyc_status' => $request->kyc_status];
            
            if ($request->kyc_status === 'verified') {
                $updateData['kyc_verified_at'] = now();
            }

            $customer->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'KYC status updated successfully',
                'data' => $this->transformCustomer($customer->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update KYC status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $totalCustomers = Customer::count();
            $activeCustomers = Customer::where('is_active', true)->count();
            $kycVerified = Customer::where('kyc_status', 'verified')->count();
            $kycPending = Customer::where('kyc_status', 'pending')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_customers' => $totalCustomers,
                    'active_customers' => $activeCustomers,
                    'kyc_verified' => $kycVerified,
                    'kyc_pending' => $kycPending,
                    'kyc_rejected' => $totalCustomers - $kycVerified - $kycPending
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transform customer data for response (mask sensitive information)
     */
    private function transformCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone_number' => $customer->phone_number,
            'date_of_birth' => $customer->date_of_birth?->format('d/m/Y'),
            'age' => $customer->age,
            'pan_number' => $customer->masked_pan,
            'aadhaar_number' => $customer->masked_aadhaar,
            'bank_account_number' => $customer->masked_bank_account,
            'bank_name' => $customer->bank_name,
            'bank_ifsc' => $customer->bank_ifsc,
            'address' => $customer->address,
            'city' => $customer->city,
            'state' => $customer->state,
            'pincode' => $customer->pincode,
            'full_address' => $customer->full_address,
            'policy_number' => $customer->policy_number,
            'policy_type' => $customer->policy_type,
            'kyc_status' => $customer->kyc_status,
            'kyc_verified_at' => $customer->kyc_verified_at?->format('d/m/Y'),
            'is_kyc_verified' => $customer->isKycVerified(),
            'is_active' => $customer->is_active,
            'last_login_at' => $customer->last_login_at?->format('d/m/Y H:i'),
            'created_at' => $customer->created_at->format('d/m/Y'),
            'updated_at' => $customer->updated_at->format('d/m/Y')
        ];
    }
}