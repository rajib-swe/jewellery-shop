<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Reads the audit trail.
 *
 * The `causer` and `subject` morphs are resolved to a label in PHP rather than
 * joined, because `subject_id` is a string across several tables and a
 * polymorphic join cannot know which table to reach for.
 */
class ActivityLogService
{
    /**
     * @param  array{user_id?: ?int, log_name?: ?string, event?: ?string, date_from?: ?string, date_to?: ?string, search?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Activity::query()->with(['causer', 'subject']);

        if (! empty($filters['user_id'])) {
            $query->where('causer_id', $filters['user_id']);
        }

        if (! empty($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $activityQuery) use ($search): void {
                $activityQuery
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * The distinct log names and events present in the trail, so the filter
     * dropdowns only ever offer values that return rows.
     *
     * @return array{log_names: list<string>, events: list<string>}
     */
    public function facets(): array
    {
        return [
            'log_names' => Activity::query()
                ->distinct()
                ->whereNotNull('log_name')
                ->orderBy('log_name')
                ->pluck('log_name')
                ->all(),
            'events' => Activity::query()
                ->distinct()
                ->whereNotNull('event')
                ->orderBy('event')
                ->pluck('event')
                ->all(),
        ];
    }
}
