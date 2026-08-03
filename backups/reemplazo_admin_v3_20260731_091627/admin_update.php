<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/validation_engine.php';

if (empty($_SESSION['admin_authenticated']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$newStatus = trim((string) ($_POST['status'] ?? ''));
$observation = trim((string) ($_POST['admin_observation'] ?? ''));
$actionType = trim((string) ($_POST['action_type'] ?? 'status'));

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

$barcodeVerified = isset($_POST['barcode_verified']) ? 1 : 0;
$identityVerified = isset($_POST['identity_verified']) ? 1 : 0;
$photosVerified = isset($_POST['photos_verified']) ? 1 : 0;
$expirationVerified = isset($_POST['expiration_verified']) ? 1 : 0;
$detectedValue = trim((string) ($_POST['barcode_detected_value'] ?? '')) ?: null;
$presets = $_POST['validation_presets'] ?? [];
if (!is_array($presets)) $presets = [];
$allowedPresets = [
    'FOTO_BORROSA','CARNE_VENCIDO','CODIGO_ILEGIBLE','DATOS_INCOMPLETOS',
    'DOCUMENTO_DETERIORADO','NUEVA_FOTOGRAFIA','CODIGO_NO_COINCIDE','POSIBLE_DUPLICADO'
];
$presets = array_values(array_intersect($allowedPresets, $presets));
$score = (int) round((($barcodeVerified + $identityVerified + $photosVerified + $expirationVerified) / 4) * 100);

$pdo->beginTransaction();

try {
    if ($actionType === 'validation') {
        $update = $pdo->prepare(
            'UPDATE requests
             SET barcode_verified = ?, identity_verified = ?, photos_verified = ?,
                 expiration_verified = ?, barcode_detected_value = ?, validation_score = ?,
                 validation_notes_json = ?, admin_observation = ?, reviewed_at = ?, reviewed_by = ?, last_admin_ip = ?, last_admin_user_agent = ?
             WHERE id = ?'
        );
        $update->execute([
            $barcodeVerified,
            $identityVerified,
            $photosVerified,
            $expirationVerified,
            $detectedValue,
            $score,
            json_encode($presets, JSON_UNESCAPED_UNICODE),
            $observation,
            date('Y-m-d H:i:s'),
            $_SESSION['admin_user'] ?? 'ADMIN',
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500),
            $id,
        ]);

        $historyObservation = 'Validación actualizada. Puntaje: ' . $score . '%.'
            . ($observation !== '' ? ' ' . $observation : '');
    } else {
        $update = $pdo->prepare(
            'UPDATE requests
             SET status = ?, validation_notes_json = ?, admin_observation = ?, reviewed_at = ?, reviewed_by = ?, last_admin_ip = ?, last_admin_user_agent = ?
             WHERE id = ?'
        );
        $update->execute([
            $newStatus,
            $observation,
            date('Y-m-d H:i:s'),
            $_SESSION['admin_user'] ?? 'ADMIN',
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500),
            $id,
        ]);

        $historyObservation = $observation;
    }

    $history = $pdo->prepare(
        'INSERT INTO status_history
         (request_id, previous_status, new_status, observation, changed_by, changed_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $history->execute([
        $id,
        $current['status'],
        $actionType === 'validation' ? $current['status'] : $newStatus,
        $historyObservation,
        $_SESSION['admin_user'] ?? 'ADMIN',
        date('Y-m-d H:i:s'),
    ]);

    updateRiskAndDuplicates($pdo, $id);
    writeAdminAudit($pdo, $id, $_SESSION['admin_user'] ?? 'ADMIN', $actionType === 'validation' ? 'VALIDATION_UPDATED' : 'STATUS_CHANGED', $observation);
    $pdo->commit();

    if ($actionType !== 'validation' && $newStatus !== $current['status']) {
        $label = $config['statuses'][$newStatus] ?? $newStatus;
        $subject = 'Actualización de solicitud ' . $current['request_number'];
        $body = "Estimado(a) {$current['first_name']}:\n\n"
            . "El estado de su solicitud {$current['request_number']} fue actualizado a: {$label}.\n\n"
            . ($observation !== '' ? "Observación: {$observation}\n\n" : '')
            . "Dirección Nacional de Recursos Humanos";

        $headers = 'From: ' . $config['from_email']
            . "\r\nContent-Type: text/plain; charset=UTF-8";

        @mail($current['email'], $subject, $body, $headers);
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($exception->__toString());
}

header('Location: admin.php?view=' . $id . '#expediente');
exit;
