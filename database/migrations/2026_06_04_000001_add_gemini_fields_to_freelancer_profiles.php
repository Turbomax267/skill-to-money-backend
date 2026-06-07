<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table): void {
            $table->string('headline', 255)->nullable()->after('bio');
            $table->string('category', 100)->nullable()->after('headline');
            $table->string('suggested_rate', 50)->nullable()->after('category');
            $table->json('gemini_analysis')->nullable()->after('suggested_rate');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table): void {
            $table->dropColumn(['headline', 'category', 'suggested_rate', 'gemini_analysis']);
        });
    }
};
