<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['admin_authenticated']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$newStatus = trim((string) ($_POST['status'] ?? ''));
$observation = trim((string) ($_POST['admin_observation'] ?? ''));

if ($id < 1 || !array_key_exists($newStatus, $config['statuses'])) {
    header('Location: admin.php');
    exit;
}

$stmt = $pdo->prepare('SELECT status, email, request_number, first_name FROM requests WHERE id = ?');
$stmt->execute([$id]);
$current = $stmt->fetch();

if (!$current) {
    header('Location: admin.php');
    exit;
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE requests SET status = ?, admin_observation = ?, reviewed_at = ?, reviewed_by = ? WHERE id = ?');
    $update->execute([$newStatus, $observation, date('Y-m-d H:i:s'), $_SESSION['admin_user'] ?? 'ADMIN', $id]);

    $history = $pdo->prepare('INSERT INTO status_history (request_id, previous_status, new_status, observation, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)');
    $history->execute([$id, $current['status'], $newStatus, $observation, $_SESSION['admin_user'] ?? 'ADMIN', date('Y-m-d H:i:s')]);
    $pdo->commit();

    if ($newStatus !== $current['status']) {
        $label = $config['statuses'][$newStatus] ?? $newStatus;
        $subject = 'Actualización de solicitud ' . $current['request_number'];
        $body = "Estimado(a) {$current['first_name']}:\n\nEl estado de su solicitud {$current['request_number']} fue actualizado a: {$label}.\n\nIngrese al portal de consulta y utilice el número de solicitud junto con los últimos cuatro dígitos de su cédula.\n\nDirección Nacional de Recursos Humanos";
        $headers = 'From: ' . $config['from_email'] . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
        @mail($current['email'], $subject, $body, $headers);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

header('Location: admin.php?view=' . $id);
exit;
