@extends('layouts.app')

@section('content')
    <div class="page-head">
        <h1>Projetos</h1>
        <div class="actions">
            <button class="btn btn-ghost" type="button" data-abrir="importar-projeto">Importar</button>
            <button class="btn btn-primary" type="button" data-abrir="novo-projeto">Novo projeto</button>
        </div>
    </div>

    @error('project')
        <p class="error">{{ $message }}</p>
    @enderror

    <nav class="filters">
        <a href="{{ route('projetos.index', ['status' => 'open']) }}" @class(['is-current' => $status === 'open'])>Abertos</a>
        <a href="{{ route('projetos.index', ['status' => 'closed']) }}" @class(['is-current' => $status === 'closed'])>Encerrados</a>
        <a href="{{ route('projetos.index', ['status' => 'all']) }}" @class(['is-current' => $status === 'all'])>Todos</a>
    </nav>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Situação</th>
                    <th>Orçamento</th>
                    <th>Horas previstas</th>
                    <th>Horas lançadas</th>
                    <th>Terceiros</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr>
                        <td><a href="{{ route('projetos.show', $project) }}">{{ $project->name }}</a></td>
                        <td>{{ $project->status->label() }}</td>
                        <td>{{ \App\Support\Formato::reais($project->budget_cents) }}</td>
                        <td>{{ \App\Support\Formato::minutos($project->plannedMinutesTotal()) }}</td>
                        <td>{{ \App\Support\Formato::minutos($project->loggedMinutes()) }}</td>
                        <td>{{ \App\Support\Formato::reais($project->thirdPartyBudgetCents()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum projeto nesta lista.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <dialog class="lightbox lightbox-form" id="novo-projeto">
        <form method="POST" action="{{ route('projetos.store') }}" class="stack">
            @csrf
            <input type="hidden" name="lightbox" value="novo-projeto">
            <h2>Novo projeto</h2>
            @include('projects._form', ['project' => new \App\Models\Project(), 'lightbox' => 'novo-projeto'])
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <button class="btn btn-ghost" type="button" data-fechar>Cancelar</button>
            </div>
        </form>
    </dialog>

    <dialog class="lightbox lightbox-form" id="importar-projeto">
        <form method="POST" action="{{ route('projetos.importar') }}" enctype="multipart/form-data" class="stack">
            @csrf
            <input type="hidden" name="lightbox" value="importar-projeto">
            <h2>Importar projeto</h2>
            <p class="muted">A primeira linha traz o nome do projeto na terceira coluna. Da terceira linha em diante, a terceira coluna é o nome de cada tarefa.</p>
            <div class="dropzone" data-dropzone>
                <input type="file" name="arquivo" accept=".csv,text/csv" data-arquivo>
                <p>Arraste o arquivo .CSV aqui</p>
                <button class="btn btn-ghost" type="button" data-escolher>Escolher arquivo</button>
                <p class="muted" data-arquivo-nome hidden></p>
            </div>
            @error('arquivo')
                <p class="error">{{ $message }}</p>
            @enderror
            <div class="actions">
                <button class="btn btn-primary" type="submit">Importar</button>
                <button class="btn btn-ghost" type="button" data-fechar>Cancelar</button>
            </div>
        </form>
    </dialog>

    @if (old('lightbox'))
        <script>
            document.getElementById(@json(old('lightbox')))?.showModal();
        </script>
    @endif

    <script>
        (function () {
            var zona = document.querySelector('[data-dropzone]');
            var input = zona.querySelector('[data-arquivo]');
            var nome = zona.querySelector('[data-arquivo-nome]');

            function mostrar() {
                var arquivo = input.files && input.files[0];
                nome.hidden = !arquivo;
                nome.textContent = arquivo ? arquivo.name : '';
            }

            zona.querySelector('[data-escolher]').addEventListener('click', function () {
                input.click();
            });

            input.addEventListener('change', mostrar);

            ['dragenter', 'dragover'].forEach(function (tipo) {
                zona.addEventListener(tipo, function (event) {
                    event.preventDefault();
                    zona.classList.add('is-over');
                });
            });

            ['dragleave', 'drop'].forEach(function (tipo) {
                zona.addEventListener(tipo, function (event) {
                    event.preventDefault();
                    zona.classList.remove('is-over');
                });
            });

            zona.addEventListener('drop', function (event) {
                if (!event.dataTransfer || !event.dataTransfer.files.length) {
                    return;
                }

                input.files = event.dataTransfer.files;
                mostrar();
            });

            document.querySelectorAll('[data-abrir]').forEach(function (abrir) {
                abrir.addEventListener('click', function () {
                    document.getElementById(abrir.getAttribute('data-abrir'))?.showModal();
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
