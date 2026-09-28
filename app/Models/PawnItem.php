<?php

namespace App\Models;

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
    'gross_weight',
    'stone_weight',
    'net_weight',
    'estimated_value',
    'photo',
    'category_id',
    'item_id',
])]
class PawnItem extends Model
{
    use HasFactory, LogsActivity;

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
                'pawn_id', 'description', 'karat', 'gross_weight', 'stone_weight', 'net_weight',
                'estimated_value', 'photo', 'category_id', 'item_id',
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
            'estimated_value' => 'decimal:2',
        ];
    }
}
