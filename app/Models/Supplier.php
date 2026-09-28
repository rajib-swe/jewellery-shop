<?php

namespace App\Models;

use App\SupplierType;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['code', 'name', 'type', 'phone', 'address', 'nid', 'notes'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            if (blank($supplier->code)) {
                $supplier->code = 'SUP-'.Str::ulid()->toBase32();
            }
        });
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('suppliers')
            ->logOnly(['name', 'type', 'phone', 'address', 'nid', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'type' => SupplierType::class,
        ];
    }
}
