<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class ShiftGap
{
    public function __construct(
        public User $user,
        public Carbon $since,
        public ?Carbon $startedAt,
        public Carbon $shiftEndsAt,
    ) {}

    public function isOngoing(?Carbon $now = null): bool
    {
        return $this->startedAt === null && ($now ?? now())->lessThan($this->shiftEndsAt);
    }

    public function minutes(?Carbon $now = null): int
    {
        $end = $this->startedAt ?? $now ?? now();

        if ($end->greaterThan($this->shiftEndsAt)) {
            $end = $this->shiftEndsAt;
        }

        return max(0, intdiv($end->getTimestamp() - $this->since->getTimestamp(), 60));
    }
}
