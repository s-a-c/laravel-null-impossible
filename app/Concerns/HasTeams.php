<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Application\Teams\Contracts\MembershipReader;
use App\Application\Teams\Contracts\TeamReader;
use App\Application\Teams\SwitchCurrentTeam;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Support\TeamPermissions;
use App\Support\UserTeam;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasTeams
{
    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this
            ->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function ownedTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('role', TeamRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    #[\Deprecated(message: 'Prefer TeamReader::findPersonalForUser() in application code.')]
    public function personalTeam(): ?Team
    {
        return app(TeamReader::class)
            ->findPersonalForUser($this)
            ->match(some: static fn (Team $team): Team => $team, none: static fn (): null => null);
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        return app(SwitchCurrentTeam::class)->handle($this, $team)->isOk();
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return app(MembershipReader::class)->roleFor($this, $team)->isSome();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->roleForTeam($team) === TeamRole::Owner;
    }

    /**
     * Get the user's role on the given team.
     */
    #[\Deprecated(message: 'Prefer MembershipReader::roleFor() in application code.')]
    public function teamRole(Team $team): ?TeamRole
    {
        return $this->roleForTeam($team);
    }

    private function roleForTeam(Team $team): ?TeamRole
    {
        return app(MembershipReader::class)
            ->roleFor($this, $team)
            ->match(some: static fn (TeamRole $role): TeamRole => $role, none: static fn (): null => null);
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this
            ->teams()
            ->get()
            ->reject(fn (Team $team): bool => ! $includeCurrent && $this->isCurrentTeam($team))
            ->map(fn (Team $team) => $this->toUserTeam($team))
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $role = $this->roleForTeam($team);

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            role: $role instanceof TeamRole ? $role->value : null,
            roleLabel: $role instanceof TeamRole ? $role->label() : null,
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        $role = $this->roleForTeam($team);
        $hasRole = $role instanceof TeamRole;

        return new TeamPermissions(
            canUpdateTeam: $hasRole && $role->hasPermission(TeamPermission::UpdateTeam),
            canDeleteTeam: $hasRole && $role->hasPermission(TeamPermission::DeleteTeam),
            canAddMember: $hasRole && $role->hasPermission(TeamPermission::AddMember),
            canUpdateMember: $hasRole && $role->hasPermission(TeamPermission::UpdateMember),
            canRemoveMember: $hasRole && $role->hasPermission(TeamPermission::RemoveMember),
            canCreateInvitation: $hasRole && $role->hasPermission(TeamPermission::CreateInvitation),
            canCancelInvitation: $hasRole && $role->hasPermission(TeamPermission::CancelInvitation),
        );
    }

    #[\Deprecated(message: 'Prefer TeamReader::findFallbackForUser() in application code.')]
    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return app(TeamReader::class)
            ->findFallbackForUser($this, $excluding)
            ->match(some: static fn (Team $team): Team => $team, none: static fn (): null => null);
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        $role = $this->roleForTeam($team);

        return $role instanceof TeamRole && $role->hasPermission($permission);
    }
}
