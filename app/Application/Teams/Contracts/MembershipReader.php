<?php

declare(strict_types=1);

namespace App\Application\Teams\Contracts;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Monads\Option;

interface MembershipReader
{
    /** @return Option<TeamRole> */
    public function roleFor(User $user, Team $team): Option;
}
