<?php

namespace App\Models;

use App\PaymentMethod;
use Database\Factories\SupplierPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'supplier_id',
    'purchase_id',
    'amount',
    'date',
    'method',
    'reference',
    'note',
    'user_id',
])]
class SupplierPayment extends Model
{
    /** @use HasFactory<SupplierPaymentFactory> */
    use HasFactory, LogsActivity;

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('supplier_payments')
            ->logOnly(['supplier_id', 'purchase_id', 'amount', 'date', 'method', 'reference', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
            'method' => PaymentMethod::class,
        ];
    }
}
