<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::query()->where('api_token', $token)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
