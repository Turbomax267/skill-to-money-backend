<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 30)->default('pro');
            $table->string('status', 30)->default('active')->index();
            $table->string('billing_cycle', 30)->default('monthly');
            $table->decimal('amount', 10, 2)->default(29);
            $table->string('currency', 10)->default('PEN');
            $table->string('source', 50)->default('skillpay_demo');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan', 30)->default('pro');
            $table->decimal('amount', 10, 2)->default(29);
            $table->string('currency', 10)->default('PEN');
            $table->string('payment_method', 30);
            $table->string('provider', 50)->default('skillpay_demo');
            $table->string('provider_reference', 80)->unique();
            $table->string('card_brand', 30)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('status', 30)->default('succeeded')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
    }
};
