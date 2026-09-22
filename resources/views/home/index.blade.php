@extends('layouts.app')

@section('content')
    <section class="panel">
        <h1>Início</h1>
        <p>Você entrou como {{ auth()->user()->role->label() }}.</p>
    </section>
@endsection
