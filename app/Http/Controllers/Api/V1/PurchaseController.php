<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPurchaseRequest;
use App\Http\Requests\PurchaseRateRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Purchases', description: 'Purchase management endpoints')]
class PurchaseController extends Controller
{
    #[OA\Get(
        path: '/purchases',
        summary: 'List purchases',
        description: 'Return a paginated list of purchases',
        tags: ['Purchases'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'supplier_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of purchases',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(IndexPurchaseRequest $request, PurchaseService $purchases): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $purchases->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return PurchaseResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/purchases',
        summary: 'Create purchase',
        description: 'Record a new purchase from a supplier',
        tags: ['Purchases'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['supplier_id', 'items', 'total_weight', 'rate_per_gram'],
                properties: [
                    new OA\Property(property: 'supplier_id', type: 'integer', example: 1),
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'total_weight', type: 'number', example: 25.5),
                    new OA\Property(property: 'rate_per_gram', type: 'number', example: 6200.00),
                    new OA\Property(property: 'purchase_date', type: 'string', format: 'date', example: '2024-01-15'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Gold purchase'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Purchase recorded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Purchase recorded.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StorePurchaseRequest $request, PurchaseService $purchases): PurchaseResource
    {
        $purchase = $purchases->create(
            $request->validated(),
            $request->user(),
            $request->user()->can('manage gold rates'),
        );

        return PurchaseResource::make($purchase)->additional([
            'meta' => [
                'message' => 'Purchase recorded.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/purchases/{purchase}',
        summary: 'Get purchase',
        description: 'Return a single purchase by ID',
        tags: ['Purchases'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'purchase',
                in: 'path',
                required: true,
                description: 'Purchase ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Purchase details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Purchase not found'),
        ]
    )]
    public function show(Purchase $purchase, PurchaseService $purchases): PurchaseResource
    {
        return PurchaseResource::make($purchases->find($purchase));
    }

    #[OA\Get(
        path: '/purchases/rates',
        summary: 'Preview purchase rates',
        description: 'The karat rates a purchase dated on the given date would be priced at',
        tags: ['Purchases'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Preview rates for the date',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function rates(PurchaseRateRequest $request, PurchaseService $purchases): JsonResponse
    {
        return response()->json([
            'data' => $purchases->previewRates(
                CarbonImmutable::parse($request->validated('date') ?? 'today')->startOfDay(),
            ),
        ]);
    }
}
