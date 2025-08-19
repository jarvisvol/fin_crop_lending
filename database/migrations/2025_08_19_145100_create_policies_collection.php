<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->string('policy_number')->unique();
            $table->string('policy_name');
            $table->string('term_plan'); // e.g., "5 Years", "10 Years", "15 Years"
            $table->decimal('rate_of_interest', 5, 2); // 5 digits, 2 decimal places
            $table->boolean('investment_type')->default(0); // 0 = daily, 1 = monthly
            $table->decimal('min_investment', 10, 2)->nullable(); // Minimum investment amount
            $table->decimal('max_investment', 10, 2)->nullable(); // Maximum investment amount
            $table->text('description')->nullable();
            $table->text('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();
            
            // Create indexes
            $table->index('policy_number');
            $table->index('is_active');
            $table->index('investment_type');
            $table->index('valid_from');
            $table->index('valid_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};