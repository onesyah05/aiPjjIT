<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => [
                'nullable',
                'integer',
                Rule::exists('courses', 'id')->where('status', 'active'),
            ],
            'mode' => ['required', Rule::in(['general', 'knowledge_only'])],
            'title' => ['nullable', 'string', 'max:120'],
        ];
    }
}
