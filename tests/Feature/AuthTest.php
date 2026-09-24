<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_valid_credentials(): void
    {
        $this->postJson('/api/v1/login')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_log_in_and_keep_the_session(): void
    {
        $user = $this->createUserWithAccessPermission();

        $this->withHeader('Referer', config('app.url'))->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles.0', 'cashier')
            ->assertJsonPath('data.permissions.0', 'access api');

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->createUserWithAccessPermission();

        $this->withHeader('Referer', config('app.url'))->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_user_can_log_out(): void
    {
        $user = $this->createUserWithAccessPermission();

        $this->actingAs($user)
            ->withHeader('Referer', config('app.url'))
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertGuest();
    }

    public function test_user_without_permission_can_still_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Referer', config('app.url'))
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->assertGuest();
    }

    public function test_logout_revokes_the_active_bearer_token(): void
    {
        $user = $this->createUserWithAccessPermission();
        $accessToken = $user->createToken('test-client');
        $tokenId = $accessToken->accessToken->getKey();

        $this->withToken($accessToken->plainTextToken)
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    private function createUserWithAccessPermission(): User
    {
        $permission = Permission::create([
            'name' => 'access api',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'cashier',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();

        $user->assignRole($role);

        return $user;
    }
}
