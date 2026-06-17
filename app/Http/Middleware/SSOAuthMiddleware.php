<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class SSOAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Token JWT tidak ditemukan.',
                'errors'  => null,
            ], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $jwksResponse = Http::get('https://iae-sso.virtualfri.id/api/v1/auth/jwks');
            $jwks = $jwksResponse->json();

            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);

            $request->merge(['auth_user' => (array) $decoded]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Token JWT tidak valid: ' . $e->getMessage(),
                'errors'  => null,
            ], 401);
        }

        return $next($request);
    }
}