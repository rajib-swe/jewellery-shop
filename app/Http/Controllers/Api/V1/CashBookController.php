<?php

namespace App\Http\Controllers\Api\V1;

use App\CashDirection;
use App\CashSourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashBookSummaryRequest;
use App\Http\Requests\IndexCashTransactionRequest;
use App\Http\Requests\StoreCashTransactionRequest;
use App\Http\Resources\CashBookSummaryResource;
use App\Http\Resources\CashTransactionResource;
use App\Services\CashBookService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Cash Book', description: 'Cash book transaction endpoints')]
class CashBookController extends Controller
{
    #[OA\Get(
        path: '/cash-book',
        summary: 'List cash transactions',
        description: 'Return a paginated list of cash book transactions',
        tags: ['Cash Book'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['in', 'out'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of cash transactions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(IndexCashTransactionRequest $request, CashBookService $cashBook): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $cashBook->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return CashTransactionResource::collection($paginator->withQueryString());
    }

    #[OA\Get(
        path: '/cash-book/summary',
        summary: 'Cash book summary',
        description: 'Opening, in, out and closing for one day, plus the per-method split',
        tags: ['Cash Book'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cash book summary for the day',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function summary(CashBookSummaryRequest $request, CashBookService $cashBook): CashBookSummaryResource
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? 'today')->startOfDay();

        return CashBookSummaryResource::make($cashBook->summaryFor($date, $request->validated()));
    }

    #[OA\Post(
        path: '/cash-book',
        summary: 'Record cash transaction',
        description: 'Money that moves without a source document, such as a bank withdrawal or a float top-up',
        tags: ['Cash Book'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['direction', 'amount'],
                properties: [
                    new OA\Property(property: 'direction', type: 'string', enum: ['in', 'out'], example: 'in'),
                    new OA\Property(property: 'amount', type: 'number', example: 500.00),
                    new OA\Property(property: 'note', type: 'string', example: 'Bank withdrawal'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Cash entry recorded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Cash entry recorded.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCashTransactionRequest $request, CashBookService $cashBook): CashTransactionResource
    {
        $validated = $request->validated();
        $direction = CashDirection::from($validated['direction']);
        $amount = (float) $validated['amount'];

        $transaction = $direction === CashDirection::In
            ? $cashBook->recordIn(CashSourceType::CashAdjustment, null, $amount, $request->user(), $validated)
            : $cashBook->recordOut(CashSourceType::CashAdjustment, null, $amount, $request->user(), $validated);

        return CashTransactionResource::make($transaction->load('user:id,name'))->additional([
            'meta' => [
                'message' => 'Cash entry recorded.',
            ],
        ]);
    }
}
