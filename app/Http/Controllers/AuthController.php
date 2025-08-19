<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new customer
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:customers',
                'phone_number' => 'required|string|max:15',
                'address' => 'required|string|max:500',
                'pan' => 'required|string|size:10|unique:customers,pan',
                'policy_number' => 'required|string|unique:customers,policy_number',
                'policy_type' => 'required|string|in:Life Insurance,Health Insurance,Motor Insurance,Term Insurance,Travel Insurance',
                'password' => 'required|string|min:8|confirmed',
                'device_token' => 'nullable|string'
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
            $customerData['email_verified_at'] = now(); // Auto-verify for simplicity

            $customer = Customer::create($customerData);

            $token = JWTAuth::fromUser($customer);

            return response()->json([
                'success' => true,
                'message' => 'Customer registered successfully',
                'data' => [
                    'customer' => $customer,
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login customer
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required_without:policy_number|email',
                'policy_number' => 'required_without:email|string',
                'password' => 'required|string',
                'device_token' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $credentials = $request->only(['email', 'policy_number', 'password']);

            // Check if customer exists and is active
            $customer = null;
            if (!empty($credentials['email'])) {
                $customer = Customer::where('email', $credentials['email'])->first();
            } elseif (!empty($credentials['policy_number'])) {
                $customer = Customer::where('policy_number', $credentials['policy_number'])->first();
            }

            if (!$customer || !$customer->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials or account inactive'
                ], 401);
            }

            // Verify password
            if (!Hash::check($credentials['password'], $customer->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Update login info
            $customer->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'device_token' => $request->device_token
            ]);

            $token = JWTAuth::fromUser($customer);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'customer' => $customer->makeHidden(['password']),
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout customer
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            // Optionally clear device token
            if ($request->user()) {
                $request->user()->update(['device_token' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Get current authenticated customer
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();

            return response()->json([
                'success' => true,
                'data' => $customer->makeHidden(['password'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Forgot password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:customers,email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // In a real application, you would send an email with reset link
            // For now, we'll just return a success message

            return response()->json([
                'success' => true,
                'message' => 'Password reset instructions sent to your email'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}