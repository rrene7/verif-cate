<?php

declare(strict_types=1);

return [
    'app_name' => 'Verif-Carné',
    'organization' => 'Dirección Nacional de Recursos Humanos',
    'base_url' => '',
    'timezone' => 'America/Panama',
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'verif_cate',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'admin_user' => getenv('ADMIN_USER') ?: 'admin',
    'admin_password' => getenv('ADMIN_PASSWORD') ?: 'Cambiar123!',
    'from_email' => getenv('FROM_EMAIL') ?: 'no-reply@policia.gob.pa',
    'upload_max_bytes' => 8 * 1024 * 1024,
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'statuses' => [
        'RECIBIDA' => 'Solicitud recibida',
        'EN_REVISION' => 'En revisión',
        'VALIDADA' => 'Validada',
        'OBSERVADA' => 'Observada',
        'EVIDENCIA_INSUFICIENTE' => 'Evidencia insuficiente',
        'CODIGO_DUPLICADO' => 'Código duplicado',
        'CODIGO_ILEGIBLE' => 'Código ilegible',
        'CARNE_DETERIORADO' => 'Carné deteriorado',
        'EXTRAVIADO_REPORTADO' => 'Extraviado reportado',
        'EXTRAVIADO_NO_REPORTADO' => 'Extraviado no reportado',
        'VERIFICACION_PRESENCIAL' => 'Requiere verificación presencial',
        'RECHAZADA' => 'Rechazada',
    ],
];
