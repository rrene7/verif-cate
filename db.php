<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

$storageDir = __DIR__ . '/storage';
$uploadDir = $storageDir . '/uploads';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$pdo = new PDO('sqlite:' . $storageDir . '/verif-cate.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_number TEXT NOT NULL UNIQUE,
    position_number TEXT NOT NULL UNIQUE,
    rank_name TEXT NOT NULL,
    first_name TEXT NOT NULL,
    middle_name TEXT,
    last_name TEXT NOT NULL,
    second_last_name TEXT,
    national_id TEXT NOT NULL UNIQUE,
    promotion_type TEXT NOT NULL,
    promotion_number TEXT,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    national_directorate TEXT NOT NULL,
    zone_name TEXT,
    area_name TEXT,
    service_name TEXT NOT NULL,
    card_condition TEXT NOT NULL,
    barcode_value TEXT,
    barcode_readable INTEGER NOT NULL DEFAULT 1,
    card_front_path TEXT,
    card_back_path TEXT,
    person_with_card_path TEXT,
    loss_report_number TEXT,
    notes TEXT,
    status TEXT NOT NULL DEFAULT 'RECIBIDA',
    admin_observation TEXT,
    submitted_at TEXT NOT NULL,
    reviewed_at TEXT,
    reviewed_by TEXT,
    ip_address TEXT,
    user_agent TEXT
)
SQL);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    previous_status TEXT,
    new_status TEXT NOT NULL,
    observation TEXT,
    changed_by TEXT NOT NULL,
    changed_at TEXT NOT NULL,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
)
SQL);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function generateRequestNumber(PDO $pdo): string
{
    do {
        $random = strtoupper(bin2hex(random_bytes(5)));
        $number = 'VC-' . date('Y') . '-' . $random;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE request_number = ?');
        $stmt->execute([$number]);
    } while ((int) $stmt->fetchColumn() > 0);

    return $number;
}

function normalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function whatsappUrl(string $phone, string $requestNumber): string
{
    $number = normalizePhone($phone);
    if (strlen($number) === 8) {
        $number = '507' . $number;
    }
    $message = rawurlencode("Buenos días. Le contactamos de la Dirección Nacional de Recursos Humanos con relación a su solicitud de validación de carné {$requestNumber}.");
    return "https://wa.me/{$number}?text={$message}";
}

function saveUploadedImage(string $field, string $requestNumber, array $config, string $uploadDir): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("No se pudo cargar la evidencia {$field}.");
    }
    if ($file['size'] > $config['upload_max_bytes']) {
        throw new RuntimeException('Una de las imágenes excede el tamaño permitido de 8 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $config['allowed_mime_types'], true)) {
        throw new RuntimeException('Las evidencias deben ser imágenes JPG, PNG o WEBP.');
    }

    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $filename = $requestNumber . '-' . $field . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('No se pudo guardar una de las evidencias.');
    }

    return 'storage/uploads/' . $filename;
}
