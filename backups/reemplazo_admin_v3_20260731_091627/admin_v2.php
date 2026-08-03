<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_v2.php');
    exit;
}

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
        header('Location: admin_v2.php');
        exit;
    }

    $loginError = 'Usuario o contraseña incorrectos.';
}

$authenticated = !empty($_SESSION['admin_authenticated']);
$requests = [];
$selected = null;
$history = [];
$units = [];
$summary = [];

function dateEsV2(?string $value, bool $withTime = false): string
{
    if (!$value) {
        return 'No indicada';
    }

    try {
        return (new DateTimeImmutable($value))->format($withTime ? 'd/m/Y h:i A' : 'd/m/Y');
    } catch (Throwable) {
        return $value;
    }
}

function expirationV2(?string $value): array
{
    if (!$value) {
        return ['label' => 'Sin fecha', 'class' => 'neutral'];
    }

    $days = (int) (new DateTimeImmutable('today'))
        ->diff(new DateTimeImmutable($value))
        ->format('%r%a');

    if ($days < 0) {
        return ['label' => 'Vencido', 'class' => 'danger'];
    }

    if ($days <= 30) {
        return ['label' => "Vence en {$days} días", 'class' => 'warning'];
    }

    return ['label' => 'Vigente', 'class' => 'success'];
}

