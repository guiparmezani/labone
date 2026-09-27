@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Algo deu errado</h1>
        <p>Não foi possível concluir isso agora. Tente de novo daqui a pouco.</p>
        <a class="btn btn-primary" href="{{ route('inicio') }}">Voltar ao início</a>
    </section>
@endsection
