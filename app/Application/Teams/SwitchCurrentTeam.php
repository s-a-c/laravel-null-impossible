<?php

declare(strict_types=1);

namespace App\Application\Teams;

use App\Application\Teams\Contracts\CurrentTeamWriter;
use App\Application\Teams\Contracts\MembershipReader;
use App\Domain\Teams\Errors\TeamSwitchRejected;
use App\Models\Team;
use App\Models\User;
use App\Monads\Err;
use App\Monads\Ok;
use App\Monads\Result;
use App\Monads\Unit;

final readonly class SwitchCurrentTeam
{
    public function __construct(
        private MembershipReader $memberships,
        private CurrentTeamWriter $currentTeamWriter,
    ) {}

    /**
     * @return Result<Unit, TeamSwitchRejected>
     */
    public function handle(User $user, Team $team): Result
    {
        return $this->memberships->roleFor($user, $team)->match(
            some: function () use ($user, $team): Result {
                $this->currentTeamWriter->persist($user, $team);

                return Ok::of(Unit::instance());
            },
            none: static fn (): Err => Err::of(new TeamSwitchRejected($team->slug, $user->id)),
        );
    }
}
