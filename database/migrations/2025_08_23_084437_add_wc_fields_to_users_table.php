<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'wc_id')) {
                $table->unsignedBigInteger('wc_id')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable();
            }
            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable();
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (! Schema::hasColumn('users', 'wc_synced_at')) {
                $table->timestamp('wc_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Drop each column only if it exists (SQLite-safe)
        if (Schema::hasColumn('users', 'wc_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('wc_id');
            });
        }
        if (Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('first_name');
            });
        }
        if (Schema::hasColumn('users', 'last_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('last_name');
            });
        }
        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
        if (Schema::hasColumn('users', 'wc_synced_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('wc_synced_at');
            });
        }
    }
};
