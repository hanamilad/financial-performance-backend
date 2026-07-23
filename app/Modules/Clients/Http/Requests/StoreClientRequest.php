<?php

namespace App\Modules\Clients\Http\Requests;

use App\Modules\Clients\Enums\EntityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'unique:clients,code'],
            'status' => ['sometimes', Rule::enum(EntityStatus::class)],
        ];
    }
}
