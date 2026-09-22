@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Editar usuário</h1>
        <form method="POST" action="{{ route('usuarios.update', $user) }}" class="stack">
            @csrf
            @method('PUT')
            @include('users._form')
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('usuarios.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
