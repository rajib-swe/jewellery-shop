<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSaleRequest;
use App\Http\Requests\StoreSalePaymentRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\VoidSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Sales', description: 'Sale management endpoints')]
class SaleController extends Controller
{
    #[OA\Get(
        path: '/sales',
        summary: 'List sales',
        description: 'Return a paginated list of sales',
        tags: ['Sales'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'customer_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of sales',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(IndexSaleRequest $request, SaleService $sales): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $sales->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return SaleResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/sales',
        summary: 'Create sale',
        description: 'Record a new sale',
        tags: ['Sales'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['customer_id', 'items', 'total', 'payment_method'],
                properties: [
                    new OA\Property(property: 'customer_id', type: 'integer', example: 1),
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'total', type: 'number', example: 15000.00),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'card', 'transfer'], example: 'cash'),
                    new OA\Property(property: 'sale_date', type: 'string', format: 'date', example: '2024-01-15'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Ring sale'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Sale recorded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Sale recorded.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreSaleRequest $request, SaleService $sales): SaleResource
    {
        $sale = $sales->create(
            $request->validated(),
            $request->user(),
            $request->user()->can('manage gold rates'),
        );

        return SaleResource::make($sale)->additional([
            'meta' => [
                'message' => 'Sale recorded.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/sales/{sale}',
        summary: 'Get sale',
        description: 'Return a single sale by ID',
        tags: ['Sales'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sale',
                in: 'path',
                required: true,
                description: 'Sale ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sale details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Sale not found'),
        ]
    )]
    public function show(Sale $sale, SaleService $sales): SaleResource
    {
        return SaleResource::make($sales->find($sale));
    }

    #[OA\Post(
        path: '/sales/{sale}/payments',
        summary: 'Add sale payment',
        description: 'Record a payment against a sale',
        tags: ['Sales'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sale',
                in: 'path',
                required: true,
                description: 'Sale ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'method'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', example: 5000.00),
                    new OA\Property(property: 'method', type: 'string', enum: ['cash', 'card', 'transfer'], example: 'cash'),
                    new OA\Property(property: 'note', type: 'string', example: 'Partial payment'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment recorded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Payment recorded.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Sale not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function addPayment(
        StoreSalePaymentRequest $request,
        Sale $sale,
        SaleService $sales,
    ): SaleResource {
        $updatedSale = $sales->addPayment($sale, $request->validated(), $request->user());

        return SaleResource::make($updatedSale)->additional([
            'meta' => [
                'message' => 'Payment recorded.',
            ],
        ]);
    }

    #[OA\Post(
        path: '/sales/{sale}/void',
        summary: 'Void sale',
        description: 'Void an existing sale',
        tags: ['Sales'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sale',
                in: 'path',
                required: true,
                description: 'Sale ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Customer cancelled'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sale voided',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Sale voided.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Sale not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function void(VoidSaleRequest $request, Sale $sale, SaleService $sales): SaleResource
    {
        $voidedSale = $sales->void($sale, $request->user(), $request->validated('reason'));

        return SaleResource::make($voidedSale)->additional([
            'meta' => [
                'message' => 'Sale voided.',
            ],
        ]);
    }
}
