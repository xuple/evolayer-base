<?php

namespace EvoDevOps\Base\Http\Middleware;

use Closure;
use EvoDevOps\Base\Contracts\AdminGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function __construct(private readonly AdminGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->gate->isAdmin($request->user()), 403);

        return $next($request);
    }
}
