<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tag_no', 32);
            $table->string('name');
            $table->unsignedTinyInteger('karat');
            $table->decimal('weight', 14, 3);
            $table->decimal('rate', 14, 2);
            $table->decimal('gold_value', 14, 2);
            $table->decimal('making', 14, 2);
            $table->decimal('stone_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->index(['sale_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
