<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersCollection extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            // MongoDB automatically creates _id field
            $table->string('name');
            $table->string('phone_number');
            $table->string('address');
            $table->string('pan')->unique(); // This already creates an index
            $table->string('policy_number')->unique(); // This already creates an index
            $table->string('policy_type');
            $table->timestamps(); // created_at and updated_at
            
            // Only create index for policy_type since pan and policy_number already have unique indexes
            $table->index('policy_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
}