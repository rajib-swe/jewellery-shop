<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedTinyInteger('karat');
            $table->decimal('weight', 14, 3);
            $table->decimal('rate', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_exchanges');
    }
};
