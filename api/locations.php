<?php

declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? 'directorates';
$parentId = filter_input(INPUT_GET, 'parent_id', FILTER_VALIDATE_INT);

$queries = [
    'directorates' => ['SELECT id, name FROM national_directorates WHERE active = 1 ORDER BY name', false],
    'zones' => ['SELECT id, name FROM zones WHERE active = 1 AND national_directorate_id = ? ORDER BY name', true],
    'areas' => ['SELECT id, name FROM areas WHERE active = 1 AND zone_id = ? ORDER BY name', true],
    'services' => ['SELECT id, name FROM services WHERE active = 1 AND area_id = ? ORDER BY name', true],
];

if (!isset($queries[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de catálogo inválido.']);
    exit;
}

[$sql, $requiresParent] = $queries[$type];
if ($requiresParent && !$parentId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($requiresParent ? [$parentId] : []);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
