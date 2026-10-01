<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\TimeClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $todos = User::query()->orderBy('name')->get();

        return view('users.index', [
            'users' => $todos->where('active', true)->values(),
            'archived' => $todos->where('active', false)->values(),
            'roles' => auth()->user()->assignableRoles(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => auth()->user()->assignableRoles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create($request->safe()->only([
            'name', 'email', 'password', 'role', 'active',
            'hourly_rate_cents', 'shift_start', 'shift_end', 'shift_afternoon_start', 'shift_afternoon_end',
        ]));

        return redirect()->route('usuarios.index')->with('status', 'Usuário criado.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => auth()->user()->assignableRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only([
            'name', 'email', 'password', 'role', 'active',
            'hourly_rate_cents', 'shift_start', 'shift_end', 'shift_afternoon_start', 'shift_afternoon_end',
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $active = array_key_exists('active', $data) ? (bool) $data['active'] : $user->active;
        $this->guardLastAdmin($user, Role::from($data['role']), $active);

        $user->update($data);

        if (! $user->active) {
            app(TimeClock::class)->closeOpen($user, $request->user());
        }

        return redirect()->route('usuarios.index')->with('status', 'Usuário atualizado.');
    }

    public function archive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        try {
            $this->guardLastAdmin($user, $user->role, false);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('usuarios.index')
                ->with('status', $exception->validator->errors()->first());
        }

        if ($user->active) {
            $user->update(['active' => false]);
            app(TimeClock::class)->closeOpen($user, $request->user());
        }

        return redirect()->route('usuarios.index')->with('status', 'Usuário arquivado.');
    }

    public function reactivate(User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $user->update(['active' => true]);

        return redirect()->route('usuarios.index')->with('status', 'Usuário reativado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->guardLastAdmin($user, $user->role, false);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('usuarios.index')
                ->with('status', $exception->validator->errors()->first());
        }

        if ($user->timeLogs()->exists()) {
            return redirect()
                ->route('usuarios.index')
                ->with('status', 'Esta pessoa tem lançamentos. Arquive a conta em vez de apagar.');
        }

        $adminId = $request->user()->id;

        DB::transaction(function () use ($user, $adminId): void {
            Project::query()->where('created_by', $user->id)->update(['created_by' => $adminId]);
            Subtask::query()->where('created_by', $user->id)->update(['created_by' => $adminId]);
            TimeLog::query()->where('created_by', $user->id)->update(['created_by' => $adminId]);
            TimeLog::query()->where('updated_by', $user->id)->update(['updated_by' => $adminId]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return redirect()->route('usuarios.index')->with('status', 'Usuário apagado.');
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
