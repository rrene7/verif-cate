<?php

declare(strict_types=1);

session_start();
require dirname(__DIR__) . '/db.php';

function v3Date(?string $value, bool $time = false): string
{
    if (!$value) {
        return 'No indicada';
    }

    try {
        $date = new DateTimeImmutable($value);
        return $date->format($time ? 'd/m/Y h:i A' : 'd/m/Y');
    } catch (Throwable) {
        return $value;
    }
}

function v3Expiration(?string $value): array
{
    if (!$value) {
        return ['label' => 'Sin fecha', 'class' => 'neutral', 'days' => null];
    }

    $today = new DateTimeImmutable('today');
    $expiration = new DateTimeImmutable($value);
    $days = (int) $today->diff($expiration)->format('%r%a');

    if ($days < 0) {
        return ['label' => 'Vencido', 'class' => 'danger', 'days' => $days];
    }

    if ($days <= 30) {
        return ['label' => "Vence en {$days} días", 'class' => 'warning', 'days' => $days];
    }

    return ['label' => 'Vigente', 'class' => 'success', 'days' => $days];
}

function v3AuthRequired(): void
{
    if (empty($_SESSION['admin_authenticated'])) {
        header('Location: ../admin_v3.php');
        exit;
    }
}

function v3FullName(array $row): string
{
    return trim(
        ($row['rank_name'] ?? '') . ' '
        . ($row['first_name'] ?? '') . ' '
        . ($row['middle_name'] ?? '') . ' '
        . ($row['last_name'] ?? '') . ' '
        . ($row['second_last_name'] ?? '')
    );
}

function v3RequestSelect(): string
{
    return '
        SELECT
            r.*,
            iu.code AS institutional_unit_code,
            iu.name AS institutional_unit_name,
            iu.category AS institutional_unit_category
        FROM requests r
        LEFT JOIN institutional_units iu
            ON iu.id = r.institutional_unit_id
    ';
}
