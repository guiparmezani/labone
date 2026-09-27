@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Página não encontrada</h1>
        <p>Esse endereço não existe.</p>
        @auth
            <a class="btn btn-primary" href="{{ route('inicio') }}">Voltar ao início</a>
        @else
            <a class="btn btn-primary" href="{{ route('entrar') }}">Entrar</a>
        @endauth
    </section>
@endsection
