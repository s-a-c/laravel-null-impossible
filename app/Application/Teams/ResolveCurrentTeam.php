<?php

declare(strict_types=1);

namespace App\Application\Teams;

use App\Application\Teams\Contracts\MembershipReader;
use App\Application\Teams\Contracts\TeamReader;
use App\Domain\Teams\Errors\TeamAccessDenied;
use App\Domain\Teams\ValueObjects\TeamSlug;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Monads\Err;
use App\Monads\Ok;
use App\Monads\Result;

final readonly class ResolveCurrentTeam
{
    public function __construct(
        private TeamReader $teams,
        private MembershipReader $memberships,
    ) {}

    /**
     * @return Result<TeamContext, TeamAccessDenied>
     */
    public function handle(User $user, TeamSlug $teamSlug, ?TeamRole $minimumRole = null): Result
    {
        return $this->teams->findBySlug($teamSlug)->match(
            some: fn (Team $team): Result => $this->memberships->roleFor($user, $team)->match(
                some: static function (TeamRole $role) use ($user, $team, $teamSlug, $minimumRole): Result {
                    if ($minimumRole instanceof TeamRole && ! $role->isAtLeast($minimumRole)) {
                        return Err::of(new TeamAccessDenied($teamSlug->value, $user->id));
                    }

                    return Ok::of(new TeamContext($user, $team));
                },
                none: static fn (): Err => Err::of(new TeamAccessDenied($teamSlug->value, $user->id)),
            ),
            none: static fn (): Err => Err::of(new TeamAccessDenied($teamSlug->value, $user->id)),
        );
    }
}
