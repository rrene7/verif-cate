<?php

declare(strict_types=1);

v3AuthRequired();

$where = [];
$params = [];

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$unit = (int) ($_GET['unit'] ?? 0);
$expirationFilter = trim((string) ($_GET['expiration'] ?? ''));

if ($q !== '') {
    $where[] = '(
        r.request_number LIKE :q
        OR r.position_number LIKE :q
        OR r.national_id LIKE :q
        OR r.first_name LIKE :q
        OR r.last_name LIKE :q
        OR r.barcode_value LIKE :q
        OR r.email LIKE :q
        OR r.phone LIKE :q
        OR iu.name LIKE :q
    )';
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

if ($expirationFilter === 'expired') {
    $where[] = 'r.card_expiration_date < CURDATE()';
} elseif ($expirationFilter === '30') {
    $where[] = 'r.card_expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
} elseif ($expirationFilter === 'valid') {
    $where[] = 'r.card_expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
}

$sql = v3RequestSelect()
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY r.submitted_at DESC LIMIT 500';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$units = $pdo->query(
    'SELECT id, name
     FROM institutional_units
     WHERE active = 1
     ORDER BY name'
)->fetchAll();

$pageTitle = 'Solicitudes';
require __DIR__ . '/partials/header.php';
?>

<section class="v3-page-heading">
    <div>
        <span class="v3-eyebrow">Gestión operativa</span>
        <h2>Solicitudes</h2>
        <p>Busque, filtre y abra los expedientes que requieren atención.</p>
    </div>
    <a class="v3-button secondary" href="admin_export.php?<?= e(http_build_query($_GET)) ?>">Exportar CSV</a>
</section>

<section class="v3-card">
    <form method="get" class="v3-filters">
        <input type="hidden" name="page" value="solicitudes">

        <label>
            Buscar
            <input name="q" value="<?= e($q) ?>" placeholder="Solicitud, nombre, cédula, código...">
        </label>

        <label>
            Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach ($config['statuses'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Unidad
            <select name="unit">
                <option value="">Todas</option>
                <?php foreach ($units as $row): ?>
                    <option value="<?= (int) $row['id'] ?>" <?= $unit === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Expiración
            <select name="expiration">
                <option value="">Todas</option>
                <option value="expired" <?= $expirationFilter === 'expired' ? 'selected' : '' ?>>Vencidos</option>
                <option value="30" <?= $expirationFilter === '30' ? 'selected' : '' ?>>Vencen en 30 días</option>
                <option value="valid" <?= $expirationFilter === 'valid' ? 'selected' : '' ?>>Vigentes</option>
            </select>
        </label>

        <div class="v3-filter-actions">
            <button class="v3-button primary" type="submit">Filtrar</button>
            <a class="v3-button secondary" href="admin.php?page=solicitudes">Limpiar</a>
        </div>
    </form>

    <div class="v3-results"><?= count($rows) ?> resultados</div>

    <div class="v3-table-wrap">
        <table class="v3-table">
            <thead>
                <tr>
                    <th>Solicitud</th>
                    <th>Funcionario</th>
                    <th>Unidad</th>
                    <th>Carné</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $expiration = v3Expiration($row['card_expiration_date']); ?>
                <tr>
                    <td><strong><?= e($row['request_number']) ?></strong><small><?= e(v3Date($row['submitted_at'], true)) ?></small></td>
                    <td><?= e(v3FullName($row)) ?><small>Pos. <?= e($row['position_number']) ?> · <?= e($row['national_id']) ?></small></td>
                    <td><?= e($row['institutional_unit_name'] ?? 'No indicada') ?><small><?= e($row['exact_work_location'] ?? '') ?></small></td>
                    <td><?= e(str_replace('_', ' ', $row['card_condition'])) ?><small class="<?= e($expiration['class']) ?>"><?= e(v3Date($row['card_expiration_date'])) ?> · <?= e($expiration['label']) ?></small></td>
                    <td><span class="v3-status"><?= e($config['statuses'][$row['status']] ?? $row['status']) ?></span></td>
                    <td><a class="v3-button secondary" href="admin.php?page=expediente&id=<?= (int) $row['id'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$rows): ?>
                <tr><td colspan="6">No hay solicitudes que coincidan con los filtros.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
