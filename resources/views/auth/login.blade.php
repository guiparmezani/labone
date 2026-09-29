@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Entrar</h1>
        <p class="muted">Escolha seu nome e digite a senha.</p>

        <form method="POST" action="{{ route('entrar.store') }}" class="stack">
            @csrf
            <label class="field">
                <span>Nome</span>
                <select name="user_id" required autofocus>
                    <option value="">Escolha seu nome</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('user_id')
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
