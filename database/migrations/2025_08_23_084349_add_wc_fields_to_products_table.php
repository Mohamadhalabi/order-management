<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'wc_id')) {
                $table->unsignedBigInteger('wc_id')->nullable()->index();
            }

            // Only add if not present; do NOT call ->change() on SQLite
            if (! Schema::hasColumn('products', 'image')) {
                $table->string('image')->nullable();
            }

            if (! Schema::hasColumn('products', 'sale_price')) {
                $table->decimal('sale_price', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('products', 'wc_synced_at')) {
                $table->timestamp('wc_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Drop only columns we might have added, and only if they exist
        if (Schema::hasColumn('products', 'wc_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('wc_id');
            });
        }

        // Only drop image/sale_price if you’re sure this migration added them.
        // Using conditional drops keeps it safe either way.
        if (Schema::hasColumn('products', 'image')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
        if (Schema::hasColumn('products', 'sale_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('sale_price');
            });
        }

        if (Schema::hasColumn('products', 'wc_synced_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('wc_synced_at');
            });
        }
    }
};
