<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mype_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mype_id', 'freelancer_id']);
            $table->index('mype_id');
            $table->index('freelancer_id');
        });

        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mype_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->unsignedInteger('expected_delivery_days')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->timestamps();

            $table->index('mype_id');
            $table->index('category_id');
        });

        Schema::create('matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mype_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('match_score', 8, 2)->nullable();
            $table->json('match_reason')->nullable();
            $table->string('status', 30)->default('suggested')->index();
            $table->timestamps();

            $table->index('mype_id');
            $table->index('freelancer_id');
            $table->index('service_request_id');
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('favorites');
    }
};

