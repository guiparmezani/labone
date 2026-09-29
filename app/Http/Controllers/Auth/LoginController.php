<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            'users' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(
            [
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'password' => ['required', 'string'],
            ],
            [
                'user_id.required' => 'Escolha seu nome.',
                'user_id.exists' => 'Escolha seu nome.',
                'password.required' => 'Informe a senha.',
            ],
        );

        $key = 'login:'.$credentials['user_id'];

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'user_id' => 'Muitas tentativas. Espere um minuto e tente de novo.',
            ])->status(429);
        }

        $user = User::query()->find($credentials['user_id']);
        $passwordOk = $user && Hash::check($credentials['password'], $user->password);

        // Conta inativa recebe a mesma resposta de senha errada.
        if (! $user || ! $user->active || ! $passwordOk) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'user_id' => 'Não foi possível entrar.',
            ]);
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('entrar');
    }
}
