<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OnlyHost
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user(), 404);

        abort_unless(in_array($request->user()->email, config('auth.admin_emails')), 404);

        return $next($request);
    }
}
