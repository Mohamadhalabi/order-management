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
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wc_id')->nullable()->index();
            $t->string('sku')->unique();
            $t->string('name');
            $t->decimal('price', 12, 2)->default(0);
            $t->integer('stock')->default(0);
            $t->timestamp('updated_from_wc_at')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
