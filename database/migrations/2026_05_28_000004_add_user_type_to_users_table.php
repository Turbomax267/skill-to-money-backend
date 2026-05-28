<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type', 30)->default('freelancer')->after('account_type');
                $table->index('user_type');
            }
        });

        if (Schema::hasColumn('users', 'account_type') && Schema::hasColumn('users', 'user_type')) {
            DB::table('users')->update([
                'user_type' => DB::raw("COALESCE(NULLIF(account_type, ''), user_type)"),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'user_type')) {
                $table->dropIndex(['user_type']);
                $table->dropColumn('user_type');
            }
        });
    }
};

