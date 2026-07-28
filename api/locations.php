<?php

declare(strict_types=1);

require dirname(__DIR__) . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$stmt = $pdo->query(<<<SQL
SELECT id, code, name, category
FROM institutional_units
WHERE active = 1
ORDER BY FIELD(category, 'DIRECCION', 'ZONA', 'SERVICIO'), name
SQL);

echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
