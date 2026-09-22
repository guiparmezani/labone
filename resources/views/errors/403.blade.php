@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Sem acesso</h1>
        <p>Você não tem acesso a esta página.</p>
        @auth
            <a class="btn btn-primary" href="{{ route('inicio') }}">Voltar ao início</a>
        @else
            <a class="btn btn-primary" href="{{ route('entrar') }}">Entrar</a>
        @endauth
    </section>
@endsection
