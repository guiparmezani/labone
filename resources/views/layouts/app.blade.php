<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $titulo = $title ?? 'Controle de projetos';
        $resumo = 'O ponto da fábrica, no molde certo. Acompanhe horas, tarefas e orçamento de cada projeto.';
    @endphp
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $resumo }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Controle de projetos">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $resumo }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Controle de projetos">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $titulo }}">
    <meta name="twitter:description" content="{{ $resumo }}">
    <meta name="twitter:image" content="{{ url('/og.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ auth()->check() ? route('inicio') : route('entrar') }}">Controle de projetos</a>
        @auth
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal" aria-label="Abrir menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="topbar-menu" id="menu-principal">
                <nav class="nav">
                    <a href="{{ route('inicio') }}" @class(['is-current' => request()->routeIs('inicio')])>Início</a>
                    @if (auth()->user()->managesProjects())
                        <a href="{{ route('projetos.index') }}" @class(['is-current' => request()->routeIs('projetos.*')])>Projetos</a>
                        <a href="{{ route('lancamentos.index') }}" @class(['is-current' => request()->routeIs('lancamentos.*')])>Lançamentos</a>
                        <a href="{{ route('relatorios.index') }}" @class(['is-current' => request()->routeIs('relatorios.*')])>Relatórios</a>
                    @endif
                    @if (auth()->user()->managesProjects())
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
            </div>
        @endauth
    </header>

    <main class="page">
        @if (session('status'))
            <p class="alert" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
    <script>
        (function () {
            var botao = document.querySelector('.nav-toggle');
            var menu = document.getElementById('menu-principal');

            if (!botao || !menu) {
                return;
            }

            botao.addEventListener('click', function () {
                var aberto = botao.getAttribute('aria-expanded') === 'true';
                botao.setAttribute('aria-expanded', aberto ? 'false' : 'true');
                botao.setAttribute('aria-label', aberto ? 'Abrir menu' : 'Fechar menu');
                menu.classList.toggle('is-open', !aberto);
            });
        })();
    </script>
    <script src="{{ asset('js/timer.js') }}"></script>
    <script src="{{ asset('js/calendario.js') }}"></script>
</body>
</html>
