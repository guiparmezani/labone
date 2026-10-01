@php
    $enviado = isset($lightbox) ? old('lightbox') === $lightbox : true;
    $nome = $enviado ? old('name', $user->name ?? '') : ($user->name ?? '');
    $email = $enviado ? old('email', $user->email ?? '') : ($user->email ?? '');
    $papel = $enviado
        ? old('role', $user->role?->value ?? \App\Enums\Role::Operator->value)
        : ($user->role?->value ?? \App\Enums\Role::Operator->value);
    $ativa = $enviado
        ? old('active', ($user->active ?? true) ? '1' : '0') == '1'
        : (bool) ($user->active ?? true);
    $valorHora = $enviado
        ? old('hourly_rate', isset($user->hourly_rate_cents) ? \App\Support\Formato::reaisEntrada($user->hourly_rate_cents) : '')
        : (isset($user->hourly_rate_cents) ? \App\Support\Formato::reaisEntrada($user->hourly_rate_cents) : '');
    $jornadaInicio = $enviado ? old('shift_start', $user->shift_start ?? '') : ($user->shift_start ?? '');
    $jornadaFim = $enviado ? old('shift_end', $user->shift_end ?? '') : ($user->shift_end ?? '');
    $tardeInicio = $enviado ? old('shift_afternoon_start', $user->shift_afternoon_start ?? '') : ($user->shift_afternoon_start ?? '');
    $tardeFim = $enviado ? old('shift_afternoon_end', $user->shift_afternoon_end ?? '') : ($user->shift_afternoon_end ?? '');
    $mostrarPrimeira = $jornadaInicio !== '' || $jornadaFim !== ''
        || ($enviado && ($errors->has('shift_start') || $errors->has('shift_end')));
    $mostrarSegunda = $tardeInicio !== '' || $tardeFim !== ''
        || ($enviado && ($errors->has('shift_afternoon_start') || $errors->has('shift_afternoon_end')));
@endphp

<label class="field">
    <span>Nome</span>
    <input type="text" name="name" value="{{ $nome }}" required maxlength="120">
    @if ($enviado)
        @error('name')
            <small class="error">{{ $message }}</small>
        @enderror
    @endif
</label>

<label class="field">
    <span>E-mail</span>
    <input type="email" name="email" value="{{ $email }}" placeholder="Opcional">
    @if ($enviado)
        @error('email')
            <small class="error">{{ $message }}</small>
        @enderror
    @endif
</label>

<label class="field">
    <span>Senha</span>
    <input type="password" name="password" @if ($user->exists ?? false) placeholder="Deixe em branco para manter a senha" @else required @endif autocomplete="new-password">
    @if ($enviado)
        @error('password')
            <small class="error">{{ $message }}</small>
        @enderror
    @endif
</label>

<label class="field">
    <span>Papel</span>
    <select name="role" required>
        @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected($papel === $role->value)>
                {{ $role->label() }}
            </option>
        @endforeach
    </select>
    @if ($enviado)
        @error('role')
            <small class="error">{{ $message }}</small>
        @enderror
    @endif
</label>

@unless ($user->exists)
    <label class="check">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" name="active" value="1" @checked($ativa)>
        <span>Conta ativa</span>
    </label>
@endunless

<label class="field">
    <span>Valor hora (R$)</span>
    <input type="text" name="hourly_rate" inputmode="decimal" value="{{ $valorHora }}" placeholder="Opcional">
    @if ($enviado)
        @error('hourly_rate_cents')
            <small class="error">{{ $message }}</small>
        @enderror
    @endif
</label>

<div data-jornadas>
    <p class="jornada-rotulo" data-jornada-vazia @if ($mostrarPrimeira || $mostrarSegunda) hidden @endif>Jornada</p>

    <div class="jornada" data-jornada @unless ($mostrarPrimeira) hidden @endunless>
        <span class="jornada-rotulo">Jornada 1</span>
        <div class="pair">
            <div class="field">
                <input type="text" name="shift_start" value="{{ $jornadaInicio }}" inputmode="numeric" maxlength="5" placeholder="XX:XX" data-mascara-hora pattern="([01][0-9]|2[0-3]):[0-5][0-9]" autocomplete="off" aria-label="Início da jornada 1">
                @if ($enviado)
                    @error('shift_start')
                        <small class="error">{{ $message }}</small>
                    @enderror
                @endif
            </div>
            <div class="field">
                <input type="text" name="shift_end" value="{{ $jornadaFim }}" inputmode="numeric" maxlength="5" placeholder="XX:XX" data-mascara-hora pattern="([01][0-9]|2[0-3]):[0-5][0-9]" autocomplete="off" aria-label="Fim da jornada 1">
                @if ($enviado)
                    @error('shift_end')
                        <small class="error">{{ $message }}</small>
                    @enderror
                @endif
            </div>
        </div>
        <button class="link-btn" type="button" data-remover-jornada>Remover</button>
    </div>

    <div class="jornada" data-jornada @unless ($mostrarSegunda) hidden @endunless>
        <span class="jornada-rotulo">Jornada 2</span>
        <div class="pair">
            <div class="field">
                <input type="text" name="shift_afternoon_start" value="{{ $tardeInicio }}" inputmode="numeric" maxlength="5" placeholder="XX:XX" data-mascara-hora pattern="([01][0-9]|2[0-3]):[0-5][0-9]" autocomplete="off" aria-label="Início da jornada 2">
                @if ($enviado)
                    @error('shift_afternoon_start')
                        <small class="error">{{ $message }}</small>
                    @enderror
                @endif
            </div>
            <div class="field">
                <input type="text" name="shift_afternoon_end" value="{{ $tardeFim }}" inputmode="numeric" maxlength="5" placeholder="XX:XX" data-mascara-hora pattern="([01][0-9]|2[0-3]):[0-5][0-9]" autocomplete="off" aria-label="Fim da jornada 2">
                @if ($enviado)
                    @error('shift_afternoon_end')
                        <small class="error">{{ $message }}</small>
                    @enderror
                @endif
            </div>
        </div>
        <button class="link-btn" type="button" data-remover-jornada>Remover</button>
    </div>

    <button class="link-btn" type="button" data-adicionar-jornada @if ($mostrarPrimeira && $mostrarSegunda) hidden @endif>Adicionar jornada</button>
</div>
