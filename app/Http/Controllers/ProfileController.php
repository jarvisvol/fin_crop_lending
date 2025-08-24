<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get customer profile
     */
    public function getProfile(): JsonResponse
    {
        try {
            $customer = auth()->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'personal_info' => [
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone_number' => $customer->phone_number,
                        'date_of_birth' => $customer->date_of_birth?->format('d/m/Y'),
                        'age' => $customer->age
                    ],
                    'financial_info' => [
                        'pan_number' => $customer->masked_pan,
                        'aadhaar_number' => $customer->masked_aadhaar,
                        'bank_account_number' => $customer->masked_bank_account,
                        'bank_name' => $customer->bank_name,
                        'bank_ifsc' => $customer->bank_ifsc
                    ],
                    'address_info' => [
                        'address' => $customer->address,
                        'city' => $customer->city,
                        'state' => $customer->state,
                        'pincode' => $customer->pincode,
                        'full_address' => $customer->full_address
                    ],
                    'kyc_status' => [
                        'status' => $customer->kyc_status,
                        'verified_at' => $customer->kyc_verified_at?->format('d/m/Y'),
                        'is_verified' => $customer->isKycVerified()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $customer = auth()->user();

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'phone_number' => 'sometimes|string|max:15',
                'date_of_birth' => 'sometimes|date|before:today',
                'address' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'pincode' => 'sometimes|string|max:10',
                'bank_name' => 'sometimes|string|max:100',
                'bank_ifsc' => 'sometimes|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $customer->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $customer->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload KYC document
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'document_type' => 'required|in:aadhaar,pan,bank_passbook,photo,signature',
                'document_number' => 'nullable|string|max:50',
                'document' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $customer = auth()->user();
            $file = $request->file('document');
            $documentType = $request->document_type;

            // Generate unique filename
            $filename = uniqid() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('documents/' . $customer->id, $filename, 'public');

            $document = Document::create([
                'customer_id' => $customer->id,
                'document_type' => $documentType,
                'document_number' => $request->document_number,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'verification_status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $document
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer documents
     */
    public function getDocuments(): JsonResponse
    {
        try {
            $customer = auth()->user();
            $documents = Document::where('customer_id', $customer->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'document_type_name' => $doc->document_type_name,
                        'document_number' => $doc->document_number,
                        'file_name' => $doc->file_name,
                        'file_size' => $doc->file_size,
                        'verification_status' => $doc->verification_status,
                        'verified_at' => $doc->verified_at?->format('d/m/Y'),
                        'rejection_reason' => $doc->rejection_reason,
                        'download_url' => Storage::url($doc->file_path),
                        'uploaded_at' => $doc->created_at->format('d/m/Y H:i')
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get KYC status
     */
    public function getKycStatus(): JsonResponse
    {
        try {
            $customer = auth()->user();
            $documents = Document::where('customer_id', $customer->id)->get();

            $documentStatus = [];
            foreach (Document::getDocumentTypes() as $type => $name) {
                $doc = $documents->where('document_type', $type)->first();
                $documentStatus[] = [
                    'type' => $type,
                    'name' => $name,
                    'status' => $doc ? $doc->verification_status : 'missing',
                    'uploaded' => (bool)$doc
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_status' => $customer->kyc_status,
                    'verified_at' => $customer->kyc_verified_at?->format('d/m/Y'),
                    'documents' => $documentStatus,
                    'is_complete' => $customer->isKycVerified()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch KYC status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}