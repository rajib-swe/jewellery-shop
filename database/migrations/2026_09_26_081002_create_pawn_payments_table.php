<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pawn_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pawn_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->date('date');
            $table->string('method', 20)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['pawn_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pawn_payments');
    }
};
