<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            // nullable first so old rows don't break; we'll backfill below
            $t->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnUpdate();
        });

        // Backfill old orders to some sensible default (first branch)
        $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');
        if ($defaultBranchId) {
            DB::table('orders')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        }

        // Optional: make NOT NULL (requires doctrine/dbal for change())
        // if (Schema::hasColumn('orders', 'branch_id')) {
        //     Schema::table('orders', function (Blueprint $t) {
        //         $t->foreignId('branch_id')->nullable(false)->change();
        //     });
        // }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('branch_id');
        });
    }
};
