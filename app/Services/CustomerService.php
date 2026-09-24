<?php

namespace App\Services;

use App\Models\Customer;
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
        return [
            'sales' => [],
            'pawns' => [],
            'payments' => [],
            'due_balance' => (string) $customer->opening_balance,
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
