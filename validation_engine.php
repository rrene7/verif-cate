<?php
declare(strict_types=1);

function calculateRisk(array $r): array
{
    $score = 0;
    $reasons = [];

    if (!empty($r['card_expiration_date'])) {
        $days = (int)(new DateTimeImmutable('today'))
            ->diff(new DateTimeImmutable($r['card_expiration_date']))
            ->format('%r%a');

        if ($days < 0) { $score += 45; $reasons[] = 'Carné vencido'; }
        elseif ($days <= 15) { $score += 25; $reasons[] = 'Próximo a vencer'; }
        elseif ($days <= 30) { $score += 15; $reasons[] = 'Vence en 30 días'; }
    } else {
        $score += 20;
        $reasons[] = 'Sin expiración';
    }

    $conditionRisk = [
        'DETERIORADO'=>25, 'CODIGO_ILEGIBLE'=>35, 'DATOS_INCORRECTOS'=>35,
        'EXTRAVIADO_REPORTADO'=>25, 'EXTRAVIADO_NO_REPORTADO'=>40,
        'ROBADO'=>40, 'NO_RECIBIDO'=>20
    ];

    $condition = (string)($r['card_condition'] ?? '');
    if (isset($conditionRisk[$condition])) {
        $score += $conditionRisk[$condition];
        $reasons[] = 'Condición especial';
    }

    $missing = 0;
    foreach (['card_front_path','card_back_path','person_with_card_path'] as $f) {
        if (empty($r[$f])) $missing++;
    }
    if ($missing) {
        $score += min(30, $missing * 10);
        $reasons[] = "Faltan {$missing} evidencias";
    }

    if (empty($r['barcode_value'])) {
        $score += 20;
        $reasons[] = 'Sin código';
    }

    if (!empty($r['duplicate_flag'])) {
        $score += 40;
        $reasons[] = 'Posible duplicado';
    }

    $verified = (int)($r['barcode_verified'] ?? 0)
        + (int)($r['identity_verified'] ?? 0)
        + (int)($r['photos_verified'] ?? 0)
        + (int)($r['expiration_verified'] ?? 0);

    $score = max(0, min(100, $score - ($verified * 8)));
    $level = $score >= 60 ? 'ALTO' : ($score >= 30 ? 'MEDIO' : 'BAJO');

    return ['score'=>$score, 'level'=>$level, 'reasons'=>array_unique($reasons)];
}

function findPotentialDuplicates(PDO $pdo, array $r): array
{
    $parts = [];
    $params = ['id'=>(int)$r['id']];

    foreach (['national_id','barcode_value','email','phone'] as $field) {
        $value = trim((string)($r[$field] ?? ''));
        if ($value !== '') {
            $parts[] = "r.$field = :$field";
            $params[$field] = $value;
        }
    }

    if (!$parts) return [];

    $sql = 'SELECT id,request_number,national_id,barcode_value,email,phone,status
            FROM requests r WHERE r.id<>:id AND (' . implode(' OR ', $parts) . ')
            ORDER BY submitted_at DESC LIMIT 20';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $labels = [
        'national_id'=>'Cédula',
        'barcode_value'=>'Código',
        'email'=>'Correo',
        'phone'=>'Teléfono'
    ];

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $matches = [];
        foreach ($labels as $field=>$label) {
            if (!empty($r[$field]) && (string)$r[$field] === (string)$row[$field]) {
                $matches[] = $label;
            }
        }
        if ($matches) {
            $out[] = [
                'id'=>(int)$row['id'],
                'request_number'=>$row['request_number'],
                'matches'=>$matches,
                'status'=>$row['status']
            ];
        }
    }
    return $out;
}

function updateRiskAndDuplicates(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM requests WHERE id=?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r) return ['risk'=>null,'duplicates'=>[]];

    $dupes = findPotentialDuplicates($pdo, $r);
    $r['duplicate_flag'] = $dupes ? 1 : 0;
    $risk = calculateRisk($r);

    $notes = array_map(
        fn($d) => $d['request_number'].' ('.implode(', ', $d['matches']).')',
        $dupes
    );

    $up = $pdo->prepare(
        'UPDATE requests SET risk_level=?,risk_score=?,duplicate_flag=?,duplicate_notes=? WHERE id=?'
    );
    $up->execute([
        $risk['level'], $risk['score'], $dupes ? 1 : 0,
        $notes ? implode(' | ', $notes) : null, $id
    ]);

    return ['risk'=>$risk,'duplicates'=>$dupes];
}

function writeAdminAudit(PDO $pdo, ?int $requestId, string $user, string $action, ?string $detail=null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO admin_audit_log
        (request_id,admin_user,action_name,action_detail,ip_address,user_agent,created_at)
        VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $requestId, $user, $action, $detail,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500),
        date('Y-m-d H:i:s')
    ]);
}
