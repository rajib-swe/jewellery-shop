<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForfeitPawnRequest;
use App\Http\Requests\IndexPawnRequest;
use App\Http\Requests\RedeemPawnRequest;
use App\Http\Requests\RenewPawnRequest;
use App\Http\Requests\StorePawnPaymentRequest;
use App\Http\Requests\StorePawnRequest;
use App\Http\Resources\PawnResource;
use App\Models\Pawn;
use App\Services\PawnService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Pawns', description: 'Pawn management endpoints')]
class PawnController extends Controller
{
    #[OA\Get(
        path: '/pawns',
        summary: 'List pawns',
        description: 'Return a paginated list of pawn transactions',
        tags: ['Pawns'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'redeemed', 'forfeited', 'overdue'])),
            new OA\Parameter(name: 'customer_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of pawns',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(IndexPawnRequest $request, PawnService $pawns): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $pawns->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return PawnResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/pawns',
        summary: 'Create pawn',
        description: 'Record a new pawn transaction',
        tags: ['Pawns'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['customer_id', 'items', 'principal', 'interest_rate', 'due_date'],
                properties: [
                    new OA\Property(property: 'customer_id', type: 'integer', example: 1),
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'principal', type: 'number', example: 5000.00),
                    new OA\Property(property: 'interest_rate', type: 'number', example: 3.5),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2024-02-15'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Gold items pawned'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pawn recorded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Pawn recorded.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StorePawnRequest $request, PawnService $pawns): PawnResource
    {
        $items = (array) $request->input('items', []);
        $photos = [];

        foreach ($items as $index => $item) {
            $photo = is_array($item) ? ($item['photo'] ?? null) : null;

            if ($photo instanceof UploadedFile) {
                $photos[$index] = $photo;
            }
        }

        $pawn = $pawns->create(
            $request->safe()->except(['items', 'items.*.photo']),
            $request->user(),
            $photos,
        );

        return PawnResource::make($pawn)->additional([
            'meta' => [
                'message' => 'Pawn recorded.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/pawns/{pawn}',
        summary: 'Get pawn',
        description: 'Return a single pawn by ID',
        tags: ['Pawns'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'pawn',
                in: 'path',
                required: true,
                description: 'Pawn ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pawn details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Pawn not found'),
        ]
    )]
    public function show(Pawn $pawn, PawnService $pawns): PawnResource
    {
        return PawnResource::make($pawns->find($pawn));
    }

    #[OA\Post(
        path: '/pawns/{pawn}/payments',
        summary: 'Add pawn payment',
        description: 'Record a payment against a pawn',
        tags: ['Pawns'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'pawn',
                in: 'path',
                required: true,
                description: 'Pawn ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'method'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', example: 500.00),
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
            new OA\Response(response: 404, description: 'Pawn not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function addPayment(
        StorePawnPaymentRequest $request,
        Pawn $pawn,
        PawnService $pawns,
    ): PawnResource {
        $updatedPawn = $pawns->addPayment($pawn, $request->validated(), $request->user());

        return PawnResource::make($updatedPawn)->additional([
            'meta' => [
                'message' => 'Payment recorded.',
            ],
        ]);
    }

    #[OA\Post(
        path: '/pawns/{pawn}/redeem',
        summary: 'Redeem pawn',
        description: 'Redeem a pawned item',
        tags: ['Pawns'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'pawn',
                in: 'path',
                required: true,
                description: 'Pawn ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['payment_method'],
                properties: [
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'card', 'transfer'], example: 'cash'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pawn redeemed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Pawn redeemed.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Pawn not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function redeem(RedeemPawnRequest $request, Pawn $pawn, PawnService $pawns): PawnResource
    {
        $redeemedPawn = $pawns->redeem($pawn, $request->validated(), $request->user());

        return PawnResource::make($redeemedPawn)->additional([
            'meta' => [
                'message' => 'Pawn redeemed.',
            ],
        ]);
    }

    #[OA\Post(
        path: '/pawns/{pawn}/renew',
        summary: 'Renew pawn',
        description: 'Renew/extend a pawn term',
        tags: ['Pawns'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'pawn',
                in: 'path',
                required: true,
                description: 'Pawn ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['new_due_date'],
                properties: [
                    new OA\Property(property: 'new_due_date', type: 'string', format: 'date', example: '2024-03-15'),
                    new OA\Property(property: 'interest_paid', type: 'number', example: 175.00),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pawn renewed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Pawn renewed.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Pawn not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function renew(RenewPawnRequest $request, Pawn $pawn, PawnService $pawns): PawnResource
    {
        $renewedPawn = $pawns->renew($pawn, $request->validated(), $request->user());

        return PawnResource::make($renewedPawn)->additional([
            'meta' => [
                'message' => 'Pawn renewed.',
            ],
        ]);
    }

    #[OA\Post(
        path: '/pawns/{pawn}/forfeit',
        summary: 'Forfeit pawn',
        description: 'Forfeit a pawned item',
        tags: ['Pawns'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'pawn',
                in: 'path',
                required: true,
                description: 'Pawn ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Customer defaulted'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pawn forfeited',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Pawn forfeited.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Pawn not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function forfeit(ForfeitPawnRequest $request, Pawn $pawn, PawnService $pawns): PawnResource
    {
        $forfeitedPawn = $pawns->forfeit($pawn, $request->validated(), $request->user());

        return PawnResource::make($forfeitedPawn)->additional([
            'meta' => [
                'message' => 'Pawn forfeited.',
            ],
        ]);
    }
}
