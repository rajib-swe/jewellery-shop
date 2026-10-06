<?php

namespace App\Models;

use App\ItemStatus;
use App\MakingType;
use App\Support\WeightPoints;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'tag_no',
    'category_id',
    'name',
    'karat',
    'gross_weight_points',
    'stone_weight_points',
    'net_weight_points',
    'making_type',
    'making_value',
    'stone_price',
    'status',
    'image',
    'barcode',
])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (Item $item): void {
            if (blank($item->tag_no)) {
                $item->tag_no = 'ITM-'.Str::ulid()->toBase32();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('items')
            ->logOnly([
                'tag_no',
                'category_id',
                'name',
                'karat',
                'gross_weight_points',
                'stone_weight_points',
                'net_weight_points',
                'making_type',
                'making_value',
                'stone_price',
                'status',
                'image',
                'barcode',
            ])
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
            'gross_weight_points' => 'integer',
            'stone_weight_points' => 'integer',
            'net_weight_points' => 'integer',
            'making_type' => MakingType::class,
            'making_value' => 'decimal:2',
            'stone_price' => 'decimal:2',
            'status' => ItemStatus::class,
        ];
    }
}
