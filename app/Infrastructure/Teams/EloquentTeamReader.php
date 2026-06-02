<?php

declare(strict_types=1);

namespace App\Infrastructure\Teams;

use App\Application\Teams\Contracts\TeamReader;
use App\Domain\Teams\ValueObjects\TeamSlug;
use App\Models\Team;
use App\Models\User;
use App\Monads\None;
use App\Monads\Option;
use App\Monads\Some;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentTeamReader implements TeamReader
{
    public function findBySlug(TeamSlug $slug): Option
    {
        $team = Team::query()->where('slug', '=', $slug->value)->first();

        return $team instanceof Team ? new Some($team) : None::instance();
    }

    public function findPersonalForUser(User $user): Option
    {
        $team = Team::query()
            ->where('is_personal', '=', true)
            ->whereHas('members', static fn (Builder $query) => $query->where('users.id', '=', $user->id))
            ->first();

        return $team instanceof Team ? new Some($team) : None::instance();
    }

    public function findFallbackForUser(User $user, ?Team $excluding = null): Option
    {
        $excludingId = $excluding?->id;

        $query = DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('team_members.user_id', '=', $user->id)
            ->select('teams.id');

        if ($excludingId !== null) {
            $query->where('teams.id', '!=', $excludingId);
        }

        $teamId = $query->orderBy('teams.name')->value('id');

        $team = Team::query()->find($teamId);

        return $team instanceof Team ? new Some($team) : None::instance();
    }
}
