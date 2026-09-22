@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Novo lançamento</h1>
        <form method="POST" action="{{ route('lancamentos.store') }}" class="stack">
            @csrf
            @include('time_logs._form')
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('lancamentos.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
