<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexActivityLogRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\Models\Activity;

#[OA\Tag(name: 'Activity Log', description: 'Activity log management endpoints')]
class ActivityLogController extends Controller
{
    #[OA\Get(
        path: '/activity-log',
        summary: 'List activity logs',
        description: 'Return a paginated list of activity log entries',
        tags: ['Activity Log'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'causer_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'log_name', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'event', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of activity logs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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

    #[OA\Get(
        path: '/activity-log/filters',
        summary: 'Activity log filters',
        description: 'The filter options the viewer needs: the users who have ever caused an entry, plus the log names and events present in the trail',
        tags: ['Activity Log'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Filter options for the activity log',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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
