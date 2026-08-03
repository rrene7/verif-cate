<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['admin_authenticated'])) {
    header('Location: admin.php');
    exit;
}

/**
 * Fuerza a Excel a conservar el valor como texto.
 * Evita notación científica, pérdida de ceros y conversión automática.
 */
function excelText(mixed $value): string
{
    $text = trim((string) ($value ?? ''));

    if ($text === '') {
        return '';
    }

    // Neutraliza caracteres peligrosos y escapa comillas para fórmula de Excel.
    $text = str_replace('"', '""', $text);

    return '="' . $text . '"';
}

function excelDate(?string $date): string
{
    if (!$date) {
        return '';
    }

    try {
        return excelText((new DateTimeImmutable($date))->format('d/m/Y'));
    } catch (Throwable) {
        return excelText($date);
    }
}

function excelDateTime(?string $date): string
{
    if (!$date) {
        return '';
    }

    try {
        return excelText((new DateTimeImmutable($date))->format('d/m/Y h:i A'));
    } catch (Throwable) {
        return excelText($date);
    }
}

$where = [];
$params = [];

$status = trim((string) ($_GET['status'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$unit = (int) ($_GET['unit'] ?? 0);
$category = trim((string) ($_GET['category'] ?? ''));
$expiration = trim((string) ($_GET['expiration'] ?? ''));
$evidence = trim((string) ($_GET['evidence'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($status !== '') {
    $where[] = 'r.status = :status';
    $params['status'] = $status;
}

if ($q !== '') {
    $where[] = '(
        r.request_number LIKE :q
        OR r.position_number LIKE :q
        OR r.national_id LIKE :q
        OR r.first_name LIKE :q
        OR r.middle_name LIKE :q
        OR r.last_name LIKE :q
        OR r.second_last_name LIKE :q
        OR r.barcode_value LIKE :q
        OR iu.name LIKE :q
        OR r.exact_work_location LIKE :q
        OR r.phone LIKE :q
        OR r.email LIKE :q
    )';
    $params['q'] = '%' . $q . '%';
}

if ($unit > 0) {
    $where[] = 'r.institutional_unit_id = :unit';
    $params['unit'] = $unit;
}

if (in_array($category, ['DIRECCION', 'ZONA', 'SERVICIO'], true)) {
    $where[] = 'iu.category = :category';
    $params['category'] = $category;
}

if ($expiration === 'expired') {
    $where[] = 'r.card_expiration_date < CURDATE()';
} elseif ($expiration === '15') {
    $where[] = 'r.card_expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)';
} elseif ($expiration === '30') {
    $where[] = 'r.card_expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
} elseif ($expiration === 'valid') {
    $where[] = 'r.card_expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
}

if ($evidence === 'complete') {
    $where[] = "r.card_front_path IS NOT NULL AND r.card_front_path <> ''
                AND r.card_back_path IS NOT NULL AND r.card_back_path <> ''
                AND r.person_with_card_path IS NOT NULL AND r.person_with_card_path <> ''";
} elseif ($evidence === 'missing') {
    $where[] = "(r.card_front_path IS NULL OR r.card_front_path = ''
                 OR r.card_back_path IS NULL OR r.card_back_path = ''
                 OR r.person_with_card_path IS NULL OR r.person_with_card_path = '')";
}

if ($dateFrom !== '') {
    $where[] = 'DATE(r.submitted_at) >= :date_from';
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(r.submitted_at) <= :date_to';
    $params['date_to'] = $dateTo;
}

$sql = '
    SELECT
        r.*,
        iu.code AS unit_code,
        iu.name AS unit_name,
        iu.category AS unit_category
    FROM requests r
    LEFT JOIN institutional_units iu
        ON iu.id = r.institutional_unit_id
'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY r.submitted_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = 'solicitudes_verif_cate_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'wb');

if ($output === false) {
    http_response_code(500);
    exit('No fue posible generar la exportación.');
}

// BOM para que Excel reconozca correctamente tildes y ñ.
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Solicitud',
    'Posición',
    'Rango',
    'Nombre completo',
    'Cédula',
    'Promoción',
    'Código de unidad',
    'Unidad',
    'Categoría',
    'Ubicación exacta',
    'Correo',
    'Teléfono',
    'Condición del carné',
    'Código de barras',
    'Expiración',
    'Estado',
    'Puntaje de validación',
    'Fecha de solicitud',
], ';');

while ($row = $stmt->fetch()) {
    $fullName = trim(
        $row['first_name']
        . ' ' . ($row['middle_name'] ?? '')
        . ' ' . $row['last_name']
        . ' ' . ($row['second_last_name'] ?? '')
    );

    fputcsv($output, [
        excelText($row['request_number']),
        excelText($row['position_number']),
        $row['rank_name'],
        $fullName,
        excelText($row['national_id']),
        trim($row['promotion_type'] . ' ' . ($row['promotion_number'] ?? '')),
        excelText($row['unit_code']),
        $row['unit_name'],
        $row['unit_category'],
        $row['exact_work_location'],
        $row['email'],
        excelText($row['phone']),
        str_replace('_', ' ', (string) $row['card_condition']),
        excelText($row['barcode_value']),
        excelDate($row['card_expiration_date']),
        $config['statuses'][$row['status']] ?? $row['status'],
        (int) ($row['validation_score'] ?? 0) . '%',
        excelDateTime($row['submitted_at']),
    ], ';');
}

fclose($output);
exit;
