<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the existing FK to customers
            try { $table->dropForeign(['customer_id']); } catch (\Throwable $e) {
                // If you named it, use: $table->dropForeign('orders_customer_id_foreign');
            }

            // (Optional) ensure the type matches users.id
            // $table->unsignedBigInteger('customer_id')->nullable()->change();

            // Re-create FK to users(id)
            $table->foreign('customer_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete(); // or ->restrictOnDelete()
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            try { $table->dropForeign(['customer_id']); } catch (\Throwable $e) {}

            // Re-create FK back to customers if you ever need to roll back:
            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }
};
