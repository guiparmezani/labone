@extends('layouts.app')

@php
    use App\Support\Formato;

    $csv = route('relatorios.people', array_filter([
        'user_id' => $user->id,
        'from' => $from,
        'to' => $to,
    ]));
@endphp

@section('content')
    <div class="page-head no-print">
        <div>
            <h1>Relatório · {{ $user->name }}</h1>
            <p class="muted">Horas já encerradas. O ponto aberto fica de fora.</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('relatorios.index') }}">Voltar</a>
        </div>
    </div>

    <form method="GET" action="{{ route('relatorios.person', $user) }}" class="panel filter-bar filter-bar-dates no-print">
        <label class="field">
            <span>De</span>
            @include('partials.calendario', ['nome' => 'from', 'valor' => $from])
        </label>
        <label class="field">
            <span>Até</span>
            @include('partials.calendario', ['nome' => 'to', 'valor' => $to])
        </label>
        <button class="btn btn-ghost" type="submit">Filtrar</button>
    </form>

    <section class="panel">
        <h1 class="print-title">{{ $user->name }}</h1>
        <p>{{ $user->role->label() }}</p>
        <p class="muted">{{ $period }} Pontos em andamento não entram na soma.</p>

        @forelse ($sheet->projects as $project)
            <h2>{{ $project->name }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tarefa</th>
                        <th>Horas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->subtasks as $subtask)
                        <tr>
                            <td>{{ $subtask->name }}@if ($subtask->is_revision) (revisão)@endif</td>
                            <td>{{ Formato::minutos($subtask->minutes) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>Total do projeto</strong></td>
                        <td><strong>{{ Formato::minutos($project->minutes) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        @empty
            <p>Nenhuma hora lançada.</p>
        @endforelse

        <div class="sheet-foot">
            <div>
                <p class="muted">Horas</p>
                <strong>{{ Formato::minutos($sheet->minutes) }}</strong>
                <p class="muted">Soma das horas já encerradas</p>
            </div>
        </div>

        <div class="sheet-download no-print actions">
            <a class="btn btn-primary" href="{{ $csv }}">Exportar</a>
            <button class="btn btn-ghost" type="button" onclick="window.print()">Imprimir</button>
        </div>
    </section>
@endsection
