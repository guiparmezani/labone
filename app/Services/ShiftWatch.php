<?php

namespace App\Services;

use App\Models\TimeLog;
use App\Models\User;
use App\Support\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Buracos de 15 minutos dentro da jornada, só em dia útil.
 * Quem não tem jornada fica de fora.
 */
class ShiftWatch
{
    public const MINUTOS = 15;

    public const DIAS = 30;

    /** @var array<string, Collection<int, ShiftGap>> */
    private array $cache = [];

    /**
     * @return Collection<int, ShiftGap>
     */
    public function gaps(?Carbon $agora = null): Collection
    {
        $agora = ($agora ?? now())->timezone(Formato::TZ);
        $chave = $agora->format('Y-m-d H:i');

        if (isset($this->cache[$chave])) {
            return $this->cache[$chave];
        }

        $pessoas = User::query()
            ->where('active', true)
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->orderBy('name')
            ->get();

        if ($pessoas->isEmpty()) {
            return $this->cache[$chave] = collect();
        }

        $limite = $agora->copy()->startOfDay()->subDays(self::DIAS - 1);
        $logs = TimeLog::query()
            ->whereIn('user_id', $pessoas->modelKeys())
            ->where('started_at', '<', $agora->copy()->endOfDay()->utc())
            ->where(function ($query) use ($limite): void {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>', $limite->copy()->utc());
            })
            ->get()
            ->groupBy('user_id');

        $linhas = collect();
        $dia = $agora->copy()->startOfDay();

        while ($dia->greaterThanOrEqualTo($limite)) {
            if (! $dia->isWeekend()) {
                $instante = $dia->isSameDay($agora) ? $agora->copy() : $dia->copy()->endOfDay();

                foreach ($pessoas as $pessoa) {
                    if ($this->antesDaConta($pessoa, $dia)) {
                        continue;
                    }

                    foreach ($this->janelas($pessoa, $dia) as [$inicio, $fim]) {
                        foreach ($this->buracos($inicio, $fim, $instante, $logs->get($pessoa->id, collect())) as [$desde, $iniciou]) {
                            $linhas->push(new ShiftGap($pessoa, $desde, $iniciou));
                        }
                    }
                }
            }

            $dia->subDay();
        }

        return $this->cache[$chave] = $linhas;
    }

    /**
     * @return array{gaps: Collection<int, ShiftGap>, podeCarregar: bool}
     */
    public function page(int $diasAnteriores = 0, ?Carbon $agora = null): array
    {
        $agora = ($agora ?? now())->timezone(Formato::TZ);
        $diasAnteriores = max(0, $diasAnteriores);
        $todos = $this->gaps($agora);
        $hoje = $agora->toDateString();

        $deHoje = $todos->filter(fn (ShiftGap $gap) => $gap->since->toDateString() === $hoje);
        $chavesAntigas = $todos
            ->reject(fn (ShiftGap $gap) => $gap->since->toDateString() === $hoje)
            ->map(fn (ShiftGap $gap) => $gap->since->toDateString())
            ->unique()
            ->sort()
            ->reverse()
            ->values();

        $mostrar = $chavesAntigas->take($diasAnteriores);
        $antigos = $todos->filter(fn (ShiftGap $gap) => $mostrar->contains($gap->since->toDateString()));

        return [
            'gaps' => $deHoje->concat($antigos)->values(),
            'podeCarregar' => $chavesAntigas->count() > $diasAnteriores,
        ];
    }

    public function openCount(?Carbon $agora = null): int
    {
        return $this->gaps($agora)->filter(fn (ShiftGap $gap) => $gap->startedAt === null)->count();
    }

    /**
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function janelas(User $pessoa, Carbon $dia): array
    {
        $janelas = [];
        $primeira = $this->janela($dia, $pessoa->shift_start, $pessoa->shift_end);

        if ($primeira !== null) {
            $janelas[] = $primeira;
        }

        $segunda = $this->janela($dia, $pessoa->shift_afternoon_start, $pessoa->shift_afternoon_end);

        if ($segunda !== null) {
            $janelas[] = $segunda;
        }

        return $janelas;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function janela(Carbon $dia, ?string $inicio, ?string $fim): ?array
    {
        if ($inicio === null || $fim === null || $inicio === '' || $fim === '' || $inicio >= $fim) {
            return null;
        }

        return [
            $dia->copy()->setTimeFromTimeString($inicio),
            $dia->copy()->setTimeFromTimeString($fim),
        ];
    }

    /**
     * @param  Collection<int, TimeLog>  $logs
     * @return list<array{0: Carbon, 1: ?Carbon}>
     */
    private function buracos(Carbon $janelaInicio, Carbon $janelaFim, Carbon $agora, Collection $logs): array
    {
        if ($agora->lessThanOrEqualTo($janelaInicio)) {
            return [];
        }

        $ate = $agora->lessThan($janelaFim) ? $agora->copy() : $janelaFim->copy();
        $ocupado = $this->intervalos($janelaInicio, $ate, $agora, $logs);
        $buracos = [];
        $cursor = $janelaInicio->copy();

        foreach ($ocupado as [$comeco, $fim]) {
            if ($comeco->greaterThan($cursor) && $this->minutos($cursor, $comeco) >= self::MINUTOS) {
                $buracos[] = [$cursor->copy(), $comeco->copy()];
            }

            if ($fim->greaterThan($cursor)) {
                $cursor = $fim->copy();
            }
        }

        if ($ate->greaterThan($cursor) && $this->minutos($cursor, $ate) >= self::MINUTOS) {
            $buracos[] = [$cursor->copy(), null];
        }

        return $buracos;
    }

    /**
     * @param  Collection<int, TimeLog>  $logs
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function intervalos(Carbon $inicio, Carbon $ate, Carbon $agora, Collection $logs): array
    {
        $intervalos = [];

        foreach ($logs as $log) {
            $comeco = $log->started_at->copy()->timezone(Formato::TZ);
            $fim = ($log->ended_at ?? $agora)->copy()->timezone(Formato::TZ);

            if ($fim->lessThanOrEqualTo($inicio) || $comeco->greaterThan($ate)) {
                continue;
            }

            $corteInicio = $comeco->greaterThan($inicio) ? $comeco : $inicio->copy();
            $corteFim = $fim->lessThan($ate) ? $fim : $ate->copy();

            if ($corteFim->greaterThanOrEqualTo($corteInicio)) {
                $intervalos[] = [$corteInicio, $corteFim];
            }
        }

        usort($intervalos, fn (array $a, array $b) => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());

        $juntos = [];

        foreach ($intervalos as [$comeco, $fim]) {
            if ($juntos === []) {
                $juntos[] = [$comeco, $fim];

                continue;
            }

            $ultimo = count($juntos) - 1;

            if ($comeco->lessThanOrEqualTo($juntos[$ultimo][1])) {
                if ($fim->greaterThan($juntos[$ultimo][1])) {
                    $juntos[$ultimo][1] = $fim;
                }
            } else {
                $juntos[] = [$comeco, $fim];
            }
        }

        return $juntos;
    }

    private function antesDaConta(User $pessoa, Carbon $dia): bool
    {
        if ($pessoa->created_at === null) {
            return false;
        }

        return $dia->copy()->endOfDay()->lt($pessoa->created_at->copy()->timezone(Formato::TZ));
    }

    private function minutos(Carbon $inicio, Carbon $fim): int
    {
        return (int) floor(($fim->getTimestamp() - $inicio->getTimestamp()) / 60);
    }
}
