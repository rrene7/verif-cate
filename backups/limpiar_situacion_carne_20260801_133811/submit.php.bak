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
        'promotion_type', 'email', 'phone', 'institutional_unit_id', 'exact_work_location',
        'card_expiration_date', 'card_condition', 'declaration'
    ];

    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
            throw new RuntimeException('Complete todos los campos obligatorios.');
        }
    }


    $allowedRanks = [
        'DIRECTOR',
        'COMISIONADO',
        'SUBCOMISIONADO',
        'MAYOR',
        'CAPITÁN',
        'TENIENTE',
        'SUBTENIENTE',
        'SARGENTO 1°',
        'SARGENTO 2°',
        'CABO 1°',
        'CABO 2°',
        'AGENTE',
        'MNJ',
    ];

    $rankName = strtoupper(trim((string) $_POST['rank_name']));

    if (!in_array($rankName, $allowedRanks, true)) {
        throw new RuntimeException('Seleccione un rango válido.');
    }

        $promotionExemptRanks = ['DIRECTOR', 'MNJ'];
    $promotionDoesNotApply = in_array($rankName, $promotionExemptRanks, true);

    if ($promotionDoesNotApply) {
        $promotionType = null;
        $promotionNumber = null;
    } else {
        $promotionType = trim((string) ($_POST['promotion_type'] ?? ''));
        $promotionNumber = trim((string) ($_POST['promotion_number'] ?? ''));

        if ($promotionType === '' || $promotionNumber === '') {
            throw new RuntimeException(
                'Debe indicar el tipo y número de promoción para el rango seleccionado.'
            );
        }
    }
$position = trim((string) $_POST['position_number']);
    $nationalId = strtoupper(trim((string) $_POST['national_id']));
    $email = strtolower(trim((string) $_POST['email']));
    $condition = trim((string) $_POST['card_condition']);
    $unitId = filter_var($_POST['institutional_unit_id'], FILTER_VALIDATE_INT);
    $exactWorkLocation = trim((string) $_POST['exact_work_location']);
    $cardExpirationDate = trim((string) $_POST['card_expiration_date']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo electrónico no tiene un formato válido.');
    }
    if (!$unitId) {
        throw new RuntimeException('Seleccione una unidad institucional válida.');
    }
    if (mb_strlen($exactWorkLocation) > 255) {
        throw new RuntimeException('La ubicación exacta de trabajo no puede superar 255 caracteres.');
    }

    $expirationDate = DateTimeImmutable::createFromFormat('!Y-m-d', $cardExpirationDate);
    $expirationErrors = DateTimeImmutable::getLastErrors();
    if (!$expirationDate || ($expirationErrors !== false && ($expirationErrors['warning_count'] > 0 || $expirationErrors['error_count'] > 0))) {
        throw new RuntimeException('La fecha de expiración del carné no es válida.');
    }

    $unitCheck = $pdo->prepare('SELECT id FROM institutional_units WHERE id = ? AND active = 1 LIMIT 1');
    $unitCheck->execute([$unitId]);
    if (!$unitCheck->fetchColumn()) {
        throw new RuntimeException('La unidad institucional seleccionada no es válida.');
    }

    $duplicate = $pdo->prepare('SELECT request_number FROM requests WHERE position_number = :position OR national_id = :national_id LIMIT 1');
    $duplicate->execute(['position' => $position, 'national_id' => $nationalId]);
    if ($duplicate->fetch()) {
        throw new RuntimeException('Ya existe una solicitud registrada para la posición o cédula suministrada. Utilice Consultar estado.');
    }

    $barcode = trim((string) ($_POST['barcode_value'] ?? ''));
    if ($barcode !== '') {
        $barcodeCheck = $pdo->prepare('SELECT request_number FROM requests WHERE barcode_value = ? LIMIT 1');
        $barcodeCheck->execute([$barcode]);
        if ($barcodeCheck->fetch()) {
            throw new RuntimeException('El código de barras ya está asociado con otra solicitud.');
        }
    }

    $requestNumber = generateRequestNumber($pdo);
    $requiresEvidence = !in_array($condition, ['EXTRAVIADO_REPORTADO', 'EXTRAVIADO_NO_REPORTADO', 'ROBADO', 'NO_RECIBIDO'], true);
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

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(<<<SQL
INSERT INTO requests (
 request_number, position_number, rank_name, first_name, middle_name, last_name, second_last_name,
 national_id, promotion_type, promotion_number, email, phone, institutional_unit_id, exact_work_location,
 card_expiration_date, card_condition, barcode_value, barcode_readable, card_front_path, card_back_path,
 person_with_card_path, loss_report_number, notes, status, submitted_at, ip_address, user_agent
) VALUES (
 :request_number, :position_number, :rank_name, :first_name, :middle_name, :last_name, :second_last_name,
 :national_id, :promotion_type, :promotion_number, :email, :phone, :institutional_unit_id, :exact_work_location,
 :card_expiration_date, :card_condition, :barcode_value, :barcode_readable, :card_front_path, :card_back_path,
 :person_with_card_path, :loss_report_number, :notes, 'RECIBIDA', :submitted_at, :ip_address, :user_agent
)
SQL);

    $stmt->execute([
        'request_number' => $requestNumber,
        'position_number' => $position,
        'rank_name' => $rankName,
        'first_name' => trim((string) $_POST['first_name']),
        'middle_name' => trim((string) ($_POST['middle_name'] ?? '')) ?: null,
        'last_name' => trim((string) $_POST['last_name']),
        'second_last_name' => trim((string) ($_POST['second_last_name'] ?? '')) ?: null,
        'national_id' => $nationalId,
        'promotion_type' => trim((string) $_POST['promotion_type']),
        'promotion_number' => trim((string) ($_POST['promotion_number'] ?? '')) ?: null,
        'email' => $email,
        'phone' => trim((string) $_POST['phone']),
        'institutional_unit_id' => $unitId,
        'exact_work_location' => $exactWorkLocation,
        'card_expiration_date' => $expirationDate->format('Y-m-d'),
        'card_condition' => $condition,
        'barcode_value' => $barcode ?: null,
        'barcode_readable' => (int) ($_POST['barcode_readable'] ?? 1),
        'card_front_path' => $frontPath,
        'card_back_path' => $backPath,
        'person_with_card_path' => $personPath,
        'loss_report_number' => trim((string) ($_POST['loss_report_number'] ?? '')) ?: null,
        'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        'submitted_at' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
    ]);

    $requestId = (int) $pdo->lastInsertId();
    $history = $pdo->prepare('INSERT INTO status_history (request_id, previous_status, new_status, observation, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)');
    $history->execute([$requestId, null, 'RECIBIDA', 'Solicitud registrada por el funcionario.', 'SISTEMA', date('Y-m-d H:i:s')]);
    $pdo->commit();

    $subject = 'Solicitud de validación de carné recibida';
    $body = "Estimado(a) funcionario(a):\n\nSu solicitud fue recibida correctamente.\n\nNúmero de solicitud: {$requestNumber}\n\nPara consultar el estado, utilice el número de solicitud junto con los últimos cuatro dígitos de su cédula.\n\nDirección Nacional de Recursos Humanos";
    $headers = 'From: ' . $config['from_email'] . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
    @mail($email, $subject, $body, $headers);

    header('Location: index.php?success=1&request=' . rawurlencode($requestNumber));
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirectError($exception->getMessage());
}
