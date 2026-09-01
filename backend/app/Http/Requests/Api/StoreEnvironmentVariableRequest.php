<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnvironmentVariableRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('key')) {
            $this->merge(['key' => $this->route('key')]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['key' => ['required', 'string', 'max:100', 'regex:/^[A-Z_][A-Z0-9_]*$/'], 'value' => ['required', 'string', 'max:10000'], 'is_secret' => ['sometimes', 'boolean']];
    }
}
