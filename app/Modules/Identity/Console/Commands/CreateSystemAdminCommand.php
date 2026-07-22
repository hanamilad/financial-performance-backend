<?php

namespace App\Modules\Identity\Console\Commands;

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the first (or any further) system administrator interactively.
 *
 * There is deliberately no seeder that ships credentials: the only way to
 * create an admin is to type the details here. The password is entered blind,
 * confirmed, hashed, and never echoed or logged.
 */
class CreateSystemAdminCommand extends Command
{
    protected $signature = 'identity:create-system-admin';

    protected $description = 'Create a system administrator account interactively';

    public function handle(): int
    {
        $name = text(
            label: 'Name',
            required: true,
            validate: fn (string $value): ?string => $this->fieldError('name', $value, ['required', 'string', 'max:255']),
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $value): ?string => $this->fieldError('email', $value, ['required', 'string', 'email', 'max:255', 'unique:users,email']),
        );

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value): ?string => $this->fieldError('password', $value, ['required', 'string', Password::min(8)]),
        );

        $confirmation = password(
            label: 'Confirm password',
            required: true,
        );

        if (! hash_equals($password, $confirmation)) {
            $this->components->error('The passwords do not match.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::SystemAdmin,
        ]);

        $this->components->info("System administrator [{$email}] created.");

        return self::SUCCESS;
    }

    /**
     * Validate a single value and return the first error message, or null when
     * it passes — the shape Laravel Prompts expects from a validate closure.
     *
     * @param  array<int, mixed>  $rules
     */
    private function fieldError(string $attribute, string $value, array $rules): ?string
    {
        $validator = Validator::make([$attribute => $value], [$attribute => $rules]);

        return $validator->fails()
            ? (string) $validator->errors()->first($attribute)
            : null;
    }
}
