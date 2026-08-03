<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Panel V3') ?> | <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css?v=20260731">
    <link rel="stylesheet" href="admin_v3/assets/admin-v3.css?v=1">
</head>
<body>
<header class="v3-header">
    <div class="v3-shell v3-header-inner">
        <div class="v3-brand">
            <img src="assets/img/logo.png" alt="Logo">
            <div>
                <h1>Verif-Carné V3</h1>
                <p>Panel institucional de validación</p>
            </div>
        </div>

        <nav class="v3-nav">
            <a href="admin.php">Dashboard</a>
            <a href="admin.php?page=solicitudes">Solicitudes</a>
            <a href="admin_export.php">Exportar</a>
            <a href="index.php">Portal público</a>
            <a href="admin.php?logout=1">Cerrar sesión</a>
        </nav>
    </div>
</header>

<main class="v3-shell v3-main">
