document.addEventListener('DOMContentLoaded', () => {
    const originalButton = document.getElementById('runOcrValidation');

    if (!originalButton) {
        return;
    }

    /*
     * Sustituimos el botón para eliminar manejadores anteriores y evitar
     * ejecutar dos veces el OCR.
     */
    const runButton = originalButton.cloneNode(true);
    originalButton.replaceWith(runButton);

    const requestId = runButton.dataset.requestId || '';
    const statusElement = document.getElementById('ocrStatus');
    const progressElement = document.getElementById('ocrProgressBar');
    const frontTextArea = document.getElementById('ocrFrontText');
    const backTextArea = document.getElementById('ocrBackText');
    const scoreElement = document.getElementById('ocrMatchScore');
    const comparisonElement = document.getElementById('ocrComparisonResults');

    const expected = {
        name: runButton.dataset.name || '',
        nationalId: runButton.dataset.nationalId || '',
        rank: runButton.dataset.rank || '',
        expiration: runButton.dataset.expiration || '',
        barcode: runButton.dataset.barcode || ''
    };

    const normalize = (value) =>
        String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^A-Z0-9]/gi, '')
            .toUpperCase();

    const escapeHtml = (value) =>
        String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    const containsNormalized = (haystack, needle) => {
        const normalizedNeedle = normalize(needle);
        return normalizedNeedle !== ''
            && normalize(haystack).includes(normalizedNeedle);
    };

    const setStatus = (message, type = '') => {
        if (!statusElement) return;
        statusElement.textContent = message;
        statusElement.className = type
            ? `ocr-status ${type}`
            : 'ocr-status';
    };

    const setProgress = (percentage) => {
        if (!progressElement) return;
        progressElement.style.width =
            `${Math.max(0, Math.min(100, percentage))}%`;
    };

    const rotateDataUrl = (sourceUrl, degrees) => new Promise(
        (resolve, reject) => {
            const image = new Image();
            image.onload = () => {
                const radians = degrees * Math.PI / 180;
                const swap = degrees === 90 || degrees === 270;
                const canvas = document.createElement('canvas');

                canvas.width = swap ? image.height : image.width;
                canvas.height = swap ? image.width : image.height;

                const context = canvas.getContext('2d');
                context.translate(canvas.width / 2, canvas.height / 2);
                context.rotate(radians);
                context.drawImage(
                    image,
                    -image.width / 2,
                    -image.height / 2
                );

                resolve(canvas.toDataURL('image/png', 1));
            };
            image.onerror = () =>
                reject(new Error('No se pudo preparar la imagen para OCR.'));
            image.src = sourceUrl;
        }
    );

    const textQualityScore = (text, side) => {
        const clean = String(text || '').trim();

        if (!clean) {
            return 0;
        }

        const alphanumeric = (clean.match(/[A-Za-zÁÉÍÓÚÜÑ0-9]/g) || []).length;
        const lines = clean.split(/\r?\n/).filter((line) => line.trim()).length;

        const keywordsFront = [
            'POLICIA',
            'PANAMA',
            'CEDULA',
            'NOMBRE',
            'RANGO',
            'POSICION',
            'SEGURIDAD',
            'PUBLICA'
        ];

        const keywordsBack = [
            'POLICIA',
            'SOCIAL',
            'SANGRE',
            'EXPEDICION',
            'EXPIRACION',
            'ARMA',
            'PATRIA'
        ];

        const keywords = side === 'front'
            ? keywordsFront
            : keywordsBack;

        const normalizedText = normalize(clean);
        const keywordPoints = keywords.reduce(
            (total, keyword) =>
                total + (normalizedText.includes(normalize(keyword)) ? 40 : 0),
            0
        );

        return alphanumeric + (lines * 8) + keywordPoints;
    };

    const recognizeOneOrientation = async (
        sourceUrl,
        side,
        degrees,
        globalStart,
        globalSpan
    ) => {
        const rotatedUrl = degrees === 0
            ? sourceUrl
            : await rotateDataUrl(sourceUrl, degrees);

        const response = await Tesseract.recognize(
            rotatedUrl,
            'spa+eng',
            {
                logger: (message) => {
                    if (message.status === 'recognizing text') {
                        const localProgress = message.progress || 0;
                        setProgress(
                            globalStart + Math.round(localProgress * globalSpan)
                        );
                        setStatus(
                            `${side === 'front' ? 'Frente' : 'Reverso'}: probando orientación ${degrees}° — ${Math.round(localProgress * 100)}%`
                        );
                    }
                }
            }
        );

        const text = response?.data?.text || '';

        return {
            degrees,
            text,
            score: textQualityScore(text, side)
        };
    };

    const recognizeBestOrientation = async (
        sourceUrl,
        side,
        progressStart,
        progressTotal
    ) => {
        if (!sourceUrl) {
            return {
                degrees: 0,
                text: '',
                score: 0
            };
        }

        /*
         * El detector ya entrega el carné horizontal. En la práctica solo
         * necesitamos escoger entre derecho y boca abajo. Probar 0° y 180°
         * reduce el tiempo a la mitad y corrige el caso observado.
         */
        const orientations = [0, 180];
        const span = progressTotal / orientations.length;
        const candidates = [];

        for (let index = 0; index < orientations.length; index++) {
            candidates.push(
                await recognizeOneOrientation(
                    sourceUrl,
                    side,
                    orientations[index],
                    progressStart + (index * span),
                    span
                )
            );
        }

        candidates.sort((a, b) => b.score - a.score);
        return candidates[0];
    };

    const compareText = (frontText, backText) => {
        const combined = `${frontText}\n${backText}`;

        const expirationAlternatives = [
            expected.expiration,
            expected.expiration.split('-').reverse().join('/'),
            expected.expiration.split('-').reverse().join('-')
        ];

        const checks = [
            {
                label: 'Nombre',
                expected: expected.name,
                matched: containsNormalized(combined, expected.name)
            },
            {
                label: 'Cédula',
                expected: expected.nationalId,
                matched: containsNormalized(combined, expected.nationalId)
            },
            {
                label: 'Rango',
                expected: expected.rank,
                matched: containsNormalized(combined, expected.rank)
            },
            {
                label: 'Expiración',
                expected: expected.expiration,
                matched: expirationAlternatives.some(
                    (value) => containsNormalized(combined, value)
                )
            },
            {
                label: 'Código de barras',
                expected: expected.barcode,
                matched: containsNormalized(combined, expected.barcode)
            }
        ];

        const applicable = checks.filter(
            (check) => normalize(check.expected) !== ''
        );

        const matchedCount = applicable.filter(
            (check) => check.matched
        ).length;

        const score = applicable.length
            ? Math.round((matchedCount / applicable.length) * 100)
            : 0;

        if (scoreElement) {
            scoreElement.textContent = `${score}%`;
        }

        if (comparisonElement) {
            comparisonElement.innerHTML = applicable.map((check) => `
                <div class="ocr-check ${check.matched ? 'matched' : 'not-matched'}">
                    <span>${check.matched ? '✓' : '!'}</span>
                    <div>
                        <strong>${escapeHtml(check.label)}</strong>
                        <small>
                            ${check.matched
                                ? 'Coincidencia encontrada'
                                : `No se encontró: ${escapeHtml(check.expected)}`}
                        </small>
                    </div>
                </div>
            `).join('');
        }

        return score;
    };

    const saveResult = async (
        frontText,
        backText,
        score,
        orientationSummary
    ) => {
        const form = new FormData();
        form.append('id', requestId);
        form.append('ocr_front_text', frontText);
        form.append('ocr_back_text', backText);
        form.append('ocr_match_score', String(score));
        form.append('ocr_orientation_summary', orientationSummary);

        const response = await fetch('admin_ocr_save.php', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        });

        const payload = await response.json();

        if (!response.ok || !payload.ok) {
            throw new Error(
                payload.message || 'No se pudo guardar el resultado OCR.'
            );
        }
    };

    runButton.addEventListener('click', async () => {
        if (!window.Tesseract) {
            setStatus(
                'No fue posible cargar el motor OCR. Verifique la conexión.',
                'danger'
            );
            return;
        }

        const processed = window.VerifCateProcessed || {};
        const frontSource =
            processed.front?.enhancedUrl
            || runButton.dataset.front
            || '';

        const backSource =
            processed.back?.enhancedUrl
            || runButton.dataset.back
            || '';

        if (!frontSource && !backSource) {
            setStatus('No existen imágenes para analizar.', 'danger');
            return;
        }

        runButton.disabled = true;
        setProgress(0);
        setStatus('Iniciando orientación automática y OCR...');

        try {
            const frontResult = await recognizeBestOrientation(
                frontSource,
                'front',
                0,
                50
            );

            const backResult = await recognizeBestOrientation(
                backSource,
                'back',
                50,
                50
            );

            if (frontTextArea) {
                frontTextArea.value = frontResult.text;
            }

            if (backTextArea) {
                backTextArea.value = backResult.text;
            }

            const score = compareText(
                frontResult.text,
                backResult.text
            );

            const summary =
                `Frente: ${frontResult.degrees}°. `
                + `Reverso: ${backResult.degrees}°.`;

            await saveResult(
                frontResult.text,
                backResult.text,
                score,
                summary
            );

            setProgress(100);
            setStatus(
                `OCR completado. ${summary} Coincidencia: ${score}%.`,
                score >= 60 ? 'success' : 'warning'
            );
        } catch (error) {
            console.error(error);
            setStatus(
                `Error durante el OCR: ${error.message}`,
                'danger'
            );
        } finally {
            runButton.disabled = false;
        }
    });
});
