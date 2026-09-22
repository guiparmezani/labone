<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Controle de projetos' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ auth()->check() ? route('inicio') : route('entrar') }}">Controle de projetos</a>
        @auth
            <nav class="nav">
                <a href="{{ route('inicio') }}" @class(['is-current' => request()->routeIs('inicio')])>Início</a>
                @if (auth()->user()->managesProjects())
                    <a href="{{ route('projetos.index') }}" @class(['is-current' => request()->routeIs('projetos.*')])>Projetos</a>
                    <a href="{{ route('lancamentos.index') }}" @class(['is-current' => request()->routeIs('lancamentos.*')])>Lançamentos</a>
                @endif
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('relatorios.index') }}" @class(['is-current' => request()->routeIs('relatorios.*')])>Relatórios</a>
                    <a href="{{ route('usuarios.index') }}" @class(['is-current' => request()->routeIs('usuarios.*')])>Usuários</a>
                @endif
            </nav>
            <div class="who">
                <span>{{ auth()->user()->name }}</span>
                <span class="tag">{{ auth()->user()->role->label() }}</span>
                <form method="POST" action="{{ route('sair') }}">
                    @csrf
                    <button class="btn btn-ghost" type="submit">Sair</button>
                </form>
            </div>
        @endauth
    </header>

    <main class="page">
        @if (session('status'))
            <p class="alert" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
    <script src="{{ asset('js/timer.js') }}"></script>
</body>
</html>
