<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\StockSummaryResource;
use App\ItemStatus;
use App\Models\Item;
use App\Services\StockService;
use App\StockMovementType;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Stock', description: 'Stock management endpoints')]
class StockController extends Controller
{
    #[OA\Get(
        path: '/stock/summary',
        summary: 'Stock summary',
        description: 'Return a summary of current stock levels',
        tags: ['Stock'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stock summary',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function summary(StockService $stock): StockSummaryResource
    {
        return StockSummaryResource::make($stock->summary());
    }

    #[OA\Post(
        path: '/items/{item}/stock-adjustments',
        summary: 'Adjust stock',
        description: 'Record a stock adjustment for an item',
        tags: ['Stock'],
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
                required: ['type', 'weight', 'status'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['addition', 'reduction'], example: 'addition'),
                    new OA\Property(property: 'weight', type: 'number', example: 2.5),
                    new OA\Property(property: 'status', type: 'string', enum: ['in_stock', 'sold', 'pawned'], example: 'in_stock'),
                    new OA\Property(property: 'note', type: 'string', example: 'Weight correction'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stock adjustment recorded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Stock adjustment recorded.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Item not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function adjust(
        StockAdjustmentRequest $request,
        Item $item,
        StockService $stock,
    ): ItemResource {
        $validated = $request->validated();
        $updatedItem = $stock->adjust(
            $item,
            $request->user(),
            StockMovementType::from($validated['type']),
            (float) $validated['weight'],
            ItemStatus::from($validated['status']),
            $validated['note'] ?? null,
        );

        return ItemResource::make($updatedItem)->additional([
            'meta' => [
                'message' => 'Stock adjustment recorded.',
            ],
        ]);
    }
}
