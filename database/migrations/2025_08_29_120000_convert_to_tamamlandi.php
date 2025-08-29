<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Example: mark all "onaylandi" as "tamamlandi"
        DB::table('orders')
            ->where('status', 'onaylandi')
            ->update(['status' => 'tamamlandi']);
    }

    public function down(): void
    {
        // Revert the change
        DB::table('orders')
            ->where('status', 'tamamlandi')
            ->update(['status' => 'onaylandi']);
    }
};
