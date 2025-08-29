<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Add line_total if it doesn’t exist yet
            if (!Schema::hasColumn('order_items', 'line_total')) {
                $table->decimal('line_total', 12, 2)->default(0)->after('unit_price');
            }

            // (Optional) keep a snapshot of stock at order time
            if (!Schema::hasColumn('order_items', 'stock_snapshot')) {
                $table->integer('stock_snapshot')->nullable()->after('line_total');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'stock_snapshot')) {
                $table->dropColumn('stock_snapshot');
            }
            if (Schema::hasColumn('order_items', 'line_total')) {
                $table->dropColumn('line_total');
            }
        });
    }
};
