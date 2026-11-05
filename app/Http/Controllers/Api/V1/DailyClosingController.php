<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseDayRequest;
use App\Http\Resources\CashBookSummaryResource;
use App\Http\Resources\DailyClosingResource;
use App\Models\DailyClosing;
use App\Services\CashBookService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Daily Closings', description: 'Daily closing management endpoints')]
class DailyClosingController extends Controller
{
    #[OA\Get(
        path: '/daily-closings',
        summary: 'List daily closings',
        description: 'Return a paginated list of daily closings',
        tags: ['Daily Closings'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of daily closings',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(CashBookService $cashBook): AnonymousResourceCollection
    {
        return DailyClosingResource::collection(
            DailyClosing::query()
                ->with(['closedBy:id,name', 'reopenedBy:id,name'])
                ->orderByDesc('date')
                ->paginate(perPage: 31),
        );
    }

    #[OA\Get(
        path: '/daily-closings/show',
        summary: 'Get closing summary',
        description: 'Return the cash book summary for a specific date',
        tags: ['Daily Closings'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cash book summary for the date',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(CloseDayRequest $request, CashBookService $cashBook): CashBookSummaryResource
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? 'today')->startOfDay();

        return CashBookSummaryResource::make($cashBook->summaryFor($date));
    }

    #[OA\Post(
        path: '/daily-closings',
        summary: 'Close day',
        description: 'Close the cash book for a specific date',
        tags: ['Daily Closings'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['date'],
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2024-01-15'),
                    new OA\Property(property: 'note', type: 'string', example: 'End of day closing'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Day closed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Day closed.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(CloseDayRequest $request, CashBookService $cashBook): DailyClosingResource
    {
        $date = CarbonImmutable::parse($request->validated('date'))->startOfDay();

        $closing = $cashBook->close($date, $request->user(), [], $request->validated('note'));

        return DailyClosingResource::make($closing->load(['closedBy:id,name', 'reopenedBy:id,name']))->additional([
            'meta' => [
                'message' => 'Day closed.',
            ],
        ]);
    }

    #[OA\Post(
        path: '/daily-closings/{closing}/reopen',
        summary: 'Reopen day',
        description: 'Reopen a previously closed day',
        tags: ['Daily Closings'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'closing',
                in: 'path',
                required: true,
                description: 'Daily closing ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Day reopened',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Day reopened.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Closing not found'),
        ]
    )]
    public function reopen(
        DailyClosing $closing,
        Request $request,
        CashBookService $cashBook,
    ): DailyClosingResource {
        $reopened = $cashBook->reopen($closing, $request->user());

        return DailyClosingResource::make($reopened->load(['closedBy:id,name', 'reopenedBy:id,name']))->additional([
            'meta' => [
                'message' => 'Day reopened.',
            ],
        ]);
    }
}
