<?php

declare(strict_types=1);

namespace App\Http\Responses\Concerns;

use App\Application\Teams\ResolveRedirectTeam;
use App\Domain\Errors\DomainError;
use App\Http\Support\DomainErrorRenderer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        return "/{$team->slug}{$redirect}";
    }

    protected function currentTeam(Request $request): Team
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return app(ResolveRedirectTeam::class)
            ->handle($user)
            ->match(
                ok: static fn (Team $team): Team => $team,
                err: static fn (DomainError $error) => DomainErrorRenderer::abortFor($error),
            );
    }
}
