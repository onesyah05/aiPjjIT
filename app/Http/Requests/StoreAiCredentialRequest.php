<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAiCredentialRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:80'],
            'secret' => ['required', 'string', 'min:20', 'max:512'],
            'daily_request_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'community_enabled' => ['required', 'boolean'],
            'consent' => ['accepted'],
        ];
    }
}
