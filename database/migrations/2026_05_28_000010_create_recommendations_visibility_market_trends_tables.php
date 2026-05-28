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
            $table->string('recommendation_type', 60)->index();
            $table->string('title', 180);
            $table->text('description');
            $table->decimal('score', 8, 2)->nullable();
            $table->json('data')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('visibility_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('profile_views')->default(0);
            $table->unsignedInteger('service_views')->default(0);
            $table->unsignedInteger('favorite_count')->default(0);
            $table->unsignedInteger('contact_count')->default(0);
            $table->decimal('visibility_score', 8, 2)->nullable();
            $table->text('analysis_notes')->nullable();
            $table->date('period_start')->nullable()->index();
            $table->date('period_end')->nullable()->index();
            $table->timestamps();

            $table->index('user_id');
            $table->index('service_id');
        });

        Schema::create('market_trends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->decimal('demand_score', 8, 2)->nullable();
            $table->decimal('average_price', 12, 2)->nullable();
            $table->string('currency', 10)->default('PEN');
            $table->string('source', 180)->nullable();
            $table->date('period_start')->nullable()->index();
            $table->date('period_end')->nullable()->index();
            $table->timestamps();

            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_trends');
        Schema::dropIfExists('visibility_analyses');
        Schema::dropIfExists('recommendations');
    }
};

