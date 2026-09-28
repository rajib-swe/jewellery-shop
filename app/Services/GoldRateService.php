<?php

namespace App\Services;

use App\Models\GoldRate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class GoldRateService
{
    /**
     * @param  array{search?: ?string, karat?: ?int, effective_date?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = GoldRate::query()->with('createdBy');

        if (isset($filters['karat'])) {
            $query->where('karat', $filters['karat']);
        }

        if (isset($filters['effective_date'])) {
            $query->whereDate('effective_date', $filters['effective_date']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            if (ctype_digit($search)) {
                $query->where('karat', (int) $search);
            } elseif (strtotime($search) !== false) {
                $query->whereDate('effective_date', $search);
            } else {
                $query->whereHas('createdBy', function (Builder $creatorQuery) use ($search): void {
                    $creatorQuery->where('name', 'like', "%{$search}%");
                });
            }
        }

        return $query
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * The single rate source for money that moves against gold.
     *
     * Sales, purchases, pawn estimates and every printout resolve the karat rate
     * through here, so "the rate effective on this date" can only ever mean one
     * thing. A karat with no rate on or before the date returns null and the
     * caller decides whether that is an error.
     */
    public function rateFor(int $karat, CarbonInterface $date): ?float
    {
        $goldRate = GoldRate::query()
            ->where('karat', $karat)
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        return $goldRate === null ? null : (float) $goldRate->rate_per_gram;
    }

    public function latest(): Collection
    {
        $latestDates = GoldRate::query()
            ->select('karat')
            ->selectRaw('MAX(effective_date) as latest_date')
            ->whereDate('effective_date', '<=', today()->toDateString())
            ->groupBy('karat');

        return GoldRate::query()
            ->joinSub($latestDates, 'latest_dates', function (JoinClause $join): void {
                $join->on('gold_rates.karat', '=', 'latest_dates.karat')
                    ->on('gold_rates.effective_date', '=', 'latest_dates.latest_date');
            })
            ->with('createdBy')
            ->orderBy('gold_rates.karat')
            ->get([
                'gold_rates.id',
                'gold_rates.karat',
                'gold_rates.rate_per_gram',
                'gold_rates.effective_date',
                'gold_rates.created_by',
                'gold_rates.created_at',
                'gold_rates.updated_at',
            ]);
    }

    /**
     * @param  array{karat: int, rate_per_gram: string, effective_date: string}  $data
     */
    public function create(array $data, User $user): GoldRate
    {
        return DB::transaction(function () use ($data, $user): GoldRate {
            return GoldRate::query()->create([
                ...$data,
                'created_by' => $user->getKey(),
            ])->load('createdBy');
        });
    }

    /**
     * @param  array{karat: int, rate_per_gram: string, effective_date: string}  $data
     */
    public function update(GoldRate $goldRate, array $data): GoldRate
    {
        return DB::transaction(function () use ($goldRate, $data): GoldRate {
            $goldRate->update($data);

            return $goldRate->load('createdBy');
        });
    }

    public function delete(GoldRate $goldRate): void
    {
        DB::transaction(function () use ($goldRate): void {
            $goldRate->delete();
        });
    }
}
