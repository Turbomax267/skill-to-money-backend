<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 30)->nullable();
            $table->string('profile_photo_path', 500)->nullable();
            $table->text('personal_description')->nullable();
            $table->string('location', 120)->nullable();
            $table->json('social_links')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('freelancer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('professional_title', 140)->nullable();
            $table->string('experience_level', 50)->nullable();
            $table->string('availability_status', 30)->default('available')->index();
            $table->decimal('visibility_score', 8, 2)->nullable();
            $table->decimal('rating', 4, 2)->nullable();
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->timestamps();
        });

        Schema::create('mype_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name', 140);
            $table->string('ruc', 20)->nullable();
            $table->string('industry', 120)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('website', 500)->nullable();
            $table->text('business_description')->nullable();
            $table->decimal('rating', 4, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mype_profiles');
        Schema::dropIfExists('freelancer_profiles');
        Schema::dropIfExists('profiles');
    }
};

