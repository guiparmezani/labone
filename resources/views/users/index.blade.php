@extends('layouts.app')

@section('content')
    <div class="page-head">
        <h1>Usuários</h1>
        <a class="btn btn-primary" href="{{ route('usuarios.create') }}">Novo usuário</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Papel</th>
                    <th>Situação</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role->label() }}</td>
                        <td>{{ $user->active ? 'Ativa' : 'Inativa' }}</td>
                        <td class="cell-end"><a href="{{ route('usuarios.edit', $user) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Nenhum usuário cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
