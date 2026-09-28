@php
    use App\Enums\SubtaskKind;
    use App\Support\Formato;

    $enviado = old('lightbox') === $id;
    $criando = $subtask === null;
    $kindAtual = $criando ? SubtaskKind::from($kind) : $subtask->kind;
    $admin = auth()->user()->isAdmin();
    $podeHoras = $admin || $criando;
    $podeOrcamento = $admin || $criando || $kindAtual !== SubtaskKind::ThirdParty;
    $nome = $enviado ? old('name') : ($subtask->name ?? '');
    $horas = $enviado
        ? old('planned_hours')
        : ($subtask?->planned_minutes !== null ? Formato::duracaoEntrada($subtask->planned_minutes) : '');
    $orcamento = $enviado
        ? old('budget')
        : ($subtask?->budget_cents !== null ? Formato::reaisEntrada($subtask->budget_cents) : '');
    $realizado = $enviado
        ? old('realized')
        : ($subtask?->realized_cents !== null ? Formato::reaisEntrada($subtask->realized_cents) : '');
    $porcentagem = $enviado
        ? old('alert_percentage')
        : (($subtask?->alert_enabled) ? ($subtask->alert_percentage ?? '') : '');
    $revisao = $enviado ? (bool) old('is_revision') : (bool) ($subtask->is_revision ?? false);
    $notas = $enviado ? old('revision_notes') : ($subtask->revision_notes ?? '');
    $relacionada = (string) ($enviado ? old('revision_of_subtask_id') : ($subtask->revision_of_subtask_id ?? ''));
    $tipo = $enviado ? old('kind', $kindAtual->value) : $kindAtual->value;
@endphp

<dialog class="lightbox lightbox-form" id="{{ $id }}">
    <form method="POST" action="{{ $action }}" class="stack">
        @csrf
        @if ($metodo !== 'POST')
            @method($metodo)
        @endif
        <input type="hidden" name="lightbox" value="{{ $id }}">
        @if ($criando)
            <input type="hidden" name="kind" value="{{ $kind }}">
        @endif

        <div class="lightbox-intro">
            <h2>{{ $titulo }}</h2>
            @unless ($criando)
                <p class="muted">Tempo realizado: {{ Formato::minutos($subtask->loggedMinutes()) }} já parados, {{ Formato::minutos($subtask->consumedMinutes()) }} com o ponto aberto. O valor realizado é digitado, não calculado.</p>
            @endunless
        </div>

        <label class="field">
            <span>Nome</span>
            <input type="text" name="name" value="{{ $nome }}" required maxlength="160">
            @if ($enviado)
                @error('name')<small class="error">{{ $message }}</small>@enderror
            @endif
        </label>

        @unless ($criando)
            <label class="field">
                <span>Tipo</span>
                <select name="kind" required>
                    @foreach (SubtaskKind::cases() as $opcao)
                        <option value="{{ $opcao->value }}" @selected($tipo === $opcao->value)>{{ $opcao->label() }}</option>
                    @endforeach
                </select>
                @if ($enviado)
                    @error('kind')<small class="error">{{ $message }}</small>@enderror
                @endif
            </label>
        @endunless

        @if ($podeHoras)
            <label class="field">
                <span>Tempo previsto</span>
                <input type="text" name="planned_hours" inputmode="numeric" value="{{ $horas }}" placeholder="hh:mm">
                @if ($enviado)
                    @error('planned_minutes')<small class="error">{{ $message }}</small>@enderror
                @endif
            </label>
        @else
            <p>Tempo previsto: {{ $subtask->planned_minutes !== null ? Formato::minutos($subtask->planned_minutes) : '—' }}. Só o administrador altera. Essas horas somam às horas previstas do projeto.</p>
        @endif

        @if ($podeOrcamento)
            <label class="field">
                <span>Valor previsto (R$)</span>
                <input type="text" name="budget" inputmode="decimal" value="{{ $orcamento }}" placeholder="1.800,00" @if ($criando && $kindAtual === SubtaskKind::ThirdParty) required @endif>
                @if ($enviado)
                    @error('budget_cents')<small class="error">{{ $message }}</small>@enderror
                @endif
            </label>
        @else
            <p>Valor previsto da equipe terceira: {{ $subtask->budget_cents !== null ? Formato::reais($subtask->budget_cents) : '—' }}. Só o administrador altera.</p>
        @endif

        <label class="field">
            <span>Valor realizado (R$)</span>
            <input type="text" name="realized" inputmode="decimal" value="{{ $realizado }}" placeholder="900,00">
            @if ($enviado)
                @error('realized_cents')<small class="error">{{ $message }}</small>@enderror
            @endif
        </label>

        <label class="field">
            <span>Alarme (%)</span>
            <input type="number" name="alert_percentage" min="1" max="100" step="1" value="{{ $porcentagem }}">
            @if ($enviado)
                @error('alert_percentage')<small class="error">{{ $message }}</small>@enderror
            @endif
        </label>

        <label class="check">
            <input class="revision-toggle" type="checkbox" name="is_revision" value="1" @checked($revisao)>
            <span>Revisão</span>
        </label>
        <div class="revision-fields">
            <label class="field">
                <span>Descrição da revisão</span>
                <textarea name="revision_notes" maxlength="2000">{{ $notas }}</textarea>
                @if ($enviado)
                    @error('revision_notes')<small class="error">{{ $message }}</small>@enderror
                @endif
            </label>
            <label class="field">
                <span>Equipe terceira relacionada</span>
                <select name="revision_of_subtask_id">
                    <option value="">Nenhuma</option>
                    @foreach ($thirdParties as $third)
                        <option value="{{ $third->id }}" @selected($relacionada === (string) $third->id)>{{ $third->name }}</option>
                    @endforeach
                </select>
                @if ($enviado)
                    @error('revision_of_subtask_id')<small class="error">{{ $message }}</small>@enderror
                @endif
            </label>
        </div>

        <div class="actions">
            <button class="btn btn-primary" type="submit">Salvar</button>
            <button class="btn btn-ghost" type="button" data-fechar>Cancelar</button>
        </div>
    </form>
</dialog>
