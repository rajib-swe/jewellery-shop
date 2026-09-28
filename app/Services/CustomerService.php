<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Pawn;
use App\Models\Sale;
use App\Models\SalePayment;
use App\SaleStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CustomerService
{
    public function __construct(private readonly PawnInterestService $pawnInterest) {}

    /**
     * @param  array{search?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Customer::query();
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $customerQuery) use ($search): void {
                $customerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('nid', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array{name: string, phone: string, nid?: ?string, address?: ?string, opening_balance: string|float|int, notes?: ?string}  $data
     */
    public function create(array $data, ?UploadedFile $photo = null): Customer
    {
        $photoPath = $this->storePhoto($photo);

        try {
            return DB::transaction(function () use ($data, $photoPath): Customer {
                return Customer::query()->create([
                    ...$data,
                    'code' => $this->generateCode(),
                    'photo' => $photoPath,
                ]);
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{name?: string, phone?: string, nid?: ?string, address?: ?string, opening_balance?: string|float|int, notes?: ?string}  $data
     */
    public function update(
        Customer $customer,
        array $data,
        ?UploadedFile $photo = null,
        bool $removePhoto = false,
    ): Customer {
        $oldPhoto = $customer->photo;
        $newPhoto = $oldPhoto;
        $storedPhoto = null;

        if ($photo !== null) {
            $storedPhoto = $this->storePhoto($photo);
            $newPhoto = $storedPhoto;
        } elseif ($removePhoto) {
            $newPhoto = null;
        }

        unset($data['photo']);

        try {
            DB::transaction(function () use ($customer, $data, $newPhoto): void {
                $customer->update([
                    ...$data,
                    'photo' => $newPhoto,
                ]);
            });
        } catch (Throwable $exception) {
            if ($storedPhoto !== null) {
                Storage::disk('public')->delete($storedPhoto);
            }

            throw $exception;
        }

        if ($oldPhoto && $oldPhoto !== $newPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $photo = $customer->photo;

        DB::transaction(function () use ($customer): void {
            $customer->delete();
        });

        if ($photo) {
            Storage::disk('public')->delete($photo);
        }
    }

    /**
     * @return array{sales: list<mixed>, pawns: list<mixed>, payments: list<mixed>, due_balance: string}
     */
    public function history(Customer $customer): array
    {
        $sales = Sale::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        // Only the interest engine reads this ledger, and it needs exactly
        // type, amount and date, so the column subset is safe here.
        $pawns = Pawn::query()
            ->where('customer_id', $customer->getKey())
            ->with('payments:id,pawn_id,type,amount,date')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $payments = SalePayment::query()
            ->whereHas('sale', function (Builder $saleQuery) use ($customer): void {
                $saleQuery->where('customer_id', $customer->getKey());
            })
            ->with('sale:id,invoice_no,date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $dueCents = $sales
            ->filter(fn (Sale $sale): bool => $sale->status === SaleStatus::Completed)
            ->sum(fn (Sale $sale): int => (int) round((float) $sale->due * 100));

        $pawnDueCents = 0;

        foreach ($pawns as $pawn) {
            $summary = $this->pawnInterest->calculate($pawn);

            $pawnDueCents += (int) round((float) $summary['total_payable'] * 100);
        }

        return [
            'sales' => $sales->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'date' => $sale->date->toDateString(),
                'status' => $sale->status->value,
                'total' => (string) $sale->total,
                'paid' => (string) $sale->paid,
                'due' => (string) $sale->due,
            ])->all(),
            'pawns' => $pawns->map(function (Pawn $pawn): array {
                $summary = $this->pawnInterest->calculate($pawn);

                return [
                    'id' => $pawn->id,
                    'pawn_no' => $pawn->pawn_no,
                    'date' => $pawn->date->toDateString(),
                    'due_date' => $pawn->due_date->toDateString(),
                    'status' => $pawn->status->value,
                    'principal' => (string) $pawn->principal,
                    'outstanding_principal' => $summary['outstanding_principal'],
                    'interest_due' => $summary['interest_due'],
                    'total_payable' => $summary['total_payable'],
                ];
            })->all(),
            'payments' => $payments->map(fn (SalePayment $payment): array => [
                'id' => $payment->id,
                'invoice_no' => $payment->sale->invoice_no,
                'date' => $payment->sale->date->toDateString(),
                'method' => $payment->method->value,
                'amount' => (string) $payment->amount,
                'reference' => $payment->reference,
            ])->all(),
            'due_balance' => number_format(
                ((int) round((float) $customer->opening_balance * 100) + $dueCents + $pawnDueCents) / 100,
                2,
                '.',
                '',
            ),
        ];
    }

    private function storePhoto(?UploadedFile $photo): ?string
    {
        if ($photo === null) {
            return null;
        }

        $path = $photo->store('customers/photos', 'public');

        if ($path === false) {
            throw new RuntimeException('Unable to store the customer photo.');
        }

        return $path;
    }

    private function generateCode(): string
    {
        return 'CUS-'.Str::ulid()->toBase32();
    }
}
