<?php

declare(strict_types=1);

namespace App\Application\Teams;

use App\Application\Teams\Contracts\TeamReader;
use App\Domain\Teams\Errors\PersonalTeamNotFound;
use App\Domain\Teams\Errors\TeamSwitchRejected;
use App\Models\Team;
use App\Models\User;
use App\Monads\Err;
use App\Monads\Result;
use App\Monads\Unit;

final readonly class SwitchToPersonalTeam
{
    public function __construct(
        private TeamReader $teams,
        private SwitchCurrentTeam $switchCurrentTeam,
    ) {}

    /**
     * @return Result<Unit, TeamSwitchRejected|PersonalTeamNotFound>
     */
    public function handle(User $user): Result
    {
        return $this->teams->findPersonalForUser($user)->match(
            some: fn (Team $team): Result => $this->switchCurrentTeam->handle($user, $team),
            none: static fn (): Err => Err::of(new PersonalTeamNotFound($user->id)),
        );
    }
}
