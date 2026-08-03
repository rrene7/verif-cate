<?php

declare(strict_types=1);

$path = __DIR__ . '/admin.php';
$content = file_get_contents($path);

if ($content === false) {
    fwrite(STDERR, "No se pudo leer admin.php.\n");
    exit(1);
}

$anchor = <<<'HTML'
                <div class="validation-layout">
HTML;

$ocrBlock = <<<'HTML'
                <section class="ocr-panel">
                    <div class="ocr-panel-heading">
                        <div>
                            <span class="eyebrow">Validación asistida</span>
                            <h3>Lectura OCR del carné</h3>
                            <p>Extrae texto del frente y reverso y lo compara con los datos registrados. El resultado es de apoyo; la decisión final corresponde al revisor.</p>
                        </div>

                        <div class="ocr-score-box">
                            <span>Coincidencia OCR</span>
                            <strong id="ocrMatchScore"><?= (int) ($selected['ocr_match_score'] ?? 0) ?>%</strong>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="runOcrValidation"
                        data-request-id="<?= (int) $selected['id'] ?>"
                        data-front="<?= e($selected['card_front_path'] ?? '') ?>"
                        data-back="<?= e($selected['card_back_path'] ?? '') ?>"
                        data-name="<?= e(trim($selected['first_name'] . ' ' . ($selected['middle_name'] ?? '') . ' ' . $selected['last_name'] . ' ' . ($selected['second_last_name'] ?? ''))) ?>"
                        data-national-id="<?= e($selected['national_id']) ?>"
                        data-rank="<?= e($selected['rank_name']) ?>"
                        data-expiration="<?= e($selected['card_expiration_date'] ?? '') ?>"
                        data-barcode="<?= e($selected['barcode_value'] ?? '') ?>"
                    >
                        Ejecutar OCR y comparar
                    </button>

                    <div class="ocr-progress">
                        <span id="ocrProgressBar"></span>
                    </div>

                    <p class="ocr-status" id="ocrStatus">
                        <?= !empty($selected['ocr_last_run_at'])
                            ? 'Último análisis: ' . e(formatDateEs($selected['ocr_last_run_at'], true))
                            : 'Aún no se ha ejecutado el OCR.' ?>
                    </p>

                    <div class="ocr-comparison" id="ocrComparisonResults"></div>

                    <details class="ocr-text-details">
                        <summary>Ver texto reconocido</summary>

                        <div class="ocr-text-grid">
                            <div>
                                <label for="ocrFrontText">Texto del frente</label>
                                <textarea id="ocrFrontText" readonly><?= e($selected['ocr_front_text'] ?? '') ?></textarea>
                            </div>

                            <div>
                                <label for="ocrBackText">Texto del reverso</label>
                                <textarea id="ocrBackText" readonly><?= e($selected['ocr_back_text'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </details>
                </section>

                <div class="validation-layout">
HTML;

if (!str_contains($content, 'id="runOcrValidation"')) {
    $position = strpos($content, $anchor);

    if ($position === false) {
        fwrite(STDERR, "No se encontró el bloque de validación.\n");
        exit(1);
    }

    $content = substr_replace($content, $ocrBlock, $position, strlen($anchor));
}

$scriptAnchor = '<script src="assets/js/barcode-fallback.js?v=20260731-1"></script>';

$ocrScripts = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="assets/js/ocr-admin.js?v=20260731-1"></script>
HTML;

if (!str_contains($content, 'assets/js/ocr-admin.js')) {
    if (str_contains($content, $scriptAnchor)) {
        $content = str_replace($scriptAnchor, $scriptAnchor . PHP_EOL . $ocrScripts, $content);
    } else {
        $content = str_replace('</body>', $ocrScripts . PHP_EOL . '</body>', $content);
    }
}

$content = preg_replace(
    '#assets/css/admin\.css\?v=[^"]+#',
    'assets/css/admin.css?v=20260731-5',
    $content,
    1
);

if (file_put_contents($path, $content) === false) {
    fwrite(STDERR, "No se pudo guardar admin.php.\n");
    exit(1);
}

echo "Panel OCR integrado.\n";
