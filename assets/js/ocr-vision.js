document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('runVisionOcr');

    if (!button) {
        return;
    }

    const status = document.getElementById('visionStatus');
    const results = document.getElementById('visionResults');
    const previewFront = document.getElementById('visionFrontPreview');
    const previewBack = document.getElementById('visionBackPreview');

    window.VerifCateProcessed = window.VerifCateProcessed || {
        front: null,
        back: null
    };

    const setStatus = (message, type = '') => {
        if (!status) return;
        status.textContent = message;
        status.className = type ? `ocr-status ${type}` : 'ocr-status';
    };

    const qualityHtml = (label, quality, rotationDegrees) => `
        <article class="vision-quality-card">
            <h4>${label}</h4>
            <div class="${quality.coverageOk ? 'quality-ok' : 'quality-bad'}">
                Cobertura del carné: ${quality.coverage}%
            </div>
            <div class="${quality.blurOk ? 'quality-ok' : 'quality-bad'}">
                Nitidez: ${quality.blur}
            </div>
            <div class="${quality.glareOk ? 'quality-ok' : 'quality-bad'}">
                Reflejo: ${quality.glare}%
            </div>
            <div class="quality-ok">
                Rotación aplicada al recorte: ${rotationDegrees || 0}°
            </div>
        </article>
    `;

    button.addEventListener('click', async () => {
        if (!window.cv || !window.VerifCateVision) {
            setStatus(
                'El módulo de visión todavía está cargando. Espere unos segundos e intente nuevamente.',
                'warning'
            );
            return;
        }

        const front = button.dataset.front || '';
        const back = button.dataset.back || '';

        if (!front && !back) {
            setStatus('No hay fotografías para analizar.', 'danger');
            return;
        }

        button.disabled = true;
        if (results) results.innerHTML = '';

        try {
            setStatus('Detectando y recortando el frente...');
            const frontResult = front
                ? await window.VerifCateVision.process(front)
                : null;

            setStatus('Detectando y recortando el reverso...');
            const backResult = back
                ? await window.VerifCateVision.process(back)
                : null;

            window.VerifCateProcessed.front = frontResult;
            window.VerifCateProcessed.back = backResult;

            if (frontResult && previewFront) {
                previewFront.src = frontResult.croppedUrl;
                previewFront.hidden = false;
            }

            if (backResult && previewBack) {
                previewBack.src = backResult.croppedUrl;
                previewBack.hidden = false;
            }

            if (results) {
                results.innerHTML =
                    (frontResult
                        ? qualityHtml(
                            'Frente',
                            frontResult.quality,
                            frontResult.rotationDegrees
                        )
                        : '') +
                    (backResult
                        ? qualityHtml(
                            'Reverso',
                            backResult.quality,
                            backResult.rotationDegrees
                        )
                        : '');
            }

            setStatus(
                'Procesamiento completado. Presione “Ejecutar OCR y comparar”; el sistema probará automáticamente las orientaciones.',
                'success'
            );
        } catch (error) {
            console.error(error);
            setStatus(
                error.message || 'No fue posible procesar las imágenes.',
                'danger'
            );
        } finally {
            button.disabled = false;
        }
    });
});
