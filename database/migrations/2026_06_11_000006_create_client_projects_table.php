<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mype_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('category', 120)->nullable();
            $table->text('description');
            $table->decimal('budget_min', 10, 2)->nullable();
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->integer('expected_delivery_days')->nullable();
            $table->string('status', 30)->default('published')->index();
            $table->integer('progress')->default(0);
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_projects');
    }
};