if ($authenticated) {
    $units = $pdo->query(
        'SELECT id, name FROM institutional_units WHERE active = 1 ORDER BY name'
    )->fetchAll();

    $summary = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(DATE(submitted_at) = CURDATE()) AS today_total,
            SUM(status = 'RECIBIDA') AS received_total,
            SUM(status = 'EN_REVISION') AS review_total,
            SUM(status = 'VALIDADA') AS validated_total,
            SUM(status = 'RECHAZADA') AS rejected_total
         FROM requests"
    )->fetch() ?: [];

    $where = [];
    $params = [];
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $unit = (int) ($_GET['unit'] ?? 0);

    if ($q !== '') {
        $where[] = '(r.request_number LIKE :q OR r.position_number LIKE :q OR r.national_id LIKE :q OR r.first_name LIKE :q OR r.last_name LIKE :q OR r.barcode_value LIKE :q OR iu.name LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    if ($status !== '') {
        $where[] = 'r.status = :status';
        $params['status'] = $status;
    }

    if ($unit > 0) {
        $where[] = 'r.institutional_unit_id = :unit';
        $params['unit'] = $unit;
    }

    $baseSql = 'SELECT r.*, iu.name AS institutional_unit_name
                FROM requests r
                LEFT JOIN institutional_units iu ON iu.id = r.institutional_unit_id';

    $sql = $baseSql
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY r.submitted_at DESC LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    if (isset($_GET['view'])) {
        $detail = $pdo->prepare($baseSql . ' WHERE r.id = ? LIMIT 1');
        $detail->execute([(int) $_GET['view']]);
        $selected = $detail->fetch() ?: null;

        if ($selected) {
            $historyStmt = $pdo->prepare(
                'SELECT previous_status, new_status, observation, changed_by, changed_at
                 FROM status_history WHERE request_id = ?
                 ORDER BY changed_at DESC, id DESC'
            );
            $historyStmt->execute([(int) $selected['id']]);
            $history = $historyStmt->fetchAll();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel V2 | <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css?v=20260731">
    <link rel="stylesheet" href="assets/css/admin_v2.css?v=20260731-1">
</head>
<body>
<header class="v2-header">
    <div class="v2-container v2-header-inner">
        <div class="v2-brand">
            <img src="assets/img/logo.png" alt="Logo">
            <div><h1>Panel administrativo V2</h1><p>Validación de carnés institucionales</p></div>
        </div>
        <?php if ($authenticated): ?>
            <nav class="v2-nav">
                <a href="index.php">Portal público</a>
                <a href="admin_export.php">Exportar CSV</a>
                <a href="admin_v2.php?logout=1">Cerrar sesión</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="v2-container v2-main">
<?php if (!$authenticated): ?>
    <section class="v2-card v2-login">
        <h2>Acceso administrativo</h2>
        <?php if ($loginError): ?><div class="notice error"><?= e($loginError) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="login" value="1">
            <label>Usuario<input name="username" required></label>
            <label>Contraseña<input type="password" name="password" required></label>
            <button class="btn btn-primary">Ingresar</button>
        </form>
    </section>
<?php else: ?>
    <section class="v2-metrics">
        <article><span>Total</span><strong><?= (int) ($summary['total'] ?? 0) ?></strong></article>
        <article><span>Hoy</span><strong><?= (int) ($summary['today_total'] ?? 0) ?></strong></article>
        <article><span>Recibidas</span><strong><?= (int) ($summary['received_total'] ?? 0) ?></strong></article>
        <article><span>En revisión</span><strong><?= (int) ($summary['review_total'] ?? 0) ?></strong></article>
        <article class="good"><span>Validadas</span><strong><?= (int) ($summary['validated_total'] ?? 0) ?></strong></article>
        <article class="bad"><span>Rechazadas</span><strong><?= (int) ($summary['rejected_total'] ?? 0) ?></strong></article>
    </section>

    <?php if ($selected): ?>
        <?php $expiration = expirationV2($selected['card_expiration_date']); ?>
        <section class="v2-card" id="expediente">
            <div class="v2-expedient-header">
                <div>
                    <span class="v2-eyebrow">Expediente de validación</span>
                    <h2><?= e(trim($selected['rank_name'] . ' ' . $selected['first_name'] . ' ' . $selected['last_name'])) ?></h2>
                    <p><?= e($selected['request_number']) ?> · <?= e(dateEsV2($selected['submitted_at'], true)) ?></p>
                </div>
                <div class="v2-expedient-actions">
                    <span class="v2-status"><?= e($config['statuses'][$selected['status']] ?? $selected['status']) ?></span>
                    <a class="btn btn-outline" href="admin_v2.php">Cerrar detalle</a>
                </div>
            </div>

            <div class="v2-tabs">
                <button class="active" data-v2-tab="datos">Datos</button>
                <button data-v2-tab="evidencias">Evidencias</button>
                <button data-v2-tab="validacion">Validación</button>
                <button data-v2-tab="historial">Historial</button>
            </div>

            <div class="v2-tab-panel active" id="v2-tab-datos">
                <div class="v2-detail-grid">
                    <div><span>Posición</span><strong><?= e($selected['position_number']) ?></strong></div>
                    <div><span>Cédula</span><strong><?= e($selected['national_id']) ?></strong></div>
                    <div><span>Promoción</span><strong><?= e(trim($selected['promotion_type'] . ' ' . ($selected['promotion_number'] ?? ''))) ?></strong></div>
                    <div><span>Unidad</span><strong><?= e($selected['institutional_unit_name'] ?? 'No indicada') ?></strong></div>
                    <div><span>Ubicación</span><strong><?= e($selected['exact_work_location'] ?: 'No indicada') ?></strong></div>
                    <div><span>Expiración</span><strong><?= e(dateEsV2($selected['card_expiration_date'])) ?></strong><small class="<?= e($expiration['class']) ?>"><?= e($expiration['label']) ?></small></div>
                    <div><span>Correo</span><strong><?= e($selected['email']) ?></strong></div>
                    <div><span>Teléfono</span><strong><?= e($selected['phone']) ?></strong></div>
                    <div class="wide"><span>Código de barras</span><strong><?= e($selected['barcode_value'] ?: 'No indicado') ?></strong></div>
                </div>
            </div>

            <div class="v2-tab-panel" id="v2-tab-evidencias">
                <div class="v2-evidence-grid">
                    <?php foreach (['card_front_path'=>'Frente del carné','card_back_path'=>'Reverso del carné','person_with_card_path'=>'Persona con carné'] as $field => $label): ?>
                        <article>
                            <div class="v2-evidence-title"><strong><?= e($label) ?></strong><?php if (!empty($selected[$field])): ?><a href="<?= e($selected[$field]) ?>" target="_blank">Abrir grande</a><?php endif; ?></div>
                            <?php if (!empty($selected[$field])): ?>
                                <button class="v2-image-button" data-v2-image="<?= e($selected[$field]) ?>" data-v2-label="<?= e($label) ?>"><img src="<?= e($selected[$field]) ?>" alt="<?= e($label) ?>"></button>
                            <?php else: ?><div class="v2-missing">Sin imagen</div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="v2-tab-panel" id="v2-tab-validacion">
                <div class="v2-inspection-grid">
                    <article><h3>Frente del carné</h3><img src="<?= e($selected['card_front_path'] ?? '') ?>" alt="Frente"></article>
                    <article><h3>Persona con el carné</h3><img src="<?= e($selected['person_with_card_path'] ?? '') ?>" alt="Persona"></article>
                    <article><h3>Reverso del carné</h3><img src="<?= e($selected['card_back_path'] ?? '') ?>" alt="Reverso"></article>
                    <article class="data"><h3>Datos registrados</h3><dl>
                        <div><dt>Nombre</dt><dd><?= e(trim($selected['first_name'] . ' ' . $selected['last_name'])) ?></dd></div>
                        <div><dt>Cédula</dt><dd><?= e($selected['national_id']) ?></dd></div>
                        <div><dt>Rango</dt><dd><?= e($selected['rank_name']) ?></dd></div>
                        <div><dt>Expiración</dt><dd><?= e(dateEsV2($selected['card_expiration_date'])) ?></dd></div>
                        <div><dt>Código</dt><dd><?= e($selected['barcode_value'] ?: 'No indicado') ?></dd></div>
                    </dl></article>
                </div>

                <div class="v2-review-grid">
                    <aside class="v2-score-box"><span>Completado</span><strong id="v2Score">0%</strong><div><span id="v2ScoreBar"></span></div><b id="v2Risk" class="high">RIESGO ALTO</b></aside>
                    <form method="post" action="admin_update.php" id="v2ValidationForm">
                        <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
                        <input type="hidden" name="action_type" value="validation">
                        <input type="hidden" name="status" id="v2DecisionStatus">
                        <fieldset><legend>Lista de comprobación</legend>
                            <label><input type="checkbox" name="identity_verified" value="1" data-v2-check> Nombre y cédula coinciden</label>
                            <label><input type="checkbox" name="photos_verified" value="1" data-v2-check> Fotografías claras y correctas</label>
                            <label><input type="checkbox" name="expiration_verified" value="1" data-v2-check> Expiración verificada</label>
                            <label><input type="checkbox" name="barcode_verified" value="1" data-v2-check> Código visible coincide</label>
                            <label><input type="checkbox" name="card_integrity_verified" value="1" data-v2-check> Documento sin alteraciones</label>
                            <label><input type="checkbox" name="person_verified" value="1" data-v2-check> Persona coincide con la foto</label>
                        </fieldset>
                        <label>Observación<textarea name="admin_observation"><?= e($selected['admin_observation'] ?? '') ?></textarea></label>
                        <div class="v2-decisions">
                            <button type="button" data-v2-decision="VALIDADA" class="validate">Validar</button>
                            <button type="button" data-v2-decision="OBSERVADA" class="observe">Observar</button>
                            <button type="button" data-v2-decision="RECHAZADA" class="reject">Rechazar</button>
                        </div>
                        <button class="btn btn-primary v2-save">Guardar revisión</button>
                    </form>
                </div>
            </div>

            <div class="v2-tab-panel" id="v2-tab-historial">
                <div class="v2-timeline">
                    <?php foreach ($history as $item): ?><article><time><?= e(dateEsV2($item['changed_at'], true)) ?></time><strong><?= e($config['statuses'][$item['new_status']] ?? $item['new_status']) ?></strong><p><?= e($item['observation'] ?: 'Sin observación') ?></p><small>Por: <?= e($item['changed_by']) ?></small></article><?php endforeach; ?>
                    <?php if (!$history): ?><p>No hay movimientos registrados.</p><?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="v2-card">
        <div class="v2-section-heading"><div><h2>Solicitudes</h2><p>Resultados: <?= count($requests) ?></p></div></div>
        <form method="get" class="v2-filters">
            <label>Buscar<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Solicitud, cédula, nombre, código..."></label>
            <label>Estado<select name="status"><option value="">Todos</option><?php foreach ($config['statuses'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= ($_GET['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Unidad<select name="unit"><option value="">Todas</option><?php foreach ($units as $unitRow): ?><option value="<?= (int) $unitRow['id'] ?>" <?= (int) ($_GET['unit'] ?? 0) === (int) $unitRow['id'] ? 'selected' : '' ?>><?= e($unitRow['name']) ?></option><?php endforeach; ?></select></label>
            <div class="v2-filter-actions"><button class="btn btn-primary">Filtrar</button><a class="btn btn-outline" href="admin_v2.php">Limpiar</a></div>
        </form>
        <div class="v2-table-wrap"><table><thead><tr><th>Solicitud</th><th>Funcionario</th><th>Unidad</th><th>Expiración</th><th>Estado</th><th></th></tr></thead><tbody>
            <?php foreach ($requests as $row): ?><?php $rowExpiration = expirationV2($row['card_expiration_date']); ?><tr>
                <td><strong><?= e($row['request_number']) ?></strong><small><?= e(dateEsV2($row['submitted_at'], true)) ?></small></td>
                <td><?= e(trim($row['rank_name'] . ' ' . $row['first_name'] . ' ' . $row['last_name'])) ?><small><?= e($row['national_id']) ?></small></td>
                <td><?= e($row['institutional_unit_name'] ?? 'No indicada') ?><small><?= e($row['exact_work_location'] ?: '') ?></small></td>
                <td><?= e(dateEsV2($row['card_expiration_date'])) ?><small class="<?= e($rowExpiration['class']) ?>"><?= e($rowExpiration['label']) ?></small></td>
                <td><span class="v2-status"><?= e($config['statuses'][$row['status']] ?? $row['status']) ?></span></td>
                <td><a class="btn btn-outline" href="admin_v2.php?view=<?= (int) $row['id'] ?>#expediente">Revisar</a></td>
            </tr><?php endforeach; ?>
            <?php if (!$requests): ?><tr><td colspan="6">No hay resultados.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
<?php endif; ?>
</main>

<div class="v2-modal" id="v2Modal"><button id="v2ModalClose">×</button><div><h3 id="v2ModalLabel"></h3><img id="v2ModalImage" alt=""></div></div>
<script src="assets/js/admin_v2.js?v=20260731-1"></script>
</body>
</html>
