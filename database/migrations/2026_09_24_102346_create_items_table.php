<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('tag_no', 32)->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('karat');
            $table->decimal('gross_weight', 14, 3);
            $table->decimal('stone_weight', 14, 3)->default(0);
            $table->decimal('net_weight', 14, 3);
            $table->string('making_type', 20);
            $table->decimal('making_value', 14, 2)->default(0);
            $table->decimal('stone_price', 14, 2)->default(0);
            $table->string('status', 20)->default('in_stock');
            $table->string('image')->nullable();
            $table->string('barcode', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['category_id', 'karat']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
