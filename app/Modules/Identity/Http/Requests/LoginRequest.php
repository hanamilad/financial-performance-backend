<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the system-admin cookie login payload. Only the shape is checked
 * here; whether the credentials are correct — and whether the account is a
 * system admin — is decided in the controller so the failure stays generic and
 * never reveals which part was wrong.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
