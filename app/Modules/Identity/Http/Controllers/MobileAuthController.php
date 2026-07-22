<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Http\Requests\MobileLoginRequest;
use App\Modules\Identity\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    /**
     * Only `client_user` accounts may sign in from mobile. An unknown email, a
     * wrong password and a `system_admin` account all fail with the same generic
     * error so the response never reveals which condition was hit. The plaintext
     * token is returned exactly once here and is never persisted or logged.
     */
    public function login(MobileLoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->where('role', UserRole::ClientUser->value)
            ->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($request->validated('device_name'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => (new AuthenticatedUserResource($user))->resolve($request),
        ]);
    }

    public function me(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        // Revoke only the token that made this request, leaving the user's other
        // devices signed in.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
