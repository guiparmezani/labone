@extends('layouts.app')

@php
    use App\Support\Formato;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><a href="{{ route('projetos.edit', $project) }}">{{ $project->name }}</a></h1>
            <p class="muted">{{ $project->status->label() }} · {{ Formato::reais($project->budget_cents) }} · {{ Formato::minutos($project->plannedMinutesTotal()) }} previstas ({{ Formato::minutos($project->planned_minutes) }} do projeto + {{ Formato::minutos($project->plannedMinutesFromSubtasks()) }} das tarefas) · {{ Formato::minutos($project->loggedMinutes()) }} lançadas</p>
        </div>
        <div class="menu">
            <button class="btn btn-ghost menu-button" type="button" data-menu aria-expanded="false" aria-haspopup="menu" aria-label="Ações do projeto">...</button>
            <div class="menu-panel" data-menu-painel hidden>
                <a href="{{ route('projetos.edit', $project) }}">Editar</a>
                @if ($project->isOpen())
                    <a href="{{ route('projetos.ponto', $project) }}">Ponto</a>
                @endif
                <a href="{{ route('projetos.relatorio', $project) }}">Relatório</a>
                <a href="{{ route('projetos.copy', $project) }}">Copiar</a>
                @if ($project->isOpen())
                    <form method="POST" action="{{ route('projetos.close', $project) }}">@csrf<button type="submit">Encerrar</button></form>
                @else
                    <form method="POST" action="{{ route('projetos.reopen', $project) }}">@csrf<button type="submit">Reabrir</button></form>
                @endif
                <form method="POST" action="{{ route('projetos.destroy', $project) }}" onsubmit="return confirm('Apagar este projeto?')">
                    @csrf
                    @method('DELETE')
                    <button class="is-danger" type="submit">Apagar</button>
                </form>
            </div>
        </div>
    </div>

    @error('project')<p class="error">{{ $message }}</p>@enderror
    @error('subtask')<p class="error">{{ $message }}</p>@enderror
    @if ($project->notes)
        <p>{{ $project->notes }}</p>
    @endif

    <section class="panel" style="margin-bottom: 1rem;">
        <h2>Tarefas</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tempo previsto</th>
                        <th>Tempo realizado</th>
                        <th>Valor previsto</th>
                        <th>Valor realizado</th>
                        <th>Alarme</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($internal as $subtask)
                        <tr class="is-clickable" tabindex="0" data-abrir="editar-{{ $subtask->id }}">
                            <td>
                                {{ $subtask->name }}
                                @if ($subtask->is_revision)
                                    <span class="muted">· Revisão</span>
                                @endif
                                @if ($subtask->revision_notes)
                                    <br><span class="muted">{{ $subtask->revision_notes }}</span>
                                @endif
                            </td>
                            <td>{{ $subtask->planned_minutes !== null ? Formato::minutos($subtask->planned_minutes) : '—' }}</td>
                            <td>{{ Formato::minutos($subtask->loggedMinutes()) }}</td>
                            <td>{{ $subtask->budget_cents !== null ? Formato::reais($subtask->budget_cents) : '—' }}</td>
                            <td>{{ $subtask->realized_cents !== null ? Formato::reais($subtask->realized_cents) : '—' }}</td>
                            <td>
                                @if ($subtask->alert_enabled && $subtask->alert_percentage)
                                    {{ $subtask->alert_percentage }}%@if ($subtask->alertReached()) · Atingido @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="cell-end">
                                @unless ($subtask->timeLogs()->exists())
                                    <form method="POST" action="{{ route('subtarefas.destroy', $subtask) }}" onsubmit="return confirm('Apagar esta tarefa?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Apagar</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Nenhuma tarefa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($project->isOpen())
            <button class="btn btn-primary" type="button" data-abrir="nova-interna">Adicionar tarefa</button>
        @endif
    </section>

    <section class="panel" style="margin-bottom: 1rem;">
        <h2>Equipe terceira</h2>
        <p class="muted">Sem ponto. O valor previsto é o combinado com a equipe de fora. O valor realizado é digitado.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tempo previsto</th>
                        <th>Valor previsto</th>
                        <th>Valor realizado</th>
                        <th>Alarme</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($thirdParty as $subtask)
                        <tr class="is-clickable" tabindex="0" data-abrir="editar-{{ $subtask->id }}">
                            <td>
                                {{ $subtask->name }}
                                @if ($subtask->is_revision)
                                    <span class="muted">· Revisão</span>
                                @endif
                                @if ($subtask->revision_notes)
                                    <br><span class="muted">{{ $subtask->revision_notes }}</span>
                                @endif
                            </td>
                            <td>{{ $subtask->planned_minutes !== null ? Formato::minutos($subtask->planned_minutes) : '—' }}</td>
                            <td>{{ $subtask->budget_cents !== null ? Formato::reais($subtask->budget_cents) : '—' }}</td>
                            <td>{{ $subtask->realized_cents !== null ? Formato::reais($subtask->realized_cents) : '—' }}</td>
                            <td>
                                @if ($subtask->alert_enabled && $subtask->alert_percentage)
                                    {{ $subtask->alert_percentage }}%
                                @else
                                    —
                                @endif
                            </td>
                            <td class="cell-end">
                                @unless ($subtask->timeLogs()->exists())
                                    <form method="POST" action="{{ route('subtarefas.destroy', $subtask) }}" onsubmit="return confirm('Apagar esta tarefa?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Apagar</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Nenhuma tarefa de equipe terceira.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($project->isOpen())
            <button class="btn btn-primary" type="button" data-abrir="nova-terceira">Adicionar equipe terceira</button>
        @endif
    </section>

    @if ($project->isOpen())
        @include('subtasks._dialog', [
            'id' => 'nova-interna',
            'titulo' => 'Nova tarefa',
            'action' => route('subtarefas.store', $project),
            'metodo' => 'POST',
            'subtask' => null,
            'kind' => 'internal',
            'thirdParties' => $thirdParty,
        ])
        @include('subtasks._dialog', [
            'id' => 'nova-terceira',
            'titulo' => 'Nova equipe terceira',
            'action' => route('subtarefas.store', $project),
            'metodo' => 'POST',
            'subtask' => null,
            'kind' => 'third_party',
            'thirdParties' => $thirdParty,
        ])
    @endif

    @foreach ($internal as $subtask)
        @include('subtasks._dialog', [
            'id' => 'editar-'.$subtask->id,
            'titulo' => 'Editar tarefa',
            'action' => route('subtarefas.update', $subtask),
            'metodo' => 'PUT',
            'subtask' => $subtask,
            'kind' => $subtask->kind->value,
            'thirdParties' => $thirdParty->where('id', '!=', $subtask->id),
        ])
    @endforeach

    @foreach ($thirdParty as $subtask)
        @include('subtasks._dialog', [
            'id' => 'editar-'.$subtask->id,
            'titulo' => 'Editar tarefa',
            'action' => route('subtarefas.update', $subtask),
            'metodo' => 'PUT',
            'subtask' => $subtask,
            'kind' => $subtask->kind->value,
            'thirdParties' => $thirdParty->where('id', '!=', $subtask->id),
        ])
    @endforeach

    @if (old('lightbox'))
        <script>
            document.getElementById(@json(old('lightbox')))?.showModal();
        </script>
    @endif

    <script>
        (function () {
            var botao = document.querySelector('[data-menu]');
            var painel = document.querySelector('[data-menu-painel]');

            botao.addEventListener('click', function () {
                var abrir = painel.hidden;
                painel.hidden = !abrir;
                botao.setAttribute('aria-expanded', abrir ? 'true' : 'false');
            });

            document.addEventListener('click', function (event) {
                if (!botao.parentElement.contains(event.target)) {
                    painel.hidden = true;
                    botao.setAttribute('aria-expanded', 'false');
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    painel.hidden = true;
                    botao.setAttribute('aria-expanded', 'false');
                }
            });

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
