<label class="field">
    <span>Nome</span>
    <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required maxlength="120">
    @error('name')
        <small class="error">{{ $message }}</small>
    @enderror
</label>

<label class="field">
    <span>E-mail</span>
    <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
    @error('email')
        <small class="error">{{ $message }}</small>
    @enderror
</label>

<label class="field">
    <span>Senha</span>
    <input type="password" name="password" @if ($user->exists ?? false) placeholder="Deixe em branco para manter a senha" @else required @endif autocomplete="new-password">
    @error('password')
        <small class="error">{{ $message }}</small>
    @enderror
</label>

<label class="field">
    <span>Papel</span>
    <select name="role" required>
        @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', $user->role?->value ?? \App\Enums\Role::Operator->value) === $role->value)>
                {{ $role->label() }}
            </option>
        @endforeach
    </select>
    @error('role')
        <small class="error">{{ $message }}</small>
    @enderror
</label>

<label class="check">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1" @checked(old('active', ($user->active ?? true) ? '1' : '0') == '1')>
    <span>Conta ativa</span>
</label>
