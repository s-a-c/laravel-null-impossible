<?php

declare(strict_types=1);

namespace App\Infrastructure\Teams;

use App\Application\Teams\Contracts\CurrentTeamWriter;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

final class LaravelCurrentTeamWriter implements CurrentTeamWriter
{
    public function persist(User $user, Team $team): void
    {
        $user->update(['current_team_id' => $team->id]);
        $user->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);
    }
}
