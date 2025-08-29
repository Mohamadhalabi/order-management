<?php
// database/migrations/2025_08_29_000001_orders_status_tr.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Make column a string (keeps current data)
        Schema::table('orders', function (Blueprint $t) {
            $t->string('status', 32)->default('taslak')->change();
        });

        // 2) Convert existing EN values -> TR
        DB::table('orders')->update([
            'status' => DB::raw("
                CASE status
                    WHEN 'draft'     THEN 'taslak'
                    WHEN 'pending'   THEN 'onay_bekliyor'
                    WHEN 'approved'  THEN 'onaylandi'
                    WHEN 'paid'      THEN 'odendi'
                    WHEN 'shipped'   THEN 'kargolandi'
                    WHEN 'cancelled' THEN 'iptal'
                    ELSE status
                END
            ")
        ]);
    }

    public function down(): void
    {
        // Optional: map back to EN and (if you want) revert to enum
        DB::table('orders')->update([
            'status' => DB::raw("
                CASE status
                    WHEN 'taslak'         THEN 'draft'
                    WHEN 'onay_bekliyor'  THEN 'pending'
                    WHEN 'onaylandi'      THEN 'approved'
                    WHEN 'odendi'         THEN 'paid'
                    WHEN 'kargolandi'     THEN 'shipped'
                    WHEN 'iptal'          THEN 'cancelled'
                    ELSE status
                END
            ")
        ]);

        Schema::table('orders', function (Blueprint $t) {
            // If you prefer to fully revert:
            // $t->enum('status', ['draft','pending','paid','shipped','cancelled'])->default('draft')->change();
            $t->string('status', 32)->default('draft')->change();
        });
    }
};
