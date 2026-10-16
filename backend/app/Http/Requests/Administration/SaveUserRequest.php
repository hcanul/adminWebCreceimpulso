<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($this->route('userId'))],
            'password' => [$this->route('userId') ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'is_active' => ['required', 'boolean'],
            'branch_id' => ['nullable', 'uuid', Rule::exists('branches', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
