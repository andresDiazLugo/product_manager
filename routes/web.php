<?php

use App\Controllers\AuthController;
use App\Services\JwtService;

$entityManager = require __DIR__ . '/../config/doctrine.php';

$authController = new AuthController(
    $entityManager,
    new JwtService()
);

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'GET' && ($uri === '/' || $uri === '')) {
    header('Location: /views/login.html');
    exit;
}

if ($method === 'POST' && $uri === '/register') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: /views/register.php?error=' . urlencode('Nombre o email inválido'));
        exit;
    }

    if (strlen($password) < 8) {
        header('Location: /views/register.php?error=' . urlencode('La contraseña debe tener al menos 8 caracteres'));
        exit;
    }

    $existingUser = $entityManager
        ->getRepository(\App\Models\User::class)
        ->findOneBy(['email' => $email]);

    if ($existingUser !== null) {
        header('Location: /views/register.php?error=' . urlencode('El email ya está registrado'));
        exit;
    }

    $user = new \App\Models\User();
    $user->setName($name);
    $user->setEmail($email);
    $user->setPassword(password_hash($password, PASSWORD_DEFAULT));

    $entityManager->persist($user);
    $entityManager->flush();

    header('Location: /views/login.html?success=registered');
    exit;
}

if ($method === 'POST' && $uri === '/api/register') {
    $authController->register();
}

if ($method === 'POST' && $uri === '/api/login') {
    $authController->login();
}

if ($method === 'POST' && $uri === '/api/logout') {
    $authController->logout();
}

if ($method === 'GET' && $uri === '/api/me') {
    $authController->me();
}

http_response_code(404);
header('Content-Type: application/json');

echo json_encode([
    'message' => 'Ruta no encontrada'
]);
