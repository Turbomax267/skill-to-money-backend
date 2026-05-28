<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 80)->nullable()->after('name');
            $table->string('last_name', 80)->nullable()->after('first_name');
            $table->string('company_name', 120)->nullable()->after('last_name');
            $table->string('account_type', 30)->default('freelancer')->after('company_name');
            $table->string('phone', 30)->nullable()->after('account_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'first_name',
                'last_name',
                'company_name',
                'account_type',
                'phone',
            ]);
        });
    }
};
