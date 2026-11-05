<?php

namespace App\Models;

use Database\Factories\DailyClosingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'date',
    'opening_balance',
    'total_in',
    'total_out',
    'closing_balance',
    'closed_by',
    'closed_at',
    'reopened_by',
    'reopened_at',
    'note',
])]
class DailyClosing extends Model
{
    /** @use HasFactory<DailyClosingFactory> */
    use HasFactory, LogsActivity;

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isLocked(): bool
    {
        return $this->closed_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('daily_closings')
            ->logOnly(['date', 'opening_balance', 'total_in', 'total_out', 'closing_balance', 'closed_at', 'reopened_at', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'opening_balance' => 'decimal:2',
            'total_in' => 'decimal:2',
            'total_out' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
