@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Novo projeto</h1>
        <form method="POST" action="{{ route('projetos.store') }}" class="stack">
            @csrf
            @include('projects._form', ['project' => new \App\Models\Project()])
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('projetos.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
