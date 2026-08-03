<?php

declare(strict_types=1);

$path = __DIR__ . '/admin_update.php';
$content = file_get_contents($path);

if ($content === false) {
    fwrite(STDERR, "No se pudo leer admin_update.php.\n");
    exit(1);
}

$content = str_replace(
    "'POSIBLE_DUPLICADO',",
    "'POSIBLE_DUPLICADO',\n    'PERSONA_NO_COINCIDE',",
    $content
);

$content = str_replace(
    '$score = (int) round(' . PHP_EOL
    . '    (($barcodeVerified + $identityVerified + $photosVerified + $expirationVerified) / 4) * 100' . PHP_EOL
    . ');',
    '$cardIntegrityVerified = isset($_POST[\'card_integrity_verified\']) ? 1 : 0;' . PHP_EOL
    . '$personVerified = isset($_POST[\'person_verified\']) ? 1 : 0;' . PHP_EOL
    . '$score = (int) round(' . PHP_EOL
    . '    (($barcodeVerified + $identityVerified + $photosVerified + $expirationVerified + $cardIntegrityVerified + $personVerified) / 6) * 100' . PHP_EOL
    . ');',
    $scoreCount
);

if ($scoreCount === 0) {
    fwrite(STDERR, "No se encontró el cálculo de puntaje esperado.\n");
    exit(1);
}

if (file_put_contents($path, $content) === false) {
    fwrite(STDERR, "No se pudo guardar admin_update.php.\n");
    exit(1);
}

echo "Guardado manual ajustado.\n";
