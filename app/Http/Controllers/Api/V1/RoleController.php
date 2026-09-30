<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()
                ->with('permissions')
                ->withCount('users')
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * The full permission list, so the matrix can be drawn without the client
     * having to collect the union of every role's permissions.
     */
    public function permissions(): AnonymousResourceCollection
    {
        return PermissionResource::collection(Permission::query()->orderBy('name')->get());
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $role->syncPermissions($request->validated()['permissions']);

        return RoleResource::make($role->load('permissions')->loadCount('users'));
    }
}
