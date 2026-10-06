<?php

namespace App\Models;

use App\Support\WeightPoints;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'pawn_id',
    'description',
    'karat',
    'gross_weight_points',
    'stone_weight_points',
    'net_weight_points',
    'estimated_value',
    'photo',
    'category_id',
    'item_id',
])]
class PawnItem extends Model
{
    use HasFactory, LogsActivity;

    public function getGrossWeightAttribute(): string
    {
        return WeightPoints::toGrams($this->attributes['gross_weight_points'] ?? null);
    }

    public function getStoneWeightAttribute(): string
    {
        return WeightPoints::toGrams($this->attributes['stone_weight_points'] ?? null);
    }

    public function getNetWeightAttribute(): string
    {
        return WeightPoints::toGrams($this->attributes['net_weight_points'] ?? null);
    }

    public function pawn(): BelongsTo
    {
        return $this->belongsTo(Pawn::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo === null ? null : Storage::disk('public')->url($this->photo);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pawn_items')
            ->logOnly([
                'pawn_id', 'description', 'karat', 'gross_weight_points', 'stone_weight_points', 'net_weight_points',
                'estimated_value', 'photo', 'category_id', 'item_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'karat' => 'integer',
            'gross_weight_points' => 'integer',
            'stone_weight_points' => 'integer',
            'net_weight_points' => 'integer',
            'estimated_value' => 'decimal:2',
        ];
    }
}
