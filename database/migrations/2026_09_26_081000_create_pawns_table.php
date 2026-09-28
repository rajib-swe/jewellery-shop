<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pawns', function (Blueprint $table) {
            $table->id();
            $table->string('pawn_no', 32)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->decimal('principal', 14, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->string('interest_type', 20)->default('simple');
            $table->date('due_date');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('forfeited_at')->nullable();
            $table->foreignId('forfeited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('close_reason')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamp('overdue_flagged_at')->nullable();
            $table->timestamps();
            $table->index('date');
            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pawns');
    }
};
