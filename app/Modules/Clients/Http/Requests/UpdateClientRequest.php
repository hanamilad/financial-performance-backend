<?php

namespace App\Modules\Clients\Http\Requests;

use App\Modules\Clients\Enums\EntityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('clients', 'code')->ignore($this->route('client')),
            ],
            'status' => ['sometimes', 'required', Rule::enum(EntityStatus::class)],
        ];
    }
}
