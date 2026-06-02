<?php

declare(strict_types=1);

namespace App\Infrastructure\Teams;

use App\Application\Teams\Contracts\MembershipReader;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Monads\None;
use App\Monads\Option;
use App\Monads\Some;

final class EloquentMembershipReader implements MembershipReader
{
    public function roleFor(User $user, Team $team): Option
    {
        $membership = Membership::query()
            ->where('user_id', '=', $user->id)
            ->where('team_id', '=', $team->id)
            ->first();

        if (! $membership instanceof Membership) {
            return None::instance();
        }

        $roleValue = $membership->getRawOriginal('role');
        $role = is_string($roleValue) ? TeamRole::tryFrom($roleValue) : null;

        return $role === null ? None::instance() : new Some($role);
    }
}
