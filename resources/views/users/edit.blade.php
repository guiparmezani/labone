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
        <div class="actions">
            @if ($user->active)
                <button class="btn btn-danger" type="submit" form="arquivar-usuario">Arquivar</button>
            @else
                <button class="btn btn-ghost" type="submit" form="reativar-usuario">Reativar</button>
            @endif
            @can('delete', $user)
                <button class="link-danger" type="submit" form="apagar-usuario">Apagar</button>
            @endcan
        </div>
        @if ($user->active)
            <form id="arquivar-usuario" method="POST" action="{{ route('usuarios.archive', $user) }}" onsubmit="return confirm('Arquivar este usuário?')" hidden>
                @csrf
            </form>
        @else
            <form id="reativar-usuario" method="POST" action="{{ route('usuarios.reactivate', $user) }}" hidden>
                @csrf
            </form>
        @endif
        @can('delete', $user)
            <form id="apagar-usuario" method="POST" action="{{ route('usuarios.destroy', $user) }}" data-confirmar="Apagar este usuário?" hidden>
                @csrf
                @method('DELETE')
            </form>
        @endcan
    </section>
    @include('users._jornada')
@endsection
