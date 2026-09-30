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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection($this->query()->get());
    }

    /**
     * The full permission list, so the matrix can be drawn without the client
     * having to collect the union of every role's permissions.
     */
    public function permissions(): AnonymousResourceCollection
    {
        return PermissionResource::collection(Permission::query()->orderBy('name')->get());
    }

    /**
     * A role is addressed by its name, not its id: the matrix column is the
     * name a shop would say out loud ("cashier"), and the roles table here has
     * no user-facing code. The name is resolved here rather than by implicit
     * binding, which would look the role up by primary key and always 404.
     */
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
