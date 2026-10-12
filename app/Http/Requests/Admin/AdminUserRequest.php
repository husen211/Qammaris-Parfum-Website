<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.manage');
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $roles = User::ASSIGNABLE_ROLES;
        // The legacy role may be kept on an existing legacy account, never newly granted.
        if ($target instanceof User && $target->role === User::ROLE_LEGACY_ADMIN) {
            $roles[] = User::ROLE_LEGACY_ADMIN;
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['nullable', 'required_without:username', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'username' => ['nullable', 'required_without:email', 'regex:/^[a-z0-9._-]{3,40}$/', Rule::unique('users', 'username')->ignore($target?->id)],
            'role' => ['required', Rule::in($roles)],
            'password' => $target ? ['prohibited'] : ['nullable', Password::min(8)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['name', 'email', 'username'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $values[$field] = $value === '' ? null : ($field === 'username' ? strtolower($value) : $value);
            }
        }
        $this->merge($values);
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'email' => 'email', 'username' => 'username', 'role' => 'role', 'password' => 'password sementara'];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Isi email atau username (minimal salah satu).',
            'username.required_without' => 'Isi email atau username (minimal salah satu).',
            'username.regex' => 'Username 3–40 karakter: huruf kecil, angka, titik, minus, atau garis bawah.',
            'unique' => ':attribute sudah dipakai akun lain.',
        ];
    }
}
