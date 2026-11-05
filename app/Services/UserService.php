<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

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

        // A user who has signed off shop records stays on the books: sales,
        // payments, pawns and closings all carry their id, and a deleted user
        // would leave those rows pointing at nobody.
        if ($user->hasAnyRole(['admin', 'manager']) || $this->hasRecordedActivity($user)) {
            throw ValidationException::withMessages([
                'user' => 'This account has signed off shop records. Change its role instead of deleting it.',
            ]);
        }

        $user->delete();
    }

    /**
     * Whether the user has ever caused an activity log entry.
     *
     * The audit trail is the record of who touched what, so it is the one place
     * to ask: every model that keeps a user id logs itself, which means this
     * catches a sale, a rate, a payment or a closing without this service
     * having to know about each of those tables.
     */
    private function hasRecordedActivity(User $user): bool
    {
        return Activity::query()
            ->where('causer_type', $user->getMorphClass())
            ->where('causer_id', $user->getKey())
            ->exists();
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
