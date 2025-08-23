<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // who created (admin/seller)
            $table->foreignId('created_by_id')->nullable()->constrained('users');

            // customer (if you don’t have it yet)
            if (! Schema::hasColumn('orders', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->constrained('users');
            }

            // money/total (if missing)
            if (! Schema::hasColumn('orders', 'total')) {
                $table->decimal('total', 12, 2)->default(0);
            }

            // shipping fields
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_address_line1')->nullable();
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postcode', 32)->nullable();
            $table->string('shipping_country', 2)->nullable();

            // optional: store pdf path
            $table->string('pdf_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignIdIfExists('created_by_id');
            // don’t drop customer_id if you already rely on it — remove next line if needed
            // $table->dropConstrainedForeignIdIfExists('customer_id');

            $table->dropColumn([
                'total',
                'shipping_name','shipping_phone','shipping_address_line1','shipping_address_line2',
                'shipping_city','shipping_state','shipping_postcode','shipping_country',
                'pdf_path',
            ]);
        });
    }
};