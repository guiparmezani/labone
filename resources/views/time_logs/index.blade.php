@extends('layouts.app')

@section('content')
    <div class="page-head">
        <h1>Lançamentos</h1>
        @if (auth()->user()->isAdmin())
            <a class="btn btn-primary" href="{{ route('lancamentos.create') }}">Novo lançamento</a>
        @endif
    </div>

    <form method="GET" action="{{ route('lancamentos.index') }}" class="panel filter-bar">
        <label class="field">
            <span>Projeto</span>
            <select name="project_id">
                <option value="">Todos</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) $filters['project_id'] === (string) $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Pessoa</span>
            <select name="user_id">
                <option value="">Todas</option>
                @foreach ($users as $person)
                    <option value="{{ $person->id }}" @selected((string) $filters['user_id'] === (string) $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>De</span>
            @include('partials.calendario', ['nome' => 'from', 'valor' => $filters['from']])
        </label>
        <label class="field">
            <span>Até</span>
            @include('partials.calendario', ['nome' => 'to', 'valor' => $filters['to']])
        </label>
        <button class="btn btn-ghost" type="submit">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pessoa</th>
                    <th>Projeto</th>
                    <th>Tarefa</th>
                    <th>Início</th>
                    <th>Duração</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php($podeEditar = auth()->user()->can('update', $log))
                    <tr @class(['is-clickable' => $podeEditar]) @if ($podeEditar) tabindex="0" data-abrir="editar-lancamento-{{ $log->id }}" @endif>
                        <td>{{ $log->user->name }}</td>
                        <td>{{ $log->subtask->project->name }}</td>
                        <td>{{ $log->subtask->name }}</td>
                        <td>{{ \App\Support\Formato::dataHora($log->started_at) }}</td>
                        <td>{{ $log->minutes() === null ? 'Em andamento' : \App\Support\Formato::minutos($log->minutes()) }}</td>
                        <td class="cell-end">
                            @can('delete', $log)
                                <form method="POST" action="{{ route('lancamentos.destroy', $log) }}" style="display:inline" onsubmit="return confirm('Apagar este lançamento?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger" type="submit">Apagar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum lançamento neste período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach ($logs as $log)
        @continue(! auth()->user()->can('update', $log))
        <dialog class="lightbox lightbox-form lightbox-calendario" id="editar-lancamento-{{ $log->id }}">
            <form method="POST" action="{{ route('lancamentos.update', $log) }}" class="stack">
                @csrf
                @method('PUT')
                <input type="hidden" name="lightbox" value="editar-lancamento-{{ $log->id }}">
                <h2>Editar lançamento</h2>
                @include('time_logs._form', ['log' => $log, 'lightbox' => 'editar-lancamento-'.$log->id])
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
