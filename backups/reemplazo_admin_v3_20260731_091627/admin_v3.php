<?php

declare(strict_types=1);

require __DIR__ . '/admin_v3/bootstrap.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_v3.php');
    exit;
}

if (empty($_SESSION['admin_authenticated'])) {
    $loginError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $user = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (
            hash_equals((string) $config['admin_user'], $user)
            && hash_equals((string) $config['admin_password'], $password)
        ) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_user'] = $user;
            header('Location: admin_v3.php');
            exit;
        }

        $loginError = 'Usuario o contraseña incorrectos.';
    }
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Acceso | <?= e($config['app_name']) ?></title>
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="admin_v3/assets/admin-v3.css?v=1">
    </head>
    <body class="v3-login-body">
        <section class="v3-login-card">
            <img src="assets/img/logo.png" alt="Logo">
            <h1>Verif-Carné V3</h1>
            <p>Acceso administrativo</p>

            <?php if ($loginError): ?>
                <div class="notice error"><?= e($loginError) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="login" value="1">
                <label>Usuario<input name="username" required autocomplete="username"></label>
                <label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label>
                <button class="v3-button primary full" type="submit">Ingresar</button>
            </form>
        </section>
    </body>
    </html>
    <?php
    exit;
}

$page = trim((string) ($_GET['page'] ?? 'dashboard'));

$allowed = [
    'dashboard' => __DIR__ . '/admin_v3/dashboard.php',
    'solicitudes' => __DIR__ . '/admin_v3/solicitudes.php',
    'expediente' => __DIR__ . '/admin_v3/expediente.php',
];

require $allowed[$page] ?? $allowed['dashboard'];
