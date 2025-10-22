<?php
// database/migrations/2025_10_22_000000_create_currencies_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();     // e.g. USD, TRY, EUR
            $table->string('name');                  // e.g. US Dollar
            $table->string('symbol', 8)->nullable(); // e.g. $, ₺, €
            $table->decimal('rate', 18, 8)->default(1); // 1 = USD baseline; or whatever you choose
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('currencies');
    }
};
