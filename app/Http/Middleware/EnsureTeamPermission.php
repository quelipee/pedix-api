<?php

namespace App\Http\Middleware;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $teamPermission = TeamPermission::tryFrom($permission);

        abort_unless($user instanceof User && $teamPermission instanceof TeamPermission, 403);

        $team = $user->currentTeam;

        abort_unless(
            $team instanceof Team && $user->hasTeamPermission($team, $teamPermission),
            403,
        );

        return $next($request);
    }
}
