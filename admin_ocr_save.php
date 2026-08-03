<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['admin_authenticated']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$frontText = trim((string) ($_POST['ocr_front_text'] ?? ''));
$backText = trim((string) ($_POST['ocr_back_text'] ?? ''));
$matchScore = max(0, min(100, (int) ($_POST['ocr_match_score'] ?? 0)));

if ($id < 1) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Solicitud inválida.']);
    exit;
}

$stmt = $pdo->prepare(
    'UPDATE requests
     SET ocr_front_text = ?,
         ocr_back_text = ?,
         ocr_match_score = ?,
         ocr_last_run_at = ?,
         ocr_reviewed_by = ?
     WHERE id = ?'
);

$stmt->execute([
    $frontText !== '' ? $frontText : null,
    $backText !== '' ? $backText : null,
    $matchScore,
    date('Y-m-d H:i:s'),
    $_SESSION['admin_user'] ?? 'ADMIN',
    $id,
]);

echo json_encode([
    'ok' => true,
    'message' => 'Resultado OCR guardado.',
    'score' => $matchScore,
], JSON_UNESCAPED_UNICODE);
