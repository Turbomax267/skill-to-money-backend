<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline', 140)->nullable();
            $table->string('category', 80)->nullable();
            $table->string('bio', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('location', 120)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->json('skills')->nullable();
            $table->json('social_links')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_profiles');
    }
};
