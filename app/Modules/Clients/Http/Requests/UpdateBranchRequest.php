<?php

namespace App\Modules\Clients\Http\Requests;

use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Clients\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
        $branch = $this->route('branch');
        $clientId = $branch instanceof Branch ? $branch->client_id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('branches', 'code')
                    ->where('client_id', $clientId)
                    ->ignore($branch),
            ],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::enum(EntityStatus::class)],
        ];
    }
}
