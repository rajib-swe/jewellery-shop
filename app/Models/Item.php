<?php

namespace App\Models;

use App\ItemStatus;
use App\MakingType;
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
    'gross_weight',
    'stone_weight',
    'net_weight',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('items')
            ->logOnly([
                'tag_no',
                'category_id',
                'name',
                'karat',
                'gross_weight',
                'stone_weight',
                'net_weight',
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
            'gross_weight' => 'decimal:3',
            'stone_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'making_type' => MakingType::class,
            'making_value' => 'decimal:2',
            'stone_price' => 'decimal:2',
            'status' => ItemStatus::class,
        ];
    }
}
