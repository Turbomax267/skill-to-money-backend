<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('PEN');
            $table->integer('delivery_days');
            $table->string('status', 30)->default('published')->index();
            $table->integer('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('portfolio_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('external_url', 255)->nullable();
            $table->integer('project_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mype_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mype_profile_id', 'freelancer_profile_id']);
        });

        Schema::create('matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mype_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('compatibility_score', 5, 2)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('suggested')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('portfolio_projects');
        Schema::dropIfExists('services');
    }
};
