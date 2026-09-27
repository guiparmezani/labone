@php
    $modo = $modo ?? 'date';
    $obrigatorio = ! empty($obrigatorio);
    $valor = (string) ($valor ?? '');
    $maquina = '';

    if ($modo === 'datetime' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $valor) === 1) {
        $maquina = substr($valor, 0, 16);
        $visivel = \App\Support\Formato::entradaDataHora($maquina);
    } elseif ($modo === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}/', $valor) === 1) {
        $maquina = substr($valor, 0, 10);
        $visivel = \App\Support\Formato::entradaData($maquina);
    } else {
        $visivel = '';
    }
@endphp
<div class="calendario" data-modo="{{ $modo }}">
    <input
        type="text"
        class="calendario-texto"
        inputmode="numeric"
        autocomplete="off"
        placeholder="{{ $modo === 'datetime' ? 'dd/mm/aaaa hh:mm' : 'dd/mm/aaaa' }}"
        value="{{ $visivel }}"
        @if ($obrigatorio) required @endif
    >
    <input type="hidden" name="{{ $nome }}" value="{{ $maquina }}">
    <div class="calendario-pop" hidden>
        <div class="calendario-nav">
            <button type="button" data-acao="anterior" aria-label="Mês anterior">‹</button>
            <strong data-rotulo></strong>
            <button type="button" data-acao="proximo" aria-label="Próximo mês">›</button>
        </div>
        <div class="calendario-semana" aria-hidden="true">
            <span>seg</span><span>ter</span><span>qua</span><span>qui</span><span>sex</span><span>sáb</span><span>dom</span>
        </div>
        <div class="calendario-grade"></div>
        @if ($modo === 'datetime')
            <div class="calendario-hora">
                <label>Hora
                    <select data-hora>
                        @for ($hora = 0; $hora < 24; $hora++)
                            <option value="{{ sprintf('%02d', $hora) }}">{{ sprintf('%02d', $hora) }}</option>
                        @endfor
                    </select>
                </label>
                <label>Minuto
                    <select data-minuto>
                        @for ($minuto = 0; $minuto < 60; $minuto++)
                            <option value="{{ sprintf('%02d', $minuto) }}">{{ sprintf('%02d', $minuto) }}</option>
                        @endfor
                    </select>
                </label>
            </div>
        @endif
        <div class="calendario-acoes">
            <button type="button" data-acao="limpar">Limpar</button>
            <button type="button" data-acao="hoje">Hoje</button>
        </div>
    </div>
</div>
