// database/migrations/2025_08_25_000001_add_billing_fields_to_orders_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'billing_name')) {
                $table->string('billing_name')->nullable();
            }
            if (! Schema::hasColumn('orders', 'billing_phone')) {
                $table->string('billing_phone')->nullable();
            }
            if (! Schema::hasColumn('orders', 'billing_address_line1')) {
                $table->string('billing_address_line1')->nullable();
            }
            if (! Schema::hasColumn('orders', 'billing_address_line2')) {
                $table->string('billing_address_line2')->nullable();
            }
            if (! Schema::hasColumn('orders', 'billing_city')) {
                $table->string('billing_city')->nullable();
            }
            if (! Schema::hasColumn('orders', 'billing_state')) {
                $table->string('billing_state')->nullable();
            }
            if (! Schema::hasColumn('orders', 'billing_postcode')) {
                $table->string('billing_postcode', 32)->nullable();
            }
            if (! Schema::hasColumn('orders', 'billing_country')) {
                // use 'TR' since your UI shows ISO code
                $table->string('billing_country', 2)->nullable()->default('TR');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $cols = [
                'billing_name','billing_phone','billing_address_line1','billing_address_line2',
                'billing_city','billing_state','billing_postcode','billing_country',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
