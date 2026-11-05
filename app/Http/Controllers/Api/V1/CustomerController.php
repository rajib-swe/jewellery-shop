<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerHistoryResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Customers', description: 'Customer management endpoints')]
class CustomerController extends Controller
{
    #[OA\Get(
        path: '/customers',
        summary: 'List customers',
        description: 'Return a paginated list of customers',
        tags: ['Customers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of customers',
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
        IndexCustomerRequest $request,
        CustomerService $customers,
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $paginator = $customers->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return CustomerResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/customers',
        summary: 'Create customer',
        description: 'Create a new customer',
        tags: ['Customers'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'phone'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'phone', type: 'string', example: '+1234567890'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'address', type: 'string', example: '123 Main St'),
                    new OA\Property(property: 'notes', type: 'string', example: 'VIP customer'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Customer created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Customer created.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(
        StoreCustomerRequest $request,
        CustomerService $customers,
    ): CustomerResource {
        $photo = $request->file('photo');
        $customer = $customers->create(
            $request->safe()->except(['photo', 'remove_photo']),
            $photo instanceof UploadedFile ? $photo : null,
        );

        return CustomerResource::make($customer)->additional([
            'meta' => [
                'message' => 'Customer created.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/customers/{customer}',
        summary: 'Get customer',
        description: 'Return a single customer by ID',
        tags: ['Customers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'customer',
                in: 'path',
                required: true,
                description: 'Customer ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Customer not found'),
        ]
    )]
    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }

    #[OA\Put(
        path: '/customers/{customer}',
        summary: 'Update customer',
        description: 'Update an existing customer',
        tags: ['Customers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'customer',
                in: 'path',
                required: true,
                description: 'Customer ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'phone', type: 'string', example: '+1234567890'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'address', type: 'string', example: '123 Main St'),
                    new OA\Property(property: 'notes', type: 'string', example: 'VIP customer'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Customer updated.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Customer not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        CustomerService $customers,
    ): CustomerResource {
        $photo = $request->file('photo');
        $updatedCustomer = $customers->update(
            $customer,
            $request->safe()->except(['photo', 'remove_photo']),
            $photo instanceof UploadedFile ? $photo : null,
            $request->boolean('remove_photo'),
        );

        return CustomerResource::make($updatedCustomer)->additional([
            'meta' => [
                'message' => 'Customer updated.',
            ],
        ]);
    }

    #[OA\Delete(
        path: '/customers/{customer}',
        summary: 'Delete customer',
        description: 'Delete a customer',
        tags: ['Customers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'customer',
                in: 'path',
                required: true,
                description: 'Customer ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Customer deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Customer not found'),
        ]
    )]
    public function destroy(Customer $customer, CustomerService $customers): Response
    {
        $customers->delete($customer);

        return response()->noContent();
    }

    #[OA\Get(
        path: '/customers/{customer}/history',
        summary: 'Customer history',
        description: 'Return the transaction history for a customer',
        tags: ['Customers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'customer',
                in: 'path',
                required: true,
                description: 'Customer ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer transaction history',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Customer not found'),
        ]
    )]
    public function history(Customer $customer, CustomerService $customers): CustomerHistoryResource
    {
        return CustomerHistoryResource::make($customers->history($customer));
    }
}
