<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

$db = $config['database'];
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $db['host'],
    $db['port'],
    $db['name'],
    $db['charset']
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    exit('No se pudo conectar con MySQL. Importe database/schema.sql y revise config.php.');
}

$storageDir = __DIR__ . '/storage';
$uploadDir = $storageDir . '/uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    throw new RuntimeException('No se pudo crear la carpeta de evidencias.');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function generateRequestNumber(PDO $pdo): string
{
    do {
        $number = 'VC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(5)));
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
