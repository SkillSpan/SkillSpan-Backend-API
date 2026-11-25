<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFromHttpOnlyCookie
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            return $next($request);
        }

        $token = $request->cookie((string) config('auth_cookie.name'));

        if (! is_string($token) || $token === '') {
            return $next($request);
        }

        if (! in_array(strtoupper($request->method()), self::SAFE_METHODS, true)
            && ! $this->isTrustedBrowserRequest($request)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'The request origin could not be verified.',
                'code' => 'CSRF_ORIGIN_MISMATCH',
            ], 419);
        }

        // Sanctum still performs token lookup, expiration checks and account
        // authentication. This middleware only moves the HttpOnly cookie into
        // the standard Bearer channel before auth:sanctum runs.
        $request->headers->set('Authorization', 'Bearer '.$token);

        return $next($request);
    }

    private function isTrustedBrowserRequest(Request $request): bool
    {
        if (! hash_equals('XMLHttpRequest', (string) $request->header('X-Requested-With', ''))) {
            return false;
        }

        $origin = rtrim((string) $request->header('Origin', ''), '/');
        if ($origin === '') {
            return false;
        }

        $allowed = array_map(
            static fn (string $value): string => rtrim($value, '/'),
            array_filter(config('cors.allowed_origins', []), 'is_string')
        );

        return in_array($origin, $allowed, true);
    }
}
