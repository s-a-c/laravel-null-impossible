<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueTeamInvitation implements ValidationRule
{
    public function __construct(
        protected Team $team,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('The email address must be a string.'));

            return;
        }

        $email = strtolower($value);

        $isMember = DB::table('team_members')
            ->join('users', 'users.id', '=', 'team_members.user_id')
            ->where('team_members.team_id', '=', $this->team->id)
            ->whereRaw('LOWER(users.email) = ?', [$email])
            ->exists();

        if ($isMember) {
            $fail(__('This user is already a member of the team.'));

            return;
        }

        $hasPendingInvitation = DB::table('team_invitations')
            ->where('team_id', '=', $this->team->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('accepted_at', '=', null)
            ->where(static function (Builder $query): void {
                $query->where('expires_at', '=', null)->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasPendingInvitation) {
            $fail(__('An invitation has already been sent to this email address.'));
        }
    }
}
