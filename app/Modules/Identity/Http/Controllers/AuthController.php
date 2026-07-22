<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * System-admin authentication over Sanctum stateful cookies (AUTH-001).
 *
 * This controller only opens and closes a first-party session for the admin
 * web panel; mobile bearer-token login is a separate slice (AUTH-002) and no
 * token is issued here.
 */
class AuthController extends Controller
{
    /**
     * Only `system_admin` accounts may sign in here. Folding the role into the
     * credential lookup makes an unknown email, a wrong password and a
     * `client_user` account all fail identically, so the response never reveals
     * which condition was hit.
     */
    public function login(LoginRequest $request): AuthenticatedUserResource
    {
        $credentials = [
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => UserRole::SystemAdmin->value,
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // A fresh session ID for the now-authenticated session closes the
        // session-fixation window opened before login.
        $request->session()->regenerate();

        return new AuthenticatedUserResource($request->user());
    }

    public function me(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        // Rotate the CSRF token so the retired session's token cannot be reused.
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
