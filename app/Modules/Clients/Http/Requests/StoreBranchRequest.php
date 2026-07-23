<?php

namespace App\Modules\Clients\Http\Requests;

use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Clients\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof Client ? $client->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('branches', 'code')->where('client_id', $clientId),
            ],
            'city' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(EntityStatus::class)],
        ];
    }
}
