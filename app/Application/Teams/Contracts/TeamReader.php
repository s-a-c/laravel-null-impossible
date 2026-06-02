<?php

declare(strict_types=1);

namespace App\Application\Teams\Contracts;

use App\Domain\Teams\ValueObjects\TeamSlug;
use App\Models\Team;
use App\Models\User;
use App\Monads\Option;

interface TeamReader
{
    /** @return Option<Team> */
    public function findBySlug(TeamSlug $slug): Option;

    /** @return Option<Team> */
    public function findPersonalForUser(User $user): Option;

    /** @return Option<Team> */
    public function findFallbackForUser(User $user, ?Team $excluding = null): Option;
}
