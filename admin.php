<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (hash_equals($config['admin_user'], $user) && hash_equals($config['admin_password'], $password)) {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_user'] = $user;
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Usuario o contraseña incorrectos.';
}

$authenticated = !empty($_SESSION['admin_authenticated']);
$selected = null;
$requests = [];
$summary = [];

if ($authenticated) {
    $statusFilter = trim((string) ($_GET['status'] ?? ''));
    $search = trim((string) ($_GET['q'] ?? ''));
    $where = [];
    $params = [];

    if ($statusFilter !== '') {
        $where[] = 'status = :status';
        $params['status'] = $statusFilter;
    }
    if ($search !== '') {
        $where[] = '(request_number LIKE :q OR position_number LIKE :q OR national_id LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR barcode_value LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

    $sql = 'SELECT * FROM requests' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY submitted_at DESC LIMIT 300';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    $summaryStmt = $pdo->query('SELECT status, COUNT(*) total FROM requests GROUP BY status');
    foreach ($summaryStmt as $row) {
        $summary[$row['status']] = (int) $row['total'];
    }

    if (isset($_GET['view'])) {
        $detail = $pdo->prepare('SELECT * FROM requests WHERE id = ?');
        $detail->execute([(int) $_GET['view']]);
        $selected = $detail->fetch() ?: null;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administración | <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand">
            <img class="brand-logo" src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
            <div class="logo-fallback" style="display:none">DNRH</div>
            <div><h1>Panel administrativo</h1><p>Validación de carnés institucionales</p></div>
        </div>
        <?php if ($authenticated): ?><nav class="top-nav"><a href="index.php">Portal público</a><a href="admin.php?logout=1">Cerrar sesión</a></nav><?php endif; ?>
    </div>
</header>

<main class="container main-content">
<?php if (!$authenticated): ?>
    <section class="card" style="max-width:520px;margin-left:auto;margin-right:auto">
        <h2 class="card-title">Acceso administrativo</h2>
        <p class="card-subtitle">Ingrese sus credenciales autorizadas.</p>
        <?php if ($loginError): ?><div class="notice error"><?= e($loginError) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="login" value="1">
            <div class="form-grid">
                <div class="field full"><label for="username">Usuario</label><input id="username" name="username" required></div>
                <div class="field full"><label for="password">Contraseña</label><input type="password" id="password" name="password" required></div>
            </div>
            <div class="actions"><button class="btn btn-primary" type="submit">Ingresar</button></div>
        </form>
    </section>
<?php else: ?>
    <section class="card">
        <h2 class="card-title">Resumen</h2>
        <div class="actions">
            <?php foreach ($config['statuses'] as $key => $label): ?>
                <?php if (($summary[$key] ?? 0) > 0): ?><span class="status-badge"><?= e($label) ?>: <?= (int) $summary[$key] ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($selected): ?>
        <section class="card">
            <div class="actions" style="justify-content:space-between;margin-top:0">
                <div><h2 class="card-title"><?= e($selected['rank_name']) ?> <?= e($selected['first_name'] . ' ' . $selected['last_name']) ?></h2><p class="card-subtitle"><?= e($selected['request_number']) ?></p></div>
                <a class="btn btn-outline" href="admin.php">Cerrar detalle</a>
            </div>

            <div class="form-grid">
                <div class="field"><strong>Posición</strong><br><?= e($selected['position_number']) ?></div>
                <div class="field"><strong>Cédula</strong><br><?= e($selected['national_id']) ?></div>
                <div class="field"><strong>Promoción</strong><br><?= e($selected['promotion_type'] . ' ' . $selected['promotion_number']) ?></div>
                <div class="field"><strong>Dirección Nacional</strong><br><?= e($selected['national_directorate']) ?></div>
                <div class="field"><strong>Zona / Área</strong><br><?= e($selected['zone_name'] . ' / ' . $selected['area_name']) ?></div>
                <div class="field"><strong>Servicio</strong><br><?= e($selected['service_name']) ?></div>
                <div class="field"><strong>Estado del carné</strong><br><?= e($selected['card_condition']) ?></div>
                <div class="field"><strong>Código de barras</strong><br><?= e($selected['barcode_value'] ?: 'No indicado') ?></div>
                <div class="field"><strong>Fecha</strong><br><?= e($selected['submitted_at']) ?></div>
            </div>

            <div class="actions">
                <a class="btn btn-whatsapp" target="_blank" rel="noopener" href="<?= e(whatsappUrl($selected['phone'], $selected['request_number'])) ?>">Abrir WhatsApp</a>
                <a class="btn btn-outline" href="mailto:<?= e($selected['email']) ?>?subject=Solicitud%20<?= rawurlencode($selected['request_number']) ?>">Enviar correo</a>
            </div>

            <h3 class="section-title">Evidencias</h3>
            <div class="evidence-grid">
                <?php foreach (['card_front_path' => 'Frente', 'card_back_path' => 'Reverso', 'person_with_card_path' => 'Persona con carné'] as $field => $label): ?>
                    <div><strong><?= e($label) ?></strong><?php if ($selected[$field]): ?><a href="<?= e($selected[$field]) ?>" target="_blank"><img src="<?= e($selected[$field]) ?>" alt="<?= e($label) ?>"></a><?php else: ?><div class="upload-example">Sin evidencia</div><?php endif; ?></div>
                <?php endforeach; ?>
            </div>

            <h3 class="section-title">Revisión</h3>
            <form method="post" action="admin_update.php">
                <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
                <div class="form-grid">
                    <div class="field half"><label for="status">Estado</label><select id="status" name="status" required><?php foreach ($config['statuses'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $selected['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div class="field half"><label for="admin_observation">Observación / orientación</label><textarea id="admin_observation" name="admin_observation"><?= e($selected['admin_observation']) ?></textarea></div>
                </div>
                <div class="actions"><button class="btn btn-primary" type="submit">Guardar revisión</button></div>
            </form>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2 class="card-title">Solicitudes</h2>
        <form method="get" class="form-grid" style="margin-bottom:20px">
            <div class="field half"><label for="q">Buscar</label><input id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Solicitud, posición, cédula, nombre o código"></div>
            <div class="field"><label for="status">Estado</label><select id="status" name="status"><option value="">Todos</option><?php foreach ($config['statuses'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($_GET['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit">Filtrar</button></div>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Solicitud</th><th>Funcionario</th><th>Ubicación</th><th>Carné / código</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $row): ?>
                    <tr>
                        <td><strong><?= e($row['request_number']) ?></strong><br><small><?= e($row['submitted_at']) ?></small></td>
                        <td><?= e($row['rank_name'] . ' ' . $row['first_name'] . ' ' . $row['last_name']) ?><br><small>Pos. <?= e($row['position_number']) ?></small></td>
                        <td><?= e($row['national_directorate']) ?><br><small><?= e($row['service_name']) ?></small></td>
                        <td><?= e($row['card_condition']) ?><br><small><?= e($row['barcode_value'] ?: 'Sin código') ?></small></td>
                        <td><span class="status-badge"><?= e($config['statuses'][$row['status']] ?? $row['status']) ?></span></td>
                        <td><a class="btn btn-outline" href="admin.php?view=<?= (int) $row['id'] ?>">Revisar</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$requests): ?><tr><td colspan="6">No hay solicitudes que coincidan con el filtro.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
</main>
<footer class="site-footer"><div class="container">Dirección Nacional de Recursos Humanos · Policía Nacional</div></footer>
</body>
</html>
