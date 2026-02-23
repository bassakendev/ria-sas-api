<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Authentication required',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Access denied',
                'code' => 'ROLE_NOT_ALLOWED',
            ], 403);
        }

        return $next($request);
    }
}
