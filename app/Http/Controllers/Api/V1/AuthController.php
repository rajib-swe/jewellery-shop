<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * The key the `login` limiter counts against, shared with the limiter
     * definition in AppServiceProvider so a successful login can clear it.
     */
    private function loginKey(Request $request): string
    {
        return 'login:'.$request->ip().'|'.$request->input('email');
    }

    public function login(LoginRequest $request): UserResource
    {
        $credentials = $request->validated();

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // A successful sign-in clears the allowance, so a cashier who fumbled
        // their password a few times is not then locked out of a clean attempt.
        RateLimiter::clear($this->loginKey($request));

        $user = $request->user()->loadMissing(['roles', 'roles.permissions', 'permissions']);

        return UserResource::make($user)->additional([
            'meta' => [
                'message' => 'Authenticated.',
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->loadMissing(['roles', 'permissions']));
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        } else {
            Auth::guard('web')->logout();
            Auth::forgetGuards();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
