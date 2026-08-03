document.addEventListener('DOMContentLoaded', () => {
    const runButton = document.getElementById('runOcrValidation');

    if (!runButton) {
        return;
    }

    const frontImage = runButton.dataset.front || '';
    const backImage = runButton.dataset.back || '';
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

    const containsNormalized = (haystack, needle) => {
        const normalizedNeedle = normalize(needle);
        return normalizedNeedle !== '' && normalize(haystack).includes(normalizedNeedle);
    };

    const escapeHtml = (value) =>
        String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    const setStatus = (message, type = '') => {
        if (!statusElement) return;
        statusElement.textContent = message;
        statusElement.className = type ? `ocr-status ${type}` : 'ocr-status';
    };

    const setProgress = (percentage) => {
        if (!progressElement) return;
        progressElement.style.width = `${Math.max(0, Math.min(100, percentage))}%`;
    };

    const compareText = (frontText, backText) => {
        const combined = `${frontText}\n${backText}`;
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
                matched:
                    containsNormalized(combined, expected.expiration) ||
                    containsNormalized(combined, expected.expiration.split('-').reverse().join('/'))
            },
            {
                label: 'Código de barras',
                expected: expected.barcode,
                matched: containsNormalized(combined, expected.barcode)
            }
        ];

        const applicable = checks.filter((check) => normalize(check.expected) !== '');
        const matched = applicable.filter((check) => check.matched).length;
        const score = applicable.length ? Math.round((matched / applicable.length) * 100) : 0;

        if (scoreElement) {
            scoreElement.textContent = `${score}%`;
        }

        if (comparisonElement) {
            comparisonElement.innerHTML = applicable.map((check) => `
                <div class="ocr-check ${check.matched ? 'matched' : 'not-matched'}">
                    <span>${check.matched ? '✓' : '!'}</span>
                    <div>
                        <strong>${escapeHtml(check.label)}</strong>
                        <small>${check.matched ? 'Coincidencia encontrada' : `No se encontró: ${escapeHtml(check.expected)}`}</small>
                    </div>
                </div>
            `).join('');
        }

        return score;
    };

    const recognizeImage = async (imageUrl, label) => {
        if (!imageUrl) {
            return '';
        }

        const result = await Tesseract.recognize(
            imageUrl,
            'spa+eng',
            {
                logger: (message) => {
                    if (message.status === 'recognizing text') {
                        setProgress(Math.round((message.progress || 0) * 100));
                        setStatus(`${label}: reconociendo texto ${Math.round((message.progress || 0) * 100)}%`);
                    } else if (message.status) {
                        setStatus(`${label}: ${message.status}`);
                    }
                }
            }
        );

        return result?.data?.text || '';
    };

    const saveResult = async (frontText, backText, score) => {
        const form = new FormData();
        form.append('id', requestId);
        form.append('ocr_front_text', frontText);
        form.append('ocr_back_text', backText);
        form.append('ocr_match_score', String(score));

        const response = await fetch('admin_ocr_save.php', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        });

        const payload = await response.json();

        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'No se pudo guardar el resultado OCR.');
        }
    };

    runButton.addEventListener('click', async () => {
        if (!window.Tesseract) {
            setStatus('No fue posible cargar el motor OCR. Verifique la conexión a Internet.', 'danger');
            return;
        }

        if (!frontImage && !backImage) {
            setStatus('No existen imágenes del carné para analizar.', 'danger');
            return;
        }

        runButton.disabled = true;
        setProgress(0);
        setStatus('Iniciando análisis OCR...');

        try {
            const frontText = await recognizeImage(frontImage, 'Frente');
            if (frontTextArea) frontTextArea.value = frontText;

            const backText = await recognizeImage(backImage, 'Reverso');
            if (backTextArea) backTextArea.value = backText;

            const score = compareText(frontText, backText);
            await saveResult(frontText, backText, score);

            setProgress(100);
            setStatus(`OCR completado y guardado. Coincidencia: ${score}%.`, score >= 60 ? 'success' : 'warning');
        } catch (error) {
            console.error(error);
            setStatus(`Error durante el OCR: ${error.message}`, 'danger');
        } finally {
            runButton.disabled = false;
        }
    });
});
