<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('users.index', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => Role::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create($request->safe()->only(['name', 'email', 'password', 'role', 'active']));

        return redirect()->route('usuarios.index')->with('status', 'Usuário criado.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'password', 'role', 'active']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $this->guardLastAdmin($user, Role::from($data['role']), (bool) $data['active']);

        $user->update($data);

        return redirect()->route('usuarios.index')->with('status', 'Usuário atualizado.');
    }

    /**
     * O sistema precisa de um administrador ativo para redefinir senhas.
     */
    private function guardLastAdmin(User $user, Role $newRole, bool $active): void
    {
        $removesAdmin = $user->isAdmin() && ($newRole !== Role::Admin || ! $active);

        if (! $removesAdmin) {
            return;
        }

        $anotherAdmin = User::query()
            ->where('role', Role::Admin)
            ->where('active', true)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $anotherAdmin) {
            throw ValidationException::withMessages([
                'role' => 'Precisa existir pelo menos um administrador ativo.',
            ]);
        }
    }
}
