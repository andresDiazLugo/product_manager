<?php

use App\Services\JwtService;
use Throwable;

function requireAuth(JwtService $jwtService): object
{
    $token = $_COOKIE['auth_token'] ?? null;

    if ($token === null) {
        jsonResponse([
            'message' => 'No autenticado'
        ], 401);
    }

    try {
        return $jwtService->validateToken($token);
    } catch (Throwable) {
        jsonResponse([
            'message' => 'Token inválido o expirado'
        ], 401);
    }
}