<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[OA\Tag(name: 'Roles', description: 'Role and permission management endpoints')]
class RoleController extends Controller
{
    #[OA\Get(
        path: '/roles',
        summary: 'List roles',
        description: 'Return a list of all roles with their permissions',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of roles',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection($this->query()->get());
    }

    #[OA\Get(
        path: '/permissions',
        summary: 'List permissions',
        description: 'The full permission list, so the matrix can be drawn without the client having to collect the union of every role\'s permissions',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of permissions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function permissions(): AnonymousResourceCollection
    {
        return PermissionResource::collection(Permission::query()->orderBy('name')->get());
    }

    #[OA\Put(
        path: '/roles/{role}',
        summary: 'Update role permissions',
        description: 'Sync the permissions assigned to a role. A role is addressed by its name, not its id.',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'role',
                in: 'path',
                required: true,
                description: 'Role name',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['permissions'],
                properties: [
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateRoleRequest $request, string $role): RoleResource
    {
        $model = Role::query()->where('name', $role)->first();

        if ($model === null) {
            abort(404, 'Role not found.');
        }

        $model->syncPermissions($request->validated()['permissions']);

        return RoleResource::make($this->query()->findOrFail($model->getKey()));
    }

    /**
     * Roles with their permissions and how many users hold each.
     *
     * The user count is a subselect on the pivot rather than
     * `withCount('users')`, because Spatie's `Role::users()` resolves the related
     * model through the *default* auth guard. Over `/api/*` that guard is
     * `sanctum` (Sanctum's stateful middleware switches to it for a session
     * request), and `config/auth.php` defines no `sanctum` guard, so the relation
     * resolves to null and Eloquent throws "Class name must be a valid object or
     * a string". Counting the pivot directly is guard-agnostic and cheaper.
     */
    private function query(): Builder
    {
        $pivot = config('permission.table_names.model_has_roles', 'model_has_roles');
        $table = (new Role)->getTable();

        return Role::query()
            ->with('permissions')
            // selectSub replaces the implicit `*`, so the table's own columns
            // are asked for explicitly first.
            ->select("{$table}.*")
            ->selectSub(
                DB::table($pivot)
                    ->selectRaw('count(*)')
                    ->whereColumn("{$pivot}.role_id", "{$table}.id")
                    ->where("{$pivot}.model_type", User::class),
                'users_count',
            )
            ->orderBy('name');
    }
}
