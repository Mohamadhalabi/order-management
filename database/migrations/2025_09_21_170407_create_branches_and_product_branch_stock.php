<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });

        Schema::create('product_branch_stock', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->integer('stock')->default(0);
            $t->timestamps();

            $t->unique(['product_id', 'branch_id']); // one row per product per branch
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_branch_stock');
        Schema::dropIfExists('branches');
    }
};
