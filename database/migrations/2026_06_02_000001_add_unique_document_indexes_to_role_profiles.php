<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table): void {
            $table->unique('dni');
        });

        Schema::table('mype_profiles', function (Blueprint $table): void {
            $table->unique('ruc');
        });
    }

    public function down(): void
    {
        Schema::table('mype_profiles', function (Blueprint $table): void {
            $table->dropUnique(['ruc']);
        });

        Schema::table('freelancer_profiles', function (Blueprint $table): void {
            $table->dropUnique(['dni']);
        });
    }
};
