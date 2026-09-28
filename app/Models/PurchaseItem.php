<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'purchase_id',
    'item_id',
    'tag_no',
    'name',
    'karat',
    'gross_weight',
    'stone_weight',
    'net_weight',
    'rate',
    'making_value',
    'amount',
])]
class PurchaseItem extends Model
{
    use HasFactory, LogsActivity;

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('purchase_items')
            ->logOnly([
                'purchase_id', 'item_id', 'tag_no', 'name', 'karat',
                'gross_weight', 'stone_weight', 'net_weight', 'rate', 'making_value', 'amount',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'karat' => 'integer',
            'gross_weight' => 'decimal:3',
            'stone_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'rate' => 'decimal:2',
            'making_value' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
