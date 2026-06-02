<?php

declare(strict_types=1);

namespace App\Application\Teams;

use App\Models\Team;
use App\Models\User;

final readonly class TeamContext
{
    public function __construct(
        public User $user,
        public Team $team,
    ) {}
}
