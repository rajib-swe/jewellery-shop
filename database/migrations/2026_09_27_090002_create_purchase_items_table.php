<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tag_no', 32)->default('');
            $table->string('name');
            $table->unsignedTinyInteger('karat');
            $table->decimal('gross_weight', 14, 3);
            $table->decimal('stone_weight', 14, 3)->default(0);
            $table->decimal('net_weight', 14, 3);
            $table->decimal('rate', 14, 2);
            $table->decimal('making_value', 14, 2)->default(0);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->index('purchase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
