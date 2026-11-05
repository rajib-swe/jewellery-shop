<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Users', description: 'User management endpoints')]
class UserController extends Controller
{
    #[OA\Get(
        path: '/users',
        summary: 'List users',
        description: 'Return a paginated list of users',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(IndexUserRequest $request, UserService $users): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $users->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return UserResource::collection($paginator->withQueryString());
    }

    #[OA\Post(
        path: '/users',
        summary: 'Create user',
        description: 'Create a new user',
        tags: ['Users'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'roles'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jane Smith'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'User created.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreUserRequest $request, UserService $users): UserResource
    {
        $user = $users->create($request->validated());

        return UserResource::make($user)->additional([
            'meta' => [
                'message' => 'User created.',
            ],
        ]);
    }

    #[OA\Get(
        path: '/users/{user}',
        summary: 'Get user',
        description: 'Return a single user by ID',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'user',
                in: 'path',
                required: true,
                description: 'User ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function show(User $user): UserResource
    {
        return UserResource::make($user->load('roles'));
    }

    #[OA\Put(
        path: '/users/{user}',
        summary: 'Update user',
        description: 'Update an existing user',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'user',
                in: 'path',
                required: true,
                description: 'User ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jane Smith'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'User updated.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateUserRequest $request, User $user, UserService $users): UserResource
    {
        $updatedUser = $users->update($user, $request->validated(), $request->user());

        return UserResource::make($updatedUser)->additional([
            'meta' => [
                'message' => 'User updated.',
            ],
        ]);
    }

    #[OA\Delete(
        path: '/users/{user}',
        summary: 'Delete user',
        description: 'Delete a user',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'user',
                in: 'path',
                required: true,
                description: 'User ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function destroy(Request $request, User $user, UserService $users): Response
    {
        $users->delete($user, $request->user());

        return response()->noContent();
    }
}
