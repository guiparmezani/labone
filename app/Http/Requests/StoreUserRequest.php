<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Http\Requests\Concerns\ValidatesUserShift;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ValidatesUserShift;

    public function authorize(): bool
    {
        return $this->user()?->managesProjects() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $email = trim((string) $this->input('email'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => $email === '' ? null : $email,
        ]);
        $this->mergeShift();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120', $this->uniqueName()],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:4'],
            'role' => ['required', Rule::in(array_map(
                fn (Role $role) => $role->value,
                $this->user()->assignableRoles(),
            ))],
            'active' => ['required', 'boolean'],
            ...$this->shiftRules(),
        ];
    }

    private function uniqueName(?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
            if (User::nameTaken((string) $value, $ignoreId)) {
                $fail('Já existe um usuário com este nome.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.min' => 'O nome precisa ter pelo menos 2 caracteres.',
            'name.max' => 'O nome pode ter no máximo 120 caracteres.',
            'name.unique' => 'Já existe um usuário com este nome.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'password.required' => 'Informe a senha.',
            'password.min' => 'A senha precisa ter pelo menos 4 caracteres.',
            'role.required' => 'Escolha o papel.',
            'role.in' => $this->user()?->isAdmin()
                ? 'Escolha o papel.'
                : 'Você não pode definir o papel de administrador.',
            ...$this->shiftMessages(),
        ];
    }
}
