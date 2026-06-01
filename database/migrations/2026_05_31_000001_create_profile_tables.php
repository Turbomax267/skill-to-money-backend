<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freelancer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('dni', 20)->nullable()->index();
            $table->string('experience_area', 150);
            $table->text('bio')->nullable();
            $table->string('profile_photo', 255)->nullable();
            $table->string('location', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('website', 255)->nullable();
            $table->json('social_links')->nullable();
            $table->string('availability_status', 50)->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('completed_jobs')->default(0);
            $table->decimal('visibility_score', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('mype_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name', 150);
            $table->string('ruc', 20)->nullable();
            $table->string('industry', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('website', 255)->nullable();
            $table->string('location', 150)->nullable();
            $table->string('profile_photo', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mype_profiles');
        Schema::dropIfExists('freelancer_profiles');
    }
};
