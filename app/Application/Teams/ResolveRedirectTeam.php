<?php

declare(strict_types=1);

namespace App\Application\Teams;

use App\Application\Teams\Contracts\TeamReader;
use App\Domain\Teams\Errors\NoTeamForRedirect;
use App\Models\Team;
use App\Models\User;
use App\Monads\Err;
use App\Monads\Ok;
use App\Monads\Result;

final readonly class ResolveRedirectTeam
{
    public function __construct(
        private TeamReader $teams,
    ) {}

    /**
     * @return Result<Team, NoTeamForRedirect>
     */
    public function handle(User $user): Result
    {
        $currentTeam = $user->currentTeam;

        if ($currentTeam instanceof Team) {
            return Ok::of($currentTeam);
        }

        return $this->teams->findPersonalForUser($user)->match(
            some: static fn (Team $team): Ok => Ok::of($team),
            none: static fn (): Err => Err::of(new NoTeamForRedirect($user->id)),
        );
    }
}
