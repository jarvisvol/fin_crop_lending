<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->string('policy_number')->unique(); // Randomly generated
            $table->string('name');
            $table->enum('type', ['monthly', 'daily', 'digital_gold']);
            $table->enum('duration', [1, 2, 3, 5]); // Years
            $table->decimal('interest_rate', 5, 2); // Interest rate percentage
            $table->decimal('min_investment', 12, 2)->default(0);
            $table->decimal('max_investment', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->text('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index('policy_number');
            $table->index('type');
            $table->index('duration');
            $table->index('is_active');
            $table->index(['type', 'duration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};