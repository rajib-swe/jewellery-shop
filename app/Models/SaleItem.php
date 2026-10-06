<?php

namespace App\Models;

use App\Support\WeightPoints;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'sale_id', 'item_id', 'tag_no', 'name', 'karat', 'weight_points',
    'rate', 'gold_value', 'making', 'stone_price', 'line_total',
])]
class SaleItem extends Model
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
            ->useLogName('sale-items')
            ->logOnly([
                'sale_id', 'item_id', 'tag_no', 'name', 'karat', 'weight_points',
                'rate', 'gold_value', 'making', 'stone_price', 'line_total',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'karat' => 'integer',
            'weight_points' => 'integer',
            'rate' => 'decimal:2',
            'gold_value' => 'decimal:2',
            'making' => 'decimal:2',
            'stone_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
