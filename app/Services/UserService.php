<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * User accounts and their role assignments.
 *
 * Two invariants live here rather than in the controller: the last administrator
 * cannot be demoted or deleted, and a user cannot strip their own permissions,
 * because either one locks everyone out of the shop with no way back in through
 * the UI.
 */
class UserService
{
    /**
     * @param  array{search?: ?string, role?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = User::query()->with('roles');
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $userQuery) use ($search): void {
                $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $role = trim((string) ($filters['role'] ?? ''));

        if ($role !== '') {
            $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', $role));
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array{name: string, email: string, password: string, roles?: list<string>}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->syncRoles($data['roles'] ?? []);

            return $user->load('roles');
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: ?string, roles?: list<string>}  $data
     */
    public function update(User $user, array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            if (array_key_exists('roles', $data) && $actor?->is($user)) {
                throw ValidationException::withMessages([
                    'roles' => 'You cannot change your own role. Ask another administrator.',
                ]);
            }

            if (array_key_exists('roles', $data) && $user->hasRole('admin') && ! in_array('admin', $data['roles'], true)) {
                $this->guardLastAdministrator($user);
            }

            $attributes = array_filter(
                [
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                ],
                fn (mixed $value) => $value !== null,
            );

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = Hash::make($data['password']);
            }

            $user->update($attributes);

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles']);
            }

            return $user->load('roles');
        });
    }

    public function delete(User $user, ?User $actor = null): void
    {
        if ($actor?->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->hasRole('admin')) {
            $this->guardLastAdministrator($user);
        }

        // A user who created a sale, a rate or a closing stays on the books:
        // those rows carry their id and must keep pointing at somebody.
        if ($user->goldRates()->exists() || $user->hasAnyRole(['admin', 'manager'])) {
            throw ValidationException::withMessages([
                'user' => 'This account has signed off shop records. Change its role instead of deleting it.',
            ]);
        }

        $user->delete();
    }

    /**
     * Refuses to remove the last user who still holds the admin role.
     */
    private function guardLastAdministrator(User $user): void
    {
        $otherAdministrators = User::query()
            ->whereKeyNot($user->getKey())
            ->role('admin')
            ->count();

        if ($otherAdministrators === 0) {
            throw ValidationException::withMessages([
                'roles' => 'The last administrator cannot be demoted. Promote someone else first.',
            ]);
        }
    }
}
