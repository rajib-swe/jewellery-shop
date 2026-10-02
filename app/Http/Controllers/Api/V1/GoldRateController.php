<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexGoldRateRequest;
use App\Http\Requests\StoreGoldRateRequest;
use App\Http\Requests\UpdateGoldRateRequest;
use App\Http\Resources\GoldRateResource;
use App\Models\GoldRate;
use App\Services\GoldRateService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Gold Rates', description: 'Gold rate management endpoints')]
class GoldRateController extends Controller
{
    #[OA\Get(
        path: '/gold-rates',
        summary: 'List gold rates',
        description: 'Return a paginated list of gold rates',
        tags: ['Gold Rates'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of gold rates',
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
        IndexGoldRateRequest $request,
        GoldRateService $goldRates,
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $paginator = $goldRates->paginate(
            $validated,
            $validated['per_page'] ?? 15,
            $validated['page'] ?? 1,
        );

        return GoldRateResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/gold-rates',
        summary: 'Create gold rate',
        description: 'Save a new gold rate entry',
        tags: ['Gold Rates'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['karat', 'purity', 'rate_per_gram'],
                properties: [
                    new OA\Property(property: 'karat', type: 'integer', example: 22),
                    new OA\Property(property: 'purity', type: 'number', example: 0.916),
                    new OA\Property(property: 'rate_per_gram', type: 'number', example: 6500.00),
                    new OA\Property(property: 'effective_date', type: 'string', format: 'date', example: '2024-01-15'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Gold rate saved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Gold rate saved.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(
        StoreGoldRateRequest $request,
        GoldRateService $goldRates,
    ): GoldRateResource {
        $goldRate = $goldRates->create($request->validated(), $request->user());

        return GoldRateResource::make($goldRate)->additional([
            'meta' => [
                'message' => 'Gold rate saved.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/gold-rates/{goldRate}',
        summary: 'Get gold rate',
        description: 'Return a single gold rate by ID',
        tags: ['Gold Rates'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'goldRate',
                in: 'path',
                required: true,
                description: 'Gold rate ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Gold rate details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Gold rate not found'),
        ]
    )]
    public function show(GoldRate $goldRate): GoldRateResource
    {
        return GoldRateResource::make($goldRate->load('createdBy'));
    }

    #[OA\Put(
        path: '/gold-rates/{goldRate}',
        summary: 'Update gold rate',
        description: 'Update an existing gold rate',
        tags: ['Gold Rates'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'goldRate',
                in: 'path',
                required: true,
                description: 'Gold rate ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'karat', type: 'integer', example: 22),
                    new OA\Property(property: 'purity', type: 'number', example: 0.916),
                    new OA\Property(property: 'rate_per_gram', type: 'number', example: 6500.00),
                    new OA\Property(property: 'effective_date', type: 'string', format: 'date', example: '2024-01-15'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Gold rate updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Gold rate not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(
        UpdateGoldRateRequest $request,
        GoldRate $goldRate,
        GoldRateService $goldRates,
    ): GoldRateResource {
        return GoldRateResource::make($goldRates->update($goldRate, $request->validated()));
    }

    #[OA\Delete(
        path: '/gold-rates/{goldRate}',
        summary: 'Delete gold rate',
        description: 'Delete a gold rate entry',
        tags: ['Gold Rates'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'goldRate',
                in: 'path',
                required: true,
                description: 'Gold rate ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Gold rate deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Gold rate not found'),
        ]
    )]
    public function destroy(GoldRate $goldRate, GoldRateService $goldRates): Response
    {
        $goldRates->delete($goldRate);

        return response()->noContent();
    }

    #[OA\Get(
        path: '/gold-rates/latest',
        summary: 'Latest gold rates',
        description: 'Return the most recent gold rates',
        tags: ['Gold Rates'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Latest gold rates',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function latest(GoldRateService $goldRates): AnonymousResourceCollection
    {
        return GoldRateResource::collection($goldRates->latest());
    }
}
