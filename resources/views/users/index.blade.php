@extends('layouts.app')

@section('content')
    <div class="page-head">
        <h1>Usuários</h1>
        <button class="btn btn-primary" type="button" data-abrir="novo-usuario">Novo usuário</button>
    </div>

    <div class="table-wrap">
        <table class="user-list">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Papel</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php($podeEditar = auth()->user()->can('update', $user))
                    <tr @class(['is-clickable' => $podeEditar]) @if ($podeEditar) tabindex="0" data-abrir="editar-usuario-{{ $user->id }}" @endif>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email ?: '—' }}</td>
                        <td>{{ $user->role->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">Nenhum usuário cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <section class="archived-users">
        <h2>Usuários arquivados</h2>
        <div class="table-wrap">
            <table class="user-list">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Papel</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($archived as $user)
                        @php($podeEditar = auth()->user()->can('update', $user))
                        <tr @class(['is-clickable' => $podeEditar]) @if ($podeEditar) tabindex="0" data-abrir="editar-usuario-{{ $user->id }}" @endif>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email ?: '—' }}</td>
                            <td>{{ $user->role->label() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Nenhum usuário arquivado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <dialog class="lightbox lightbox-form" id="novo-usuario">
        @include('partials.lightbox-fechar')
        <form method="POST" action="{{ route('usuarios.store') }}" class="stack">
            @csrf
            <input type="hidden" name="lightbox" value="novo-usuario">
            <h2>Novo usuário</h2>
            @include('users._form', ['user' => new \App\Models\User(), 'lightbox' => 'novo-usuario'])
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
            </div>
        </form>
    </dialog>

    @foreach ($users->concat($archived) as $user)
        @continue(! auth()->user()->can('update', $user))
        <dialog class="lightbox lightbox-form" id="editar-usuario-{{ $user->id }}">
            @include('partials.lightbox-fechar')
            <form method="POST" action="{{ route('usuarios.update', $user) }}" class="stack">
                @csrf
                @method('PUT')
                <input type="hidden" name="lightbox" value="editar-usuario-{{ $user->id }}">
                <h2>Editar usuário</h2>
                @include('users._form', ['user' => $user, 'lightbox' => 'editar-usuario-'.$user->id])
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Salvar</button>
                    @if ($user->active)
                        <button class="btn btn-danger" type="submit" form="arquivar-usuario-{{ $user->id }}">Arquivar</button>
                    @else
                        <button class="btn btn-ghost" type="submit" form="reativar-usuario-{{ $user->id }}">Reativar</button>
                    @endif
                    @can('delete', $user)
                        <button class="link-danger" type="submit" form="apagar-usuario-{{ $user->id }}">Apagar</button>
                    @endcan
                </div>
            </form>
            @if ($user->active)
                <form id="arquivar-usuario-{{ $user->id }}" method="POST" action="{{ route('usuarios.archive', $user) }}" onsubmit="return confirm('Arquivar este usuário?')" hidden>
                    @csrf
                </form>
            @else
                <form id="reativar-usuario-{{ $user->id }}" method="POST" action="{{ route('usuarios.reactivate', $user) }}" hidden>
                    @csrf
                </form>
            @endif
            @can('delete', $user)
                <form id="apagar-usuario-{{ $user->id }}" method="POST" action="{{ route('usuarios.destroy', $user) }}" data-confirmar="Apagar este usuário?" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @endcan
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
    @include('users._jornada')
@endsection
