<?php

namespace App\Modules\Imports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnToDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'review_note.required' => 'ملاحظة الإرجاع مطلوبة.',
            'review_note.max' => 'ملاحظة الإرجاع طويلة جدًا.',
        ];
    }
}
