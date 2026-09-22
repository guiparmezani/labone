@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Novo usuário</h1>
        <form method="POST" action="{{ route('usuarios.store') }}" class="stack">
            @csrf
            @include('users._form', ['user' => new \App\Models\User()])
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('usuarios.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
