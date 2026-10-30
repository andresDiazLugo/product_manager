<?php

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');

    echo json_encode($data);
    exit;
}

function getJsonBody(): array
{
    $body = file_get_contents('php://input');

    $data = json_decode($body, true);

    if (!is_array($data)) {
        jsonResponse([
            'message' => 'El cuerpo de la petición no es válido'
        ], 400);
    }

    return $data;
}

function setAuthCookie(string $token): void
{
    setcookie('auth_token', $token, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => ($_ENV['APP_ENV'] ?? 'development') === 'production',
        'samesite' => 'Lax',
    ]);
}

function clearAuthCookie(): void
{
    setcookie('auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => ($_ENV['APP_ENV'] ?? 'development') === 'production',
        'samesite' => 'Lax',
    ]);
}