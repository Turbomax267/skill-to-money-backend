<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource_type', 80);
            $table->unsignedBigInteger('resource_id');
            $table->date('viewed_on');
            $table->timestamps();

            $table->unique(['viewer_user_id', 'resource_type', 'resource_id', 'viewed_on'], 'resource_views_unique_daily');
            $table->index(['resource_type', 'resource_id']);
        });

        Schema::table('freelancer_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('views_count')->default(0)->after('visibility_score');
        });

        Schema::table('mype_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('views_count')->default(0)->after('profile_photo');
        });

        Schema::table('client_projects', function (Blueprint $table): void {
            $table->unsignedInteger('views_count')->default(0)->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table): void {
            $table->dropColumn('views_count');
        });

        Schema::table('mype_profiles', function (Blueprint $table): void {
            $table->dropColumn('views_count');
        });

        Schema::table('freelancer_profiles', function (Blueprint $table): void {
            $table->dropColumn('views_count');
        });

        Schema::dropIfExists('resource_views');
    }
};
