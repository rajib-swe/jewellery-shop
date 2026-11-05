<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSupplierRequest;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierLedgerResource;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Suppliers', description: 'Supplier management endpoints')]
class SupplierController extends Controller
{
    #[OA\Get(
        path: '/suppliers',
        summary: 'List suppliers',
        description: 'Return a paginated list of suppliers',
        tags: ['Suppliers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of suppliers',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(IndexSupplierRequest $request, SupplierService $suppliers): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $suppliers->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return SupplierResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/suppliers',
        summary: 'Create supplier',
        description: 'Create a new supplier',
        tags: ['Suppliers'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'phone'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Gold Suppliers Inc.'),
                    new OA\Property(property: 'phone', type: 'string', example: '+1234567890'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'supplier@example.com'),
                    new OA\Property(property: 'address', type: 'string', example: '456 Market St'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Primary gold supplier'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Supplier created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Supplier created.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreSupplierRequest $request, SupplierService $suppliers): SupplierResource
    {
        return SupplierResource::make($suppliers->create($request->validated()))->additional([
            'meta' => [
                'message' => 'Supplier created.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/suppliers/{supplier}',
        summary: 'Get supplier',
        description: 'Return a single supplier by ID',
        tags: ['Suppliers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'supplier',
                in: 'path',
                required: true,
                description: 'Supplier ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Supplier details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Supplier not found'),
        ]
    )]
    public function show(Supplier $supplier, SupplierService $suppliers): SupplierResource
    {
        return SupplierResource::make($suppliers->find($supplier));
    }

    #[OA\Put(
        path: '/suppliers/{supplier}',
        summary: 'Update supplier',
        description: 'Update an existing supplier',
        tags: ['Suppliers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'supplier',
                in: 'path',
                required: true,
                description: 'Supplier ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Gold Suppliers Inc.'),
                    new OA\Property(property: 'phone', type: 'string', example: '+1234567890'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'supplier@example.com'),
                    new OA\Property(property: 'address', type: 'string', example: '456 Market St'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Primary gold supplier'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Supplier updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Supplier updated.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Supplier not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier,
        SupplierService $suppliers,
    ): SupplierResource {
        return SupplierResource::make($suppliers->update($supplier, $request->validated()))->additional([
            'meta' => [
                'message' => 'Supplier updated.',
            ],
        ]);
    }

    #[OA\Delete(
        path: '/suppliers/{supplier}',
        summary: 'Delete supplier',
        description: 'Delete a supplier',
        tags: ['Suppliers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'supplier',
                in: 'path',
                required: true,
                description: 'Supplier ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Supplier deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Supplier not found'),
        ]
    )]
    public function destroy(Supplier $supplier, SupplierService $suppliers): Response
    {
        $suppliers->delete($supplier);

        return response()->noContent();
    }

    #[OA\Get(
        path: '/suppliers/{supplier}/ledger',
        summary: 'Supplier ledger',
        description: 'Return the transaction ledger for a supplier',
        tags: ['Suppliers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'supplier',
                in: 'path',
                required: true,
                description: 'Supplier ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Supplier ledger',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Supplier not found'),
        ]
    )]
    public function ledger(Supplier $supplier, SupplierService $suppliers): SupplierLedgerResource
    {
        return SupplierLedgerResource::make([
            'supplier' => $supplier,
            'ledger' => $suppliers->ledger($supplier),
        ]);
    }

    #[OA\Post(
        path: '/suppliers/{supplier}/payments',
        summary: 'Add supplier payment',
        description: 'Record a payment to a supplier',
        tags: ['Suppliers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'supplier',
                in: 'path',
                required: true,
                description: 'Supplier ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'method'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', example: 10000.00),
                    new OA\Property(property: 'method', type: 'string', enum: ['cash', 'card', 'transfer'], example: 'transfer'),
                    new OA\Property(property: 'note', type: 'string', example: 'Purchase payment'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Payment recorded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Payment recorded.'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Supplier not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function addPayment(
        StoreSupplierPaymentRequest $request,
        Supplier $supplier,
        PurchaseService $purchases,
        SupplierService $suppliers,
    ): JsonResponse {
        $purchases->addPayment($supplier, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Payment recorded.',
            'data' => SupplierResource::make($suppliers->find($supplier))->resolve($request),
        ], 201);
    }
}
