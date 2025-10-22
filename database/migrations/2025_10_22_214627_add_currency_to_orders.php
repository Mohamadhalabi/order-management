<?php

// database/migrations/2025_10_22_000001_add_currency_to_orders.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('USD')->after('branch_id');
            $table->decimal('currency_rate', 18, 8)->default(1)->after('currency_code');
        });
    }

    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'currency_rate']);
        });
    }
};
