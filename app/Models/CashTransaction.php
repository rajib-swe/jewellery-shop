<?php

namespace App\Models;

use App\CashDirection;
use App\CashSourceType;
use App\PaymentMethod;
use Database\Factories\CashTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'source_type',
    'source_id',
    'direction',
    'amount',
    'method',
    'date',
    'reference',
    'note',
    'user_id',
])]
class CashTransaction extends Model
{
    /** @use HasFactory<CashTransactionFactory> */
    use HasFactory, LogsActivity;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cash_transactions')
            ->logOnly(['source_type', 'source_id', 'direction', 'amount', 'method', 'date', 'reference', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'source_type' => CashSourceType::class,
            'direction' => CashDirection::class,
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
            'method' => PaymentMethod::class,
        ];
    }
}
