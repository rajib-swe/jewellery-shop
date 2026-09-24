<?php

namespace App\Services;

use App\ItemStatus;
use App\Karat;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StockService
{
    /**
     * @param  array{search?: ?string, category_id?: ?int, karat?: ?int, status?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Item::query()->with('category');

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['karat'])) {
            $query->where('karat', $filters['karat']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $itemQuery) use ($search): void {
                $itemQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('tag_no', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array{category_id: int, name: string, karat: int, gross_weight: string|float, stone_weight?: string|float, making_type: string, making_value: string|float, stone_price?: string|float, barcode?: ?string}  $data
     */
    public function createInbound(
        array $data,
        User $user,
        ?UploadedFile $photo = null,
    ): Item {
        $data = Arr::except($data, ['tag_no', 'net_weight', 'status', 'image']);
        $netWeight = $this->netWeight(
            (float) $data['gross_weight'],
            (float) ($data['stone_weight'] ?? 0),
        );
        $photoPath = $this->storePhoto($photo);

        try {
            return DB::transaction(function () use ($data, $netWeight, $photoPath, $user): Item {
                $item = Item::query()->create([
                    ...$data,
                    'net_weight' => $netWeight,
                    'status' => ItemStatus::InStock,
                    'image' => $photoPath,
                ]);

                $this->recordMovement(
                    $item,
                    StockMovementType::In,
                    $netWeight,
                    $user,
                    'Item added to inventory',
                    'item',
                    (string) $item->getKey(),
                );

                return $item->load('category');
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{category_id?: int, name?: string, karat?: int, gross_weight?: string|float, stone_weight?: string|float, making_type?: string, making_value?: string|float, stone_price?: string|float, barcode?: ?string}  $data
     */
    public function update(
        Item $item,
        array $data,
        User $user,
        ?UploadedFile $photo = null,
        bool $removePhoto = false,
    ): Item {
        $data = Arr::except($data, ['tag_no', 'net_weight', 'status', 'image']);
        $oldPhoto = $item->image;
        $newPhoto = $oldPhoto;
        $storedPhoto = null;

        if ($photo !== null) {
            $storedPhoto = $this->storePhoto($photo);
            $newPhoto = $storedPhoto;
        } elseif ($removePhoto) {
            $newPhoto = null;
        }

        try {
            $updatedItem = DB::transaction(function () use ($item, $data, $newPhoto, $user): Item {
                $lockedItem = Item::query()->lockForUpdate()->findOrFail($item->getKey());
                $oldNetWeight = (float) $lockedItem->net_weight;
                $newNetWeight = $this->netWeight(
                    (float) ($data['gross_weight'] ?? $lockedItem->gross_weight),
                    (float) ($data['stone_weight'] ?? $lockedItem->stone_weight),
                );

                $lockedItem->update([
                    ...$data,
                    'net_weight' => $newNetWeight,
                    'image' => $newPhoto,
                ]);

                $weightDifference = round($newNetWeight - $oldNetWeight, 3);

                if ($weightDifference !== 0.0) {
                    $this->recordMovement(
                        $lockedItem,
                        StockMovementType::Adjust,
                        abs($weightDifference),
                        $user,
                        'Item weight updated',
                        'item',
                        (string) $lockedItem->getKey(),
                    );
                }

                return $lockedItem->load('category');
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

        return $updatedItem;
    }

    public function adjust(
        Item $item,
        User $user,
        StockMovementType $type,
        float $weight,
        ItemStatus $status,
        ?string $note = null,
    ): Item {
        if ($weight <= 0) {
            throw ValidationException::withMessages([
                'weight' => 'Movement weight must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($item, $user, $type, $weight, $status, $note): Item {
            $lockedItem = Item::query()->lockForUpdate()->findOrFail($item->getKey());

            $this->recordMovement(
                $lockedItem,
                $type,
                $weight,
                $user,
                $note,
                'manual_adjustment',
                null,
            );

            $lockedItem->status = $status;
            $lockedItem->save();

            return $lockedItem->load('category');
        });
    }

    public function markSold(
        Item $item,
        User $user,
        string $referenceType,
        string $referenceId,
        ?float $weight = null,
    ): Item {
        return $this->changeStatus(
            $item,
            $user,
            ItemStatus::Sold,
            StockMovementType::Out,
            $weight ?? (float) $item->net_weight,
            $referenceType,
            $referenceId,
            'Item sold',
        );
    }

    public function markPawned(
        Item $item,
        User $user,
        string $referenceType,
        string $referenceId,
        ?float $weight = null,
    ): Item {
        return $this->changeStatus(
            $item,
            $user,
            ItemStatus::Pawned,
            StockMovementType::Out,
            $weight ?? (float) $item->net_weight,
            $referenceType,
            $referenceId,
            'Item pawned',
        );
    }

    public function markScrap(
        Item $item,
        User $user,
        string $referenceType,
        string $referenceId,
        ?float $weight = null,
    ): Item {
        return $this->changeStatus(
            $item,
            $user,
            ItemStatus::Scrap,
            StockMovementType::Out,
            $weight ?? (float) $item->net_weight,
            $referenceType,
            $referenceId,
            'Item scrapped',
        );
    }

    public function restoreInStock(
        Item $item,
        User $user,
        string $referenceType,
        string $referenceId,
        ?float $weight = null,
    ): Item {
        return $this->changeStatus(
            $item,
            $user,
            ItemStatus::InStock,
            StockMovementType::In,
            $weight ?? (float) $item->net_weight,
            $referenceType,
            $referenceId,
            'Item returned to stock',
        );
    }

    public function delete(Item $item): void
    {
        $photo = $item->image;

        DB::transaction(function () use ($item): void {
            $item->delete();
        });

        if ($photo !== null) {
            Storage::disk('public')->delete($photo);
        }
    }

    /**
     * @return array{total_items: int, total_net_weight: string, by_karat: list<array{karat: int, total_net_weight: string, item_count: int}>}
     */
    public function summary(): array
    {
        $rows = Item::query()
            ->where('status', ItemStatus::InStock->value)
            ->select('karat')
            ->selectRaw('COALESCE(SUM(net_weight), 0) as total_net_weight')
            ->selectRaw('COUNT(*) as item_count')
            ->groupBy('karat')
            ->orderBy('karat')
            ->get()
            ->keyBy('karat');

        $byKarat = collect(Karat::cases())
            ->map(function (Karat $karat) use ($rows): array {
                $row = $rows->get($karat->value);

                return [
                    'karat' => $karat->value,
                    'total_net_weight' => number_format((float) ($row->total_net_weight ?? 0), 3, '.', ''),
                    'item_count' => (int) ($row->item_count ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            'total_items' => array_sum(array_column($byKarat, 'item_count')),
            'total_net_weight' => number_format(
                array_sum(array_map(static fn (array $row): float => (float) $row['total_net_weight'], $byKarat)),
                3,
                '.',
                '',
            ),
            'by_karat' => $byKarat,
        ];
    }

    private function changeStatus(
        Item $item,
        User $user,
        ItemStatus $status,
        StockMovementType $type,
        float $weight,
        string $referenceType,
        string $referenceId,
        ?string $note = null,
    ): Item {
        return DB::transaction(function () use ($item, $user, $status, $type, $weight, $referenceType, $referenceId, $note): Item {
            $lockedItem = Item::query()->lockForUpdate()->findOrFail($item->getKey());

            $this->recordMovement(
                $lockedItem,
                $type,
                $weight,
                $user,
                $note,
                $referenceType,
                $referenceId,
            );

            $lockedItem->status = $status;
            $lockedItem->save();

            return $lockedItem->load('category');
        });
    }

    private function recordMovement(
        Item $item,
        StockMovementType $type,
        float $weight,
        User $user,
        ?string $note,
        ?string $referenceType,
        ?string $referenceId,
    ): StockMovement {
        return $item->movements()->create([
            'type' => $type,
            'weight' => number_format($weight, 3, '.', ''),
            'note' => $note,
            'ref_type' => $referenceType,
            'ref_id' => $referenceId,
            'user_id' => $user->getKey(),
        ]);
    }

    private function netWeight(float $grossWeight, float $stoneWeight): string
    {
        if ($grossWeight <= 0) {
            throw ValidationException::withMessages([
                'gross_weight' => 'Gross weight must be greater than zero.',
            ]);
        }

        if ($stoneWeight < 0 || $stoneWeight > $grossWeight) {
            throw ValidationException::withMessages([
                'stone_weight' => 'Stone weight cannot exceed gross weight.',
            ]);
        }

        return number_format($grossWeight - $stoneWeight, 3, '.', '');
    }

    private function storePhoto(?UploadedFile $photo): ?string
    {
        if ($photo === null) {
            return null;
        }

        $path = $photo->store('items/images', 'public');

        if ($path === false) {
            throw new RuntimeException('Unable to store the item image.');
        }

        return $path;
    }
}
