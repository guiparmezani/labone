<?php

namespace App\Services;

use App\Models\Subtask;
use App\Models\User;

/**
 * O número do menu é o que esta pessoa ainda não abriu.
 * Abrir Alertas marca o conjunto atual como lido, inclusive os dias que a tela ainda não carregou.
 */
class AlertInbox
{
    public function __construct(private ShiftWatch $watch) {}

    public function unreadCount(User $user): int
    {
        return count(array_diff($this->keys(), $this->seen($user)));
    }

    public function markRead(User $user): void
    {
        $vistas = array_values(array_unique([...$this->seen($user), ...$this->keys()]));
        sort($vistas);
        $user->forceFill(['alerts_seen' => $vistas])->save();
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        $jornada = $this->watch->gaps()
            ->filter(fn (ShiftGap $gap) => $gap->startedAt === null)
            ->map(fn (ShiftGap $gap) => 'jornada:'.$gap->user->id.':'.$gap->since->format('Y-m-d H:i'))
            ->all();

        $atingidos = Subtask::query()->reached()->pluck('subtasks.id')
            ->map(fn ($id) => 'atingido:'.$id)
            ->all();

        $chaves = array_values(array_unique([...$jornada, ...$atingidos]));
        sort($chaves);

        return $chaves;
    }

    /**
     * @return list<string>
     */
    private function seen(User $user): array
    {
        $visto = $user->alerts_seen;

        return is_array($visto) ? $visto : [];
    }
}
