<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('knowledge')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sharedVisibility = $this->input('context') === 'course' ? 'course' : 'community';

        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'context' => ['required', Rule::in(['general', 'course'])],
            'course_id' => ['exclude_if:context,general', 'required_if:context,course', 'nullable', 'integer', Rule::exists('courses', 'id')->where('status', 'active')],
            'visibility' => ['required', Rule::in(['private', $sharedVisibility])],
            'content' => ['nullable', 'string', 'max:2000000'],
            'file' => ['nullable', 'file', 'extensions:md', 'mimetypes:text/plain,text/markdown', 'max:2048'],
        ];
    }
}
