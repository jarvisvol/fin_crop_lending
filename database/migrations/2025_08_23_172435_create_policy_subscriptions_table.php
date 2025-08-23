<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_subscriptions', function (Blueprint $table) {
            $table->string('subscription_id')->unique(); // Random ID
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('policy_id')->constrained('policies');
            $table->decimal('investment_amount', 12, 2);
            $table->date('start_date');
            $table->date('maturity_date');
            $table->decimal('expected_maturity_amount', 12, 2);
            $table->enum('status', ['active', 'matured', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('subscription_id');
            $table->index('customer_id');
            $table->index('policy_id');
            $table->index('status');
            $table->index(['customer_id', 'status']);
            $table->index('maturity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_subscriptions');
    }
};