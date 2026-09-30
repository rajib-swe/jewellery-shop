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

class UserController extends Controller
{
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

    public function store(StoreUserRequest $request, UserService $users): UserResource
    {
        $user = $users->create($request->validated());

        return UserResource::make($user)->additional([
            'meta' => [
                'message' => 'User created.',
            ],
        ]);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user, UserService $users): UserResource
    {
        $updatedUser = $users->update($user, $request->validated(), $request->user());

        return UserResource::make($updatedUser)->additional([
            'meta' => [
                'message' => 'User updated.',
            ],
        ]);
    }

    public function destroy(Request $request, User $user, UserService $users): Response
    {
        $users->delete($user, $request->user());

        return response()->noContent();
    }
}
