<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexActivityLogRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(
        IndexActivityLogRequest $request,
        ActivityLogService $activityLog,
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $paginator = $activityLog->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 25),
            (int) ($validated['page'] ?? 1),
        );

        return ActivityLogResource::collection($paginator->withQueryString());
    }

    /**
     * The filter options the viewer needs: the users who have ever caused an
     * entry, plus the log names and events present in the trail. Only values
     * that return rows are offered.
     */
    public function filters(ActivityLogService $activityLog): JsonResponse
    {
        $causerIds = Activity::query()
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id')
            ->filter()
            ->map(fn (mixed $id) => (int) $id);

        return response()->json([
            'data' => [
                'users' => User::query()
                    ->whereIn('id', $causerIds)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ])
                    ->all(),
                ...$activityLog->facets(),
            ],
        ]);
    }
}
