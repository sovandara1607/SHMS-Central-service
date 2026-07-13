<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service-to-service auth for the Application Server calling this API
 * synchronously. Not a user-facing auth scheme — there is no login here.
 */
class EnsureCentralServiceApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.central_service.api_key');
        $provided = $request->header('X-Central-Service-Key');

        if (! $expected || ! $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
