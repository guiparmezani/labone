@extends('layouts.app')

@php
    use App\Enums\Role;
    use App\Support\Formato;
@endphp

@section('content')
    <div class="page-head">
        <h1>Relatórios</h1>
    </div>

    <p class="muted">A lista mostra todo o histórico. Pontos em andamento não entram na soma. Orçamento e horas previstas são do projeto inteiro.</p>

    <section class="report-block">
        <h2>Por projeto</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Situação</th>
                        <th>Orçamento</th>
                        <th>Horas previstas</th>
                        <th>Horas lançadas</th>
                        <th>Orçamento de terceiros</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr class="is-clickable" tabindex="0" data-folha="{{ route('projetos.relatorio', $project) }}">
                            <td>{{ $project->name }}</td>
                            <td>{{ $project->status->label() }}</td>
                            <td>{{ Formato::reais($project->budget_cents) }}</td>
                            <td>{{ Formato::minutos($project->plannedMinutesTotal()) }}</td>
                            <td>{{ Formato::minutos($project->loggedMinutes()) }}</td>
                            <td>{{ Formato::reais($project->thirdPartyBudgetCents()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Nenhum projeto.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <a class="btn btn-primary" href="{{ route('relatorios.projects') }}">Exportar tudo</a>
    </section>

    <section class="report-block">
        <h2>Por operador</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Papel</th>
                        <th>Horas</th>
                        <th>Por projeto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($operators as $operator)
                        <tr class="is-clickable" tabindex="0" data-exportar="pessoa" data-id="{{ $operator->id }}" data-nome="{{ $operator->name }}">
                            <td>{{ $operator->name }}</td>
                            <td>{{ Role::from($operator->role)->label() }}</td>
                            <td>{{ Formato::minutos($operator->minutes) }}</td>
                            <td>
                                @foreach ($operator->projects as $line)
                                    {{ $line->name }} {{ Formato::minutos($line->minutes) }}@if (! $loop->last), @endif
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Nenhuma hora lançada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <a class="btn btn-primary" href="{{ route('relatorios.people') }}">Exportar tudo</a>
    </section>

    <dialog class="lightbox" id="exportar">
        <form method="GET" id="exportar-form" class="stack" action="{{ route('relatorios.people') }}">
            <div class="lightbox-intro">
                <h2 id="exportar-titulo"></h2>
                <p class="muted">Sem datas, o relatório cobre todo o histórico.</p>
            </div>
            <label class="field">
                <span>De</span>
                @include('partials.calendario', ['nome' => 'from'])
            </label>
            <label class="field">
                <span>Até</span>
                @include('partials.calendario', ['nome' => 'to'])
            </label>
            <input type="hidden" name="user_id" value="">
            <div class="actions">
                <button class="btn btn-primary" type="submit">Exportar</button>
                <button class="btn btn-ghost" type="button" data-fechar>Cancelar</button>
            </div>
        </form>
    </dialog>

    <script>
        (function () {
            var dialog = document.getElementById('exportar');
            var form = document.getElementById('exportar-form');
            var titulo = document.getElementById('exportar-titulo');
            var pessoa = form.querySelector('[name="user_id"]');

            function limparDatas() {
                form.querySelectorAll('.calendario').forEach(function (campo) {
                    campo.querySelector('.calendario-texto').value = '';
                    campo.querySelector('input[type="hidden"]').value = '';
                    campo.querySelector('.calendario-pop').hidden = true;
                });
            }

            function abrir(linha) {
                limparDatas();
                titulo.tabIndex = -1;
                titulo.textContent = linha.dataset.nome;
                pessoa.value = linha.dataset.id;
                dialog.showModal();
                limparDatas();
                titulo.focus();
            }

            document.querySelectorAll('[data-folha]').forEach(function (linha) {
                linha.addEventListener('click', function () { window.location = linha.dataset.folha; });
                linha.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        window.location = linha.dataset.folha;
                    }
                });
            });

            document.querySelectorAll('[data-exportar]').forEach(function (linha) {
                linha.addEventListener('click', function () { abrir(linha); });
                linha.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        abrir(linha);
                    }
                });
            });

            dialog.querySelector('[data-fechar]').addEventListener('click', function () {
                dialog.close();
            });

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

            form.addEventListener('submit', function () {
                setTimeout(function () { dialog.close(); }, 0);
            });
        })();
    </script>
@endsection
