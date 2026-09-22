@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Editar lançamento</h1>
        <form method="POST" action="{{ route('lancamentos.update', $log) }}" class="stack">
            @csrf
            @method('PUT')
            @include('time_logs._form')
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('lancamentos.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
