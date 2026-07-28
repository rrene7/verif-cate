<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

function redirectError(string $message): never
{
    header('Location: index.php?error=' . rawurlencode($message) . '#formulario');
    exit;
}

try {
    $required = [
        'position_number', 'rank_name', 'national_id', 'first_name', 'last_name',
        'promotion_type', 'email', 'phone', 'national_directorate', 'service_name',
        'card_condition', 'declaration'
    ];

    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
            throw new RuntimeException('Complete todos los campos obligatorios.');
        }
    }

    $position = trim((string) $_POST['position_number']);
    $nationalId = strtoupper(trim((string) $_POST['national_id']));
    $email = strtolower(trim((string) $_POST['email']));
    $condition = trim((string) $_POST['card_condition']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo electrónico no tiene un formato válido.');
    }

    $duplicate = $pdo->prepare('SELECT request_number FROM requests WHERE position_number = :position OR national_id = :national_id LIMIT 1');
    $duplicate->execute(['position' => $position, 'national_id' => $nationalId]);
    if ($duplicate->fetch()) {
        throw new RuntimeException('Ya existe una solicitud registrada para la posición o cédula suministrada. Utilice la opción Consultar estado.');
    }

    $requestNumber = generateRequestNumber($pdo);
    $noCardConditions = ['EXTRAVIADO_REPORTADO', 'EXTRAVIADO_NO_REPORTADO', 'ROBADO', 'NO_RECIBIDO'];
    $requiresEvidence = !in_array($condition, $noCardConditions, true);

    if ($requiresEvidence) {
        foreach (['card_front', 'card_back', 'person_with_card'] as $fileField) {
            if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Debe adjuntar las tres fotografías requeridas.');
            }
        }
    }

    $frontPath = saveUploadedImage('card_front', $requestNumber, $config, $uploadDir);
    $backPath = saveUploadedImage('card_back', $requestNumber, $config, $uploadDir);
    $personPath = saveUploadedImage('person_with_card', $requestNumber, $config, $uploadDir);

    $stmt = $pdo->prepare(<<<SQL
INSERT INTO requests (
    request_number, position_number, rank_name, first_name, middle_name, last_name,
    second_last_name, national_id, promotion_type, promotion_number, email, phone,
    national_directorate, zone_name, area_name, service_name, card_condition,
    barcode_value, barcode_readable, card_front_path, card_back_path,
    person_with_card_path, loss_report_number, notes, status, submitted_at,
    ip_address, user_agent
) VALUES (
    :request_number, :position_number, :rank_name, :first_name, :middle_name, :last_name,
    :second_last_name, :national_id, :promotion_type, :promotion_number, :email, :phone,
    :national_directorate, :zone_name, :area_name, :service_name, :card_condition,
    :barcode_value, :barcode_readable, :card_front_path, :card_back_path,
    :person_with_card_path, :loss_report_number, :notes, 'RECIBIDA', :submitted_at,
    :ip_address, :user_agent
)
SQL);

    $stmt->execute([
        'request_number' => $requestNumber,
        'position_number' => $position,
        'rank_name' => trim((string) $_POST['rank_name']),
        'first_name' => trim((string) $_POST['first_name']),
        'middle_name' => trim((string) ($_POST['middle_name'] ?? '')),
        'last_name' => trim((string) $_POST['last_name']),
        'second_last_name' => trim((string) ($_POST['second_last_name'] ?? '')),
        'national_id' => $nationalId,
        'promotion_type' => trim((string) $_POST['promotion_type']),
        'promotion_number' => trim((string) ($_POST['promotion_number'] ?? '')),
        'email' => $email,
        'phone' => trim((string) $_POST['phone']),
        'national_directorate' => trim((string) $_POST['national_directorate']),
        'zone_name' => trim((string) ($_POST['zone_name'] ?? '')),
        'area_name' => trim((string) ($_POST['area_name'] ?? '')),
        'service_name' => trim((string) $_POST['service_name']),
        'card_condition' => $condition,
        'barcode_value' => trim((string) ($_POST['barcode_value'] ?? '')),
        'barcode_readable' => (int) ($_POST['barcode_readable'] ?? 1),
        'card_front_path' => $frontPath,
        'card_back_path' => $backPath,
        'person_with_card_path' => $personPath,
        'loss_report_number' => trim((string) ($_POST['loss_report_number'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'submitted_at' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
    ]);

    $requestId = (int) $pdo->lastInsertId();
    $history = $pdo->prepare('INSERT INTO status_history (request_id, previous_status, new_status, observation, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)');
    $history->execute([$requestId, null, 'RECIBIDA', 'Solicitud registrada por el funcionario.', 'SISTEMA', date('Y-m-d H:i:s')]);

    $subject = 'Solicitud de validación de carné recibida';
    $body = "Estimado(a) funcionario(a):\n\nSu solicitud fue recibida correctamente.\n\nNúmero de solicitud: {$requestNumber}\n\nPara consultar el estado, ingrese al portal y utilice el número de solicitud junto con los últimos cuatro dígitos de su cédula.\n\nLa recepción no significa que el carné ya haya sido validado.\n\nDirección Nacional de Recursos Humanos";
    $headers = 'From: ' . $config['from_email'] . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
    @mail($email, $subject, $body, $headers);

    header('Location: index.php?success=1&request=' . rawurlencode($requestNumber));
    exit;
} catch (Throwable $exception) {
    redirectError($exception->getMessage());
}
