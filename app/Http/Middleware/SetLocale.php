<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = strtolower(substr($request->getPreferredLanguage(['bn', 'en']) ?? 'en', 0, 2));

        if (in_array($locale, ['bn', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
