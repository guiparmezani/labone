@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Entrar</h1>
        <p class="muted">Use o e-mail e a senha que o administrador passou.</p>

        <form method="POST" action="{{ route('entrar.store') }}" class="stack">
            @csrf
            <label class="field">
                <span>E-mail</span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                @error('email')
                    <small class="error">{{ $message }}</small>
                @enderror
            </label>
            <label class="field">
                <span>Senha</span>
                <input type="password" name="password" autocomplete="current-password" required>
                @error('password')
                    <small class="error">{{ $message }}</small>
                @enderror
            </label>
            <button class="btn btn-primary" type="submit">Entrar</button>
        </form>
    </section>
@endsection
