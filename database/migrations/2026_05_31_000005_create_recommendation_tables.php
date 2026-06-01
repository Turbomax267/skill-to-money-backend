<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('recommendation_type', 50)->index();
            $table->string('title', 150);
            $table->text('description');
            $table->decimal('score', 5, 2)->nullable();
            $table->json('data')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('market_trends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->decimal('demand_score', 5, 2)->nullable();
            $table->decimal('average_price', 10, 2)->nullable();
            $table->string('currency', 10)->default('PEN');
            $table->string('source', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_trends');
        Schema::dropIfExists('recommendations');
    }
};
