<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetTeamUrlDefaults
{
    /**
     * Set the default URL parameters for team-based routes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentTeam = $request->user()?->currentTeam;

        if ($currentTeam !== null) {
            URL::defaults(array_fill_keys(explode(',', 'current_team,team'), $currentTeam->slug));
        }

        return $next($request);
    }
}
