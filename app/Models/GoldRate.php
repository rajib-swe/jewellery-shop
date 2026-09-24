<?php

namespace App\Models;

use Database\Factories\GoldRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['karat', 'rate_per_gram', 'effective_date', 'created_by'])]
class GoldRate extends Model
{
    /** @use HasFactory<GoldRateFactory> */
    use HasFactory, LogsActivity;

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('gold-rates')
            ->logOnly(['karat', 'rate_per_gram', 'effective_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'karat' => 'integer',
            'rate_per_gram' => 'decimal:2',
            'effective_date' => 'date:Y-m-d',
        ];
    }
}
