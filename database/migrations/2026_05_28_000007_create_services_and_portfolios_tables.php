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
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->decimal('price', 12, 2);
            $table->string('currency', 10)->default('PEN');
            $table->unsignedInteger('delivery_days');
            $table->string('status', 30)->default('draft')->index();
            $table->json('tags')->nullable();
            $table->text('requirements')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('freelancer_id');
            $table->index('category_id');
        });

        Schema::create('portfolios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('freelancer_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('portfolio_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->integer('project_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('portfolio_id');
            $table->index('category_id');
            $table->index('project_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_projects');
        Schema::dropIfExists('portfolios');
        Schema::dropIfExists('services');
    }
};

