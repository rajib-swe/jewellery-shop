<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexItemRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Items', description: 'Inventory item management endpoints')]
class ItemController extends Controller
{
    #[OA\Get(
        path: '/items',
        summary: 'List items',
        description: 'Return a paginated list of inventory items',
        tags: ['Items'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['in_stock', 'sold', 'pawned'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of items',
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
        IndexItemRequest $request,
        StockService $stock,
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $paginator = $stock->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return ItemResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/items',
        summary: 'Create item',
        description: 'Add a new item to inventory',
        tags: ['Items'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category_id', 'name', 'weight', 'status'],
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Gold Ring'),
                    new OA\Property(property: 'weight', type: 'number', example: 5.2),
                    new OA\Property(property: 'status', type: 'string', enum: ['in_stock', 'sold', 'pawned'], example: 'in_stock'),
                    new OA\Property(property: 'notes', type: 'string', example: '18K gold'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Item added to inventory',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Item added to inventory.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreItemRequest $request, StockService $stock): ItemResource
    {
        $image = $request->file('image');
        $item = $stock->createInbound(
            $request->safe()->except(['image', 'remove_image']),
            $request->user(),
            $image instanceof UploadedFile ? $image : null,
        );

        return ItemResource::make($item)->additional([
            'meta' => [
                'message' => 'Item added to inventory.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/items/{item}',
        summary: 'Get item',
        description: 'Return a single item by ID',
        tags: ['Items'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'item',
                in: 'path',
                required: true,
                description: 'Item ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Item details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Item not found'),
        ]
    )]
    public function show(Item $item): ItemResource
    {
        return ItemResource::make($item->load('category'));
    }

    #[OA\Put(
        path: '/items/{item}',
        summary: 'Update item',
        description: 'Update an existing inventory item',
        tags: ['Items'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'item',
                in: 'path',
                required: true,
                description: 'Item ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Gold Ring'),
                    new OA\Property(property: 'weight', type: 'number', example: 5.2),
                    new OA\Property(property: 'status', type: 'string', enum: ['in_stock', 'sold', 'pawned'], example: 'in_stock'),
                    new OA\Property(property: 'notes', type: 'string', example: '18K gold'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Item updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Item updated.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Item not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(
        UpdateItemRequest $request,
        Item $item,
        StockService $stock,
    ): ItemResource {
        $image = $request->file('image');
        $updatedItem = $stock->update(
            $item,
            $request->safe()->except(['image', 'remove_image']),
            $request->user(),
            $image instanceof UploadedFile ? $image : null,
            $request->boolean('remove_image'),
        );

        return ItemResource::make($updatedItem)->additional([
            'meta' => [
                'message' => 'Item updated.',
            ],
        ]);
    }

    #[OA\Delete(
        path: '/items/{item}',
        summary: 'Delete item',
        description: 'Delete an inventory item',
        tags: ['Items'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'item',
                in: 'path',
                required: true,
                description: 'Item ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Item deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Item not found'),
        ]
    )]
    public function destroy(Item $item, StockService $stock): Response
    {
        $stock->delete($item);

        return response()->noContent();
    }
}
