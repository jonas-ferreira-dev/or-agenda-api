<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_platform_admin) {
            return response()->json([
                'message' => 'Acesso permitido apenas para administradores da plataforma.',
            ], 403);
        }

        return $next($request);
    }
}