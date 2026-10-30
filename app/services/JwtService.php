<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private int $expiration;

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET'];
        $this->expiration = (int) ($_ENV['JWT_EXPIRE_SECONDS'] ?? 3600);
    }

    public function createToken(int $userId, string $email): string
    {
        $now = time();

        $payload = [
            'iss' => 'gestor-productos',
            'iat' => $now,
            'exp' => $now + $this->expiration,
            'sub' => $userId,
            'email' => $email,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validateToken(string $token): object
    {
        return JWT::decode(
            $token,
            new Key($this->secret, 'HS256')
        );
    }
}