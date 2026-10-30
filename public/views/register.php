<?php
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | Gestor de productos</title>
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
    <main class="container">
        <section class="card">
            <h1>Crear cuenta</h1>
            <p>Regístrate para comenzar a gestionar tus productos.</p>

            <?php if ($error !== null): ?>
                <div class="message" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/register">
                <label for="name">Nombre</label>
                <input id="name" name="name" type="text" required autocomplete="name">

                <label for="email">Email</label>
                <input id="email" name="email" type="email" required autocomplete="email">

                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">

                <button type="submit">Registrarme</button>
            </form>

            <p class="link">¿Ya tienes cuenta? <a href="/views/login.html">Inicia sesión</a></p>
        </section>
    </main>
</body>
</html>
