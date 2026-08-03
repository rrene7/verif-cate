<?php

declare(strict_types=1);

v3AuthRequired();

$summary = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(DATE(submitted_at) = CURDATE()) AS today_total,
        SUM(status = 'RECIBIDA') AS received_total,
        SUM(status = 'EN_REVISION') AS review_total,
        SUM(status = 'VALIDADA') AS validated_total,
        SUM(status = 'RECHAZADA') AS rejected_total,
        SUM(card_expiration_date < CURDATE()) AS expired_total,
        SUM(card_expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS expiring_total
     FROM requests"
)->fetch() ?: [];

$statusRows = $pdo->query(
    'SELECT status, COUNT(*) AS total
     FROM requests
     GROUP BY status
     ORDER BY total DESC'
)->fetchAll();

$unitRows = $pdo->query(
    "SELECT
        COALESCE(iu.name, 'Sin unidad') AS unit_name,
        COUNT(*) AS total
     FROM requests r
     LEFT JOIN institutional_units iu ON iu.id = r.institutional_unit_id
     GROUP BY iu.id, iu.name
     ORDER BY total DESC
     LIMIT 10"
)->fetchAll();

$recent = $pdo->query(
    v3RequestSelect() . '
     ORDER BY r.submitted_at DESC
     LIMIT 8'
)->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/partials/header.php';
?>

<section class="v3-page-heading">
    <div>
        <span class="v3-eyebrow">Resumen ejecutivo</span>
        <h2>Dashboard institucional</h2>
        <p>Estado general de las solicitudes y prioridades de revisión.</p>
    </div>
</section>

<section class="v3-metrics">
    <article><span>Total</span><strong><?= (int) ($summary['total'] ?? 0) ?></strong></article>
    <article><span>Hoy</span><strong><?= (int) ($summary['today_total'] ?? 0) ?></strong></article>
    <article><span>En revisión</span><strong><?= (int) ($summary['review_total'] ?? 0) ?></strong></article>
    <article class="metric-success"><span>Validadas</span><strong><?= (int) ($summary['validated_total'] ?? 0) ?></strong></article>
    <article class="metric-warning"><span>Vencen en 30 días</span><strong><?= (int) ($summary['expiring_total'] ?? 0) ?></strong></article>
    <article class="metric-danger"><span>Vencidos</span><strong><?= (int) ($summary['expired_total'] ?? 0) ?></strong></article>
</section>

<section class="v3-dashboard-grid">
    <article class="v3-card">
        <div class="v3-card-heading">
            <div>
                <h3>Distribución por estado</h3>
                <p>Cantidad de solicitudes en cada etapa.</p>
            </div>
        </div>

        <?php $maxStatus = max(1, ...array_map(static fn(array $r): int => (int) $r['total'], $statusRows ?: [['total' => 1]])); ?>

        <div class="v3-bars">
            <?php foreach ($statusRows as $row): ?>
                <?php $width = max(3, ((int) $row['total'] / $maxStatus) * 100); ?>
                <a href="admin.php?page=solicitudes&status=<?= e($row['status']) ?>">
                    <div><span><?= e($config['statuses'][$row['status']] ?? $row['status']) ?></span><strong><?= (int) $row['total'] ?></strong></div>
                    <i><b style="width:<?= $width ?>%"></b></i>
                </a>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="v3-card">
        <div class="v3-card-heading">
            <div>
                <h3>Unidades con más solicitudes</h3>
                <p>Las diez unidades con mayor volumen.</p>
            </div>
        </div>

        <div class="v3-ranking">
            <?php foreach ($unitRows as $index => $row): ?>
                <div>
                    <span><?= $index + 1 ?></span>
                    <p><?= e($row['unit_name']) ?></p>
                    <strong><?= (int) $row['total'] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="v3-card">
    <div class="v3-card-heading">
        <div>
            <h3>Solicitudes recientes</h3>
            <p>Últimos registros recibidos.</p>
        </div>
        <a class="v3-button secondary" href="admin.php?page=solicitudes">Ver todas</a>
    </div>

    <div class="v3-table-wrap">
        <table class="v3-table">
            <thead>
                <tr>
                    <th>Solicitud</th>
                    <th>Funcionario</th>
                    <th>Unidad</th>
                    <th>Expiración</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <?php $expiration = v3Expiration($row['card_expiration_date']); ?>
                <tr>
                    <td><strong><?= e($row['request_number']) ?></strong><small><?= e(v3Date($row['submitted_at'], true)) ?></small></td>
                    <td><?= e(v3FullName($row)) ?><small><?= e($row['national_id']) ?></small></td>
                    <td><?= e($row['institutional_unit_name'] ?? 'No indicada') ?></td>
                    <td><?= e(v3Date($row['card_expiration_date'])) ?><small class="<?= e($expiration['class']) ?>"><?= e($expiration['label']) ?></small></td>
                    <td><span class="v3-status"><?= e($config['statuses'][$row['status']] ?? $row['status']) ?></span></td>
                    <td><a class="v3-button secondary" href="admin.php?page=expediente&id=<?= (int) $row['id'] ?>">Revisar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
