<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'groups_count' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'groups_count.required' => 'يرجى إدخال عدد المجموعات المطلوب.',
            'groups_count.integer' => 'عدد المجموعات يجب أن يكون رقمًا صحيحًا.',
            'groups_count.min' => 'عدد المجموعات يجب أن يكون واحدًا على الأقل.',
        ];
    }
}
