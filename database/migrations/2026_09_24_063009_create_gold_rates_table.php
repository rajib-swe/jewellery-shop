<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gold_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('karat');
            $table->decimal('rate_per_gram', 14, 2);
            $table->date('effective_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['karat', 'effective_date']);
            $table->index(['effective_date', 'karat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_rates');
    }
};
