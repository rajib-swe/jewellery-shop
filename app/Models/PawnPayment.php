<?php

namespace App\Models;

use App\PawnPaymentType;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'pawn_id',
    'type',
    'amount',
    'date',
    'method',
    'reference',
    'note',
    'user_id',
])]
class PawnPayment extends Model
{
    use HasFactory, LogsActivity;

    public function pawn(): BelongsTo
    {
        return $this->belongsTo(Pawn::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pawn_payments')
            ->logOnly(['pawn_id', 'type', 'amount', 'date', 'method', 'reference', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
            'type' => PawnPaymentType::class,
            'method' => PaymentMethod::class,
        ];
    }
}
