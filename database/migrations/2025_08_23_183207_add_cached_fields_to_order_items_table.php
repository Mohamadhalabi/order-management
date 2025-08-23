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
            if (! Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name')->nullable();
            }
            if (! Schema::hasColumn('order_items', 'sku')) {
                $table->string('sku')->nullable();
            }
            if (! Schema::hasColumn('order_items', 'stock_snapshot')) {
                $table->integer('stock_snapshot')->nullable();
            }
        });
    }
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name','sku','stock_snapshot']);
        });
    }
};
