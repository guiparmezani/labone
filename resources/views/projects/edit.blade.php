@extends('layouts.app')

@section('content')
    <section class="panel">
        <h1>Editar projeto</h1>
        <form method="POST" action="{{ route('projetos.update', $project) }}" class="stack">
            @csrf
            @method('PUT')
            @include('projects._form')
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('projetos.show', $project) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
