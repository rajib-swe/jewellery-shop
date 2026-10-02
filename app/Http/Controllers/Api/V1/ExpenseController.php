<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Expenses', description: 'Expense management endpoints')]
class ExpenseController extends Controller
{
    #[OA\Get(
        path: '/expenses',
        summary: 'List expenses',
        description: 'Return a paginated list of expenses',
        tags: ['Expenses'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of expenses',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(IndexExpenseRequest $request, ExpenseService $expenses): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $expenses->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return ExpenseResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/expenses',
        summary: 'Create expense',
        description: 'Record a new expense',
        tags: ['Expenses'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category_id', 'amount', 'date'],
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'amount', type: 'number', example: 150.00),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2024-01-15'),
                    new OA\Property(property: 'note', type: 'string', example: 'Office supplies'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Expense recorded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Expense recorded.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreExpenseRequest $request, ExpenseService $expenses): ExpenseResource
    {
        return ExpenseResource::make($expenses->create($request->validated(), $request->user()))->additional([
            'meta' => [
                'message' => 'Expense recorded.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/expenses/{expense}',
        summary: 'Get expense',
        description: 'Return a single expense by ID',
        tags: ['Expenses'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'expense',
                in: 'path',
                required: true,
                description: 'Expense ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Expense details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Expense not found'),
        ]
    )]
    public function show(Expense $expense, ExpenseService $expenses): ExpenseResource
    {
        return ExpenseResource::make($expenses->find($expense));
    }

    #[OA\Put(
        path: '/expenses/{expense}',
        summary: 'Update expense',
        description: 'Update an existing expense',
        tags: ['Expenses'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'expense',
                in: 'path',
                required: true,
                description: 'Expense ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'amount', type: 'number', example: 150.00),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2024-01-15'),
                    new OA\Property(property: 'note', type: 'string', example: 'Office supplies'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Expense updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Expense updated.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Expense not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(
        UpdateExpenseRequest $request,
        Expense $expense,
        ExpenseService $expenses,
    ): ExpenseResource {
        return ExpenseResource::make($expenses->update($expense, $request->validated(), $request->user()))->additional([
            'meta' => [
                'message' => 'Expense updated.',
            ],
        ]);
    }

    #[OA\Delete(
        path: '/expenses/{expense}',
        summary: 'Delete expense',
        description: 'Delete an expense',
        tags: ['Expenses'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'expense',
                in: 'path',
                required: true,
                description: 'Expense ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Expense deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Expense not found'),
        ]
    )]
    public function destroy(Expense $expense, ExpenseService $expenses): Response
    {
        $expenses->delete($expense);

        return response()->noContent();
    }
}
