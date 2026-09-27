@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Página expirada</h1>
        <p>A página ficou aberta tempo demais. Volte e tente de novo.</p>
        <a class="btn btn-primary" href="{{ route('entrar') }}">Entrar</a>
    </section>
@endsection
