<?php

declare(strict_types=1);

namespace App\Application\Teams\Contracts;

use App\Models\Team;
use App\Models\User;

interface CurrentTeamWriter
{
    public function persist(User $user, Team $team): void;
}
