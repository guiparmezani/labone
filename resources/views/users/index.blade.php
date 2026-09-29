@extends('layouts.app')

@section('content')
    <div class="page-head">
        <h1>Usuários</h1>
        <button class="btn btn-primary" type="button" data-abrir="novo-usuario">Novo usuário</button>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Papel</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php($podeEditar = auth()->user()->can('update', $user))
                    <tr @class(['is-clickable' => $podeEditar]) @if ($podeEditar) tabindex="0" data-abrir="editar-usuario-{{ $user->id }}" @endif>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email ?: '—' }}</td>
                        <td>{{ $user->role->label() }}</td>
                        <td>{{ $user->active ? 'Ativa' : 'Inativa' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Nenhum usuário cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <dialog class="lightbox lightbox-form" id="novo-usuario">
        <form method="POST" action="{{ route('usuarios.store') }}" class="stack">
            @csrf
            <input type="hidden" name="lightbox" value="novo-usuario">
            <h2>Novo usuário</h2>
            @include('users._form', ['user' => new \App\Models\User(), 'lightbox' => 'novo-usuario'])
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <button class="btn btn-ghost" type="button" data-fechar>Cancelar</button>
            </div>
        </form>
    </dialog>

    @foreach ($users as $user)
        @continue(! auth()->user()->can('update', $user))
        <dialog class="lightbox lightbox-form" id="editar-usuario-{{ $user->id }}">
            <form method="POST" action="{{ route('usuarios.update', $user) }}" class="stack">
                @csrf
                @method('PUT')
                <input type="hidden" name="lightbox" value="editar-usuario-{{ $user->id }}">
                <h2>Editar usuário</h2>
                @include('users._form', ['user' => $user, 'lightbox' => 'editar-usuario-'.$user->id])
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Salvar</button>
                    <button class="btn btn-ghost" type="button" data-fechar>Cancelar</button>
                </div>
            </form>
        </dialog>
    @endforeach

    @if (old('lightbox'))
        <script>
            document.getElementById(@json(old('lightbox')))?.showModal();
        </script>
    @endif

    <script>
        (function () {
            document.querySelectorAll('[data-abrir]').forEach(function (abrir) {
                function abrirDialogo() {
                    document.getElementById(abrir.getAttribute('data-abrir'))?.showModal();
                }

                abrir.addEventListener('click', function (event) {
                    if (abrir.tagName === 'TR' && event.target.closest('button, a, form, input, select, textarea')) {
                        return;
                    }

                    abrirDialogo();
                });

                abrir.addEventListener('keydown', function (event) {
                    if (event.target !== abrir) {
                        return;
                    }

                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        abrirDialogo();
                    }
                });
            });

            document.querySelectorAll('dialog.lightbox').forEach(function (dialog) {
                var comecouFora = false;

                function noEscuro(event) {
                    var caixa = dialog.getBoundingClientRect();

                    return event.target === dialog && (
                        event.clientX < caixa.left
                        || event.clientX > caixa.right
                        || event.clientY < caixa.top
                        || event.clientY > caixa.bottom
                    );
                }

                dialog.addEventListener('mousedown', function (event) {
                    comecouFora = noEscuro(event);
                });

                dialog.addEventListener('click', function (event) {
                    if (comecouFora && noEscuro(event)) {
                        dialog.close();
                    }

                    comecouFora = false;
                });

                dialog.querySelectorAll('[data-fechar]').forEach(function (fechar) {
                    fechar.addEventListener('click', function () {
                        dialog.close();
                    });
                });
            });
        })();
    </script>
@endsection
