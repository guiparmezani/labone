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
    ) {}
}
