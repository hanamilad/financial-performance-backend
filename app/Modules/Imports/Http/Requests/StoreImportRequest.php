<?php

namespace App\Modules\Imports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'branch_id' => [
                'required', 'integer',
                Rule::exists('branches', 'id')->where('client_id', $this->input('client_id')),
            ],
            'reporting_period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.exists' => 'الفرع المختار لا يتبع العميل المختار.',
            'reporting_period.regex' => 'صيغة الفترة يجب أن تكون YYYY-MM.',
            'file.mimes' => 'يجب أن يكون الملف بصيغة Excel (xlsx).',
        ];
    }
}
