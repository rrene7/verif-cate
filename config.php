<?php

declare(strict_types=1);

return [
    'app_name' => 'Verif-Carné',
    'organization' => 'Dirección Nacional de Recursos Humanos',
    'base_url' => '',
    'timezone' => 'America/Panama',
    'admin_user' => 'admin',
    'admin_password' => 'Cambiar123!',
    'from_email' => 'no-reply@policia.gob.pa',
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
