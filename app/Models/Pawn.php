<?php

namespace App\Models;

use App\PawnInterestType;
use App\PawnStatus;
use Database\Factories\PawnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'pawn_no',
    'customer_id',
    'date',
    'principal',
    'interest_rate',
    'interest_type',
    'due_date',
    'status',
    'notes',
    'user_id',
    'redeemed_at',
    'redeemed_by',
    'forfeited_at',
    'forfeited_by',
    'close_reason',
    'renewed_at',
    'overdue_flagged_at',
])]
class Pawn extends Model
{
    /** @use HasFactory<PawnFactory> */
    use HasFactory, LogsActivity;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function forfeitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forfeited_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PawnItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PawnPayment::class)->orderBy('date')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === PawnStatus::Active;
    }

    public function isOverdue(): bool
    {
        return $this->status === PawnStatus::Active
            && $this->due_date->startOfDay()->isBefore(today()->startOfDay());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pawns')
            ->logOnly([
                'pawn_no', 'customer_id', 'date', 'principal', 'interest_rate',
                'interest_type', 'due_date', 'status', 'notes', 'close_reason', 'renewed_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'principal' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'interest_type' => PawnInterestType::class,
            'status' => PawnStatus::class,
            'redeemed_at' => 'datetime',
            'forfeited_at' => 'datetime',
            'renewed_at' => 'datetime',
            'overdue_flagged_at' => 'datetime',
        ];
    }
}
