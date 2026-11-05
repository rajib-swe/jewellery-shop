<?php

namespace App\Models;

use App\Support\WeightPoints;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['sale_id', 'description', 'karat', 'weight_points', 'rate', 'amount', 'item_id'])]
class SaleExchange extends Model
{
    use LogsActivity;

    public function getWeightAttribute(): string
    {
        return WeightPoints::toGrams($this->attributes['weight_points'] ?? null);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sale-exchanges')
            ->logOnly(['sale_id', 'description', 'karat', 'weight_points', 'rate', 'amount', 'item_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'karat' => 'integer',
            'weight_points' => 'integer',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
