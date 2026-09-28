<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pawn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pawn_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedTinyInteger('karat');
            $table->decimal('gross_weight', 14, 3);
            $table->decimal('stone_weight', 14, 3)->default(0);
            $table->decimal('net_weight', 14, 3);
            $table->decimal('estimated_value', 14, 2);
            $table->string('photo')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('pawn_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pawn_items');
    }
};
