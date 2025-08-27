<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Adds KDV fields after shipping_amount
            $table->decimal('kdv_percent', 5, 2)->default(0)->after('shipping_amount');
            $table->decimal('kdv_amount', 12, 2)->default(0)->after('kdv_percent');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Safe to drop in MySQL without extra packages on recent Laravel
            if (Schema::hasColumn('orders', 'kdv_amount')) {
                $table->dropColumn('kdv_amount');
            }
            if (Schema::hasColumn('orders', 'kdv_percent')) {
                $table->dropColumn('kdv_percent');
            }
        });
    }
};
