<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

require_once __DIR__ . '/../helpers/helpers.php';

class AuthController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JwtService $jwtService
    ) {
    }

    public function register(): never
    {
        $data = getJsonBody();

        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            jsonResponse([
                'message' => 'Nombre, email y contraseña son obligatorios'
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse([
                'message' => 'El email no es válido'
            ], 422);
        }

        if (strlen($password) < 8) {
            jsonResponse([
                'message' => 'La contraseña debe tener al menos 8 caracteres'
            ], 422);
        }

        $existingUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser !== null) {
            jsonResponse([
                'message' => 'El email ya está registrado'
            ], 409);
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setPassword(password_hash($password, PASSWORD_DEFAULT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        jsonResponse([
            'message' => 'Usuario registrado exitosamente'
        ], 201);
    }

    public function login(): never
    {
        $data = getJsonBody();

        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (
            $user === null ||
            !password_verify($password, $user->getPassword())
        ) {
            jsonResponse([
                'message' => 'Email o contraseña incorrectos'
            ], 401);
        }

        $token = $this->jwtService->createToken(
            $user->getId(),
            $user->getEmail()
        );

        setAuthCookie($token);

        jsonResponse([
            'message' => 'Login correcto',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ]
        ]);
    }

    public function logout(): never
    {
        clearAuthCookie();

        jsonResponse([
            'message' => 'Sesión cerrada'
        ]);
    }

    public function me(): never
    {
        $token = $_COOKIE['auth_token'] ?? null;

        if ($token === null) {
            jsonResponse([
                'message' => 'No autenticado'
            ], 401);
        }

        try {
            $payload = $this->jwtService->validateToken($token);

            $user = $this->entityManager
                ->getRepository(User::class)
                ->find((int) $payload->sub);

            if ($user === null) {
                jsonResponse([
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            jsonResponse([
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                ]
            ]);
        } catch (Throwable) {
            jsonResponse([
                'message' => 'Token inválido o expirado'
            ], 401);
        }
    }
}
