<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Teams\ValueObjects\InvitationState;
use App\Enums\TeamRole;
use Database\Factories\TeamInvitationFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['team_id', 'email', 'role', 'invited_by', 'expires_at', 'accepted_at'])]
class TeamInvitation extends Model
{
    /** @use HasFactory<TeamInvitationFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function (TeamInvitation $invitation): void {
            $code = $invitation->getAttribute('code');

            if (! is_string($code) || $code === '') {
                $invitation->code = Str::random(64);
            }
        });
    }

    /**
     * Get the team that the invitation belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who sent the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Determine if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->state() === InvitationState::Accepted;
    }

    /**
     * Determine if the invitation is pending.
     */
    public function isPending(): bool
    {
        return $this->state() === InvitationState::Pending;
    }

    /**
     * Determine if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->state() === InvitationState::Expired;
    }

    /**
     * Get the invitation state from persisted boundary fields.
     */
    public function state(): InvitationState
    {
        if ($this->getAttribute('accepted_at') instanceof DateTimeInterface) {
            return InvitationState::Accepted;
        }

        $expiresAt = $this->getAttribute('expires_at');

        if ($expiresAt instanceof DateTimeInterface && $expiresAt->getTimestamp() < now()->getTimestamp()) {
            return InvitationState::Expired;
        }

        return InvitationState::Pending;
    }

    /**
     * Scope invitations that are still actionable.
     *
     * @param  Builder<TeamInvitation>  $query
     * @return Builder<TeamInvitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->where('accepted_at', '=', null)
            ->where(static function (Builder $query): void {
                $query->where('expires_at', '=', null)->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
