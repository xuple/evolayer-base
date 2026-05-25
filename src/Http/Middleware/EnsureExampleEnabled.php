<?php

namespace Xuple\EvoLayer\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExampleEnabled
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        abort_unless(config("evolayer.base.examples.{$flag}"), 404);

        return $next($request);
    }
}
