<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestNumber = strtoupper(trim((string) ($_POST['request_number'] ?? '')));
    $lastFour = preg_replace('/\D+/', '', (string) ($_POST['last_four'] ?? ''));

    if ($requestNumber === '' || strlen($lastFour) !== 4) {
        $error = 'Ingrese el número de solicitud y los cuatro últimos dígitos de la cédula.';
    } else {
        $stmt = $pdo->prepare('SELECT request_number, first_name, last_name, national_id, status, submitted_at, admin_observation FROM requests WHERE request_number = ? LIMIT 1');
        $stmt->execute([$requestNumber]);
        $row = $stmt->fetch();
        $storedDigits = $row ? preg_replace('/\D+/', '', $row['national_id']) : '';

        if (!$row || substr($storedDigits, -4) !== $lastFour) {
            $error = 'Los datos ingresados no coinciden con ningún registro.';
        } else {
            $result = $row;
        }
    }
}

$statusMessages = [
    'RECIBIDA' => 'Su solicitud fue recibida correctamente y está pendiente de revisión.',
    'EN_REVISION' => 'La Dirección Nacional de Recursos Humanos está verificando la información y las evidencias presentadas.',
    'VALIDADA' => 'Su carné institucional ha sido validado satisfactoriamente. No requiere ninguna acción adicional.',
    'OBSERVADA' => 'La solicitud presenta una situación que requiere aclaración. Recursos Humanos se comunicará mediante el teléfono o correo registrado.',
    'EVIDENCIA_INSUFICIENTE' => 'Una o más evidencias no permiten completar la validación. Recibirá instrucciones para aportar la información requerida.',
    'CODIGO_DUPLICADO' => 'El código de barras requiere una comprobación adicional por posible duplicidad.',
    'CODIGO_ILEGIBLE' => 'El código de barras no pudo confirmarse y requiere revisión adicional.',
    'CARNE_DETERIORADO' => 'El carné fue identificado como deteriorado y el caso fue enviado para seguimiento.',
    'EXTRAVIADO_REPORTADO' => 'El carné figura como extraviado y reportado. El caso se encuentra en seguimiento.',
    'EXTRAVIADO_NO_REPORTADO' => 'Se registró la declaración de extravío. Debe completarse el procedimiento institucional correspondiente.',
    'VERIFICACION_PRESENCIAL' => 'El caso requiere una verificación presencial. Recursos Humanos comunicará el procedimiento correspondiente.',
    'RECHAZADA' => 'La solicitud no pudo ser validada. Recursos Humanos se comunicará para indicar el procedimiento aplicable.',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consultar estado | <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand">
            <img class="brand-logo" src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
            <div class="logo-fallback" style="display:none">DNRH</div>
            <div><h1>Consulta de solicitud</h1><p>Dirección Nacional de Recursos Humanos</p></div>
        </div>
        <nav class="top-nav"><a href="index.php">Volver al formulario</a></nav>
    </div>
</header>

<main class="container main-content">
    <section class="card" style="max-width:760px;margin-left:auto;margin-right:auto">
        <h2 class="card-title">Consultar estado</h2>
        <p class="card-subtitle">Ingrese los datos enviados a su correo al finalizar el formulario.</p>

        <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

        <form method="post">
            <div class="form-grid">
                <div class="field half"><label class="required" for="request_number">Número de solicitud</label><input id="request_number" name="request_number" placeholder="VC-2026-XXXXXXXXXX" required value="<?= e($_POST['request_number'] ?? '') ?>"></div>
                <div class="field half"><label class="required" for="last_four">Últimos 4 dígitos de la cédula</label><input id="last_four" name="last_four" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required></div>
            </div>
            <div class="actions"><button class="btn btn-primary" type="submit">Consultar estado</button></div>
        </form>

        <?php if ($result): ?>
            <div class="notice success" style="margin-top:28px">
                <span class="status-badge"><?= e($config['statuses'][$result['status']] ?? $result['status']) ?></span>
                <h3><?= e($result['first_name']) ?> <?= e(substr($result['last_name'], 0, 1)) ?>.</h3>
                <p><strong>Solicitud:</strong> <?= e($result['request_number']) ?></p>
                <p><strong>Fecha de envío:</strong> <?= e($result['submitted_at']) ?></p>
                <p><?= e($statusMessages[$result['status']] ?? 'Su solicitud se encuentra en proceso de revisión.') ?></p>
                <?php if (!empty($result['admin_observation']) && in_array($result['status'], ['OBSERVADA', 'EVIDENCIA_INSUFICIENTE', 'VERIFICACION_PRESENCIAL', 'RECHAZADA'], true)): ?>
                    <p><strong>Orientación:</strong> <?= e($result['admin_observation']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer class="site-footer"><div class="container">Dirección Nacional de Recursos Humanos · Policía Nacional</div></footer>
</body>
</html>
