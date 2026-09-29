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

<label class="check">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1" @checked($ativa)>
    <span>Conta ativa</span>
</label>
