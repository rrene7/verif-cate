<?php

declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? 'directorates';
$parentId = filter_input(INPUT_GET, 'parent_id', FILTER_VALIDATE_INT);

$typeMap = [
    'directorates' => ['direccion_nacional'],
    'zones' => ['zona_policial'],
    'areas' => ['area'],
    'services' => ['departamento', 'division', 'seccion', 'oficina', 'dependencia', 'cuartel', 'estacion', 'subestacion', 'puesto'],
];

if (!isset($typeMap[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de catálogo inválido.']);
    exit;
}

$requiresParent = $type !== 'directorates';
if ($requiresParent && !$parentId) {
    echo json_encode([]);
    exit;
}

$names = $typeMap[$type];
$placeholders = implode(',', array_fill(0, count($names), '?'));
$sql = <<<SQL
SELECT ou.id, COALESCE(NULLIF(ou.short_name, ''), ou.name) AS name
FROM organizational_units ou
JOIN unit_types ut ON ut.id = ou.unit_type_id
WHERE ou.status = 'active'
  AND ut.name IN ($placeholders)
SQL;
$params = $names;

if ($requiresParent) {
    $sql .= ' AND ou.parent_id = ?';
    $params[] = $parentId;
} else {
    $sql .= ' AND (ou.parent_id IS NULL OR ou.level IN (0, 1, 2))';
}

$sql .= ' ORDER BY ou.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
