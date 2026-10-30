# Autenticación de usuarios con JWT y cookies en PHP

Esta guía implementa registro, login, logout y una ruta protegida utilizando:

- PHP sin framework
- Doctrine ORM
- MySQL
- JWT
- Cookies `HttpOnly`

La estructura actual del proyecto contiene la entidad `User`, pero todavía están vacíos el controlador de autenticación, las rutas y el punto de entrada principal.

## 1. Flujo de autenticación

```text
Registro
  -> Validar datos
  -> Hashear contraseña
  -> Guardar usuario

Login
  -> Buscar usuario
  -> Verificar contraseña
  -> Crear JWT
  -> Guardar JWT en cookie HttpOnly

Ruta protegida
  -> Leer cookie
  -> Validar JWT
  -> Permitir o rechazar la petición
```

## 2. Instalar la librería JWT

Desde la raíz del proyecto ejecuta:

```powershell
composer require firebase/php-jwt
```

## 3. Configurar las variables de entorno

Edita `.env` y agrega:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=gestion_productos
DB_USER=root
DB_PASSWORD=root

JWT_SECRET=pon_aqui_una_clave_larga_y_aleatoria
JWT_EXPIRE_SECONDS=3600
APP_ENV=development
```

Para generar una clave segura:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

No subas `.env` al repositorio.

## 4. Crear el servicio JWT

Crea el archivo `app/services/JwtService.php`:

```php
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
```

`sub` identifica al usuario y `exp` define la fecha de expiración del token.

## 5. Crear funciones auxiliares

Crea `app/helpers.php`:

```php
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
```

La cookie usa:

- `HttpOnly`: JavaScript no puede leer el JWT.
- `Secure`: en producción solo se envía por HTTPS.
- `SameSite=Lax`: reduce el riesgo de ataques CSRF.

## 6. Implementar `AuthController`

Edita `app/controllers/AuthController.php`:

```php
<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

require_once __DIR__ . '/../helpers.php';

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

        $token = $this->jwtService->createToken(
            $user->getId(),
            $user->getEmail()
        );

        setAuthCookie($token);

        jsonResponse([
            'message' => 'Usuario registrado correctamente',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ]
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
```

La contraseña nunca se guarda en texto plano. Se guarda mediante `password_hash()` y se comprueba con `password_verify()`.

## 7. Crear las rutas

Edita `routes/web.php`:

```php
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
```

Las rutas disponibles serán:

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/register` | Registrar usuario |
| `POST` | `/api/login` | Iniciar sesión |
| `POST` | `/api/logout` | Cerrar sesión |
| `GET` | `/api/me` | Obtener usuario autenticado |

## 8. Configurar el punto de entrada

Edita `public/index.php`:

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

require_once __DIR__ . '/../routes/web.php';
```

## 9. Ejecutar las migraciones

Comprueba el estado:

```powershell
composer migration:status
```

Si todavía no ejecutaste la migración:

```powershell
composer migration:migrate
```

La migración existente crea la tabla `users` con `name`, `email`, `password` y `createdAt`.

## 10. Levantar el servidor

Desde la carpeta del proyecto:

```powershell
php -S localhost:8000 -t public
```

## 11. Probar el registro

```powershell
Invoke-RestMethod `
  -Method POST `
  -Uri http://localhost:8000/api/register `
  -ContentType "application/json" `
  -Body '{"name":"Andrés","email":"andres@example.com","password":"Password123"}'
```

Respuesta esperada:

```json
{
  "message": "Usuario registrado correctamente",
  "user": {
    "id": 1,
    "name": "Andrés",
    "email": "andres@example.com"
  }
}
```

## 12. Probar el login

```powershell
Invoke-RestMethod `
  -Method POST `
  -Uri http://localhost:8000/api/login `
  -ContentType "application/json" `
  -Body '{"email":"andres@example.com","password":"Password123"}'
```

El servidor enviará una cookie llamada `auth_token`.

## 13. Probar la ruta protegida

Abre en el navegador:

```text
http://localhost:8000/api/me
```

Desde JavaScript:

```javascript
fetch('/api/me', {
  credentials: 'include'
})
  .then(response => response.json())
  .then(data => console.log(data));
```

La opción `credentials: 'include'` permite enviar cookies en la petición.

## 14. Proteger otras rutas

Crea `app/middleware/auth.php`:

```php
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
```

Después puedes utilizarlo en una ruta:

```php
if ($method === 'GET' && $uri === '/api/products') {
    $user = requireAuth(new \App\Services\JwtService());

    // Aquí el usuario ya está autenticado.
    // Continuar con la lógica de productos.
}
```

## 15. Logout

El frontend debe enviar:

```text
POST /api/logout
```

El servidor eliminará la cookie `auth_token`.

Un JWT ya emitido continúa siendo válido hasta su expiración. Para invalidarlo inmediatamente se necesita una estrategia adicional, como guardar tokens revocados o utilizar tokens de corta duración junto con refresh tokens.

## 16. Recomendaciones para producción

- Usar HTTPS.
- Configurar `secure => true`.
- Mantener `HttpOnly => true`.
- Usar `SameSite=Strict` o `Lax`.
- No guardar JWT en `localStorage`.
- Agregar protección CSRF para operaciones que modifican datos.
- Implementar rate limiting en login.
- Usar tokens de corta duración.
- Mantener `JWT_SECRET` fuera del repositorio.
- Cambiar `isDevMode: true` de Doctrine en producción.
- Validar y limitar el tamaño de los datos recibidos.

## 17. Archivos que se agregan o modifican

```text
.env
composer.json
app/helpers.php
app/services/JwtService.php
app/controllers/AuthController.php
app/middleware/auth.php
routes/web.php
public/index.php
```

