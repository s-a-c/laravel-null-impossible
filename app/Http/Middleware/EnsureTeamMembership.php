<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Teams\ResolveCurrentTeam;
use App\Application\Teams\SwitchCurrentTeam;
use App\Application\Teams\TeamContext;
use App\Domain\Errors\DomainError;
use App\Domain\Teams\ValueObjects\TeamSlug;
use App\Enums\TeamRole;
use App\Http\Support\DomainErrorRenderer;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    public function __construct(
        private readonly ResolveCurrentTeam $resolveCurrentTeam,
        private readonly SwitchCurrentTeam $switchCurrentTeam,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $teamSlug = $this->resolveTeamSlug($request);

        if ($teamSlug === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $requiredRole = null;

        if ($minimumRole !== null) {
            $requiredRole = TeamRole::tryFrom($minimumRole);

            if ($requiredRole === null) {
                abort(Response::HTTP_FORBIDDEN);
            }
        }

        $result = $this->resolveCurrentTeam->handle($user, TeamSlug::fromString($teamSlug), $requiredRole);

        return $result->match(
            ok: function (TeamContext $context) use ($request, $next): Response {
                if ($request->route('current_team') !== null && ! $context->user->isCurrentTeam($context->team)) {
                    $this->switchCurrentTeam->handle($context->user, $context->team)->match(
                        ok: static fn (): null => null,
                        err: static fn (DomainError $error) => DomainErrorRenderer::abortFor($error),
                    );
                }

                return $next($request);
            },
            err: static fn (DomainError $error) => DomainErrorRenderer::abortFor($error),
        );
    }

    protected function resolveTeamSlug(Request $request): ?string
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if ($team instanceof Team) {
            return $team->slug;
        }

        if (is_string($team) && $team !== '') {
            return $team;
        }

        return null;
    }
}
