document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('runZoneOcr');

    if (!button) {
        return;
    }

    const status = document.getElementById('zoneOcrStatus');
    const results = document.getElementById('zoneOcrResults');
    const preview = document.getElementById('zoneOcrPreview');
    const scoreBox = document.getElementById('zoneOcrScore');

    const requestId = button.dataset.requestId || '';

    const expected = {
        name: button.dataset.name || '',
        nationalId: button.dataset.nationalId || '',
        rank: button.dataset.rank || '',
        expiration: button.dataset.expiration || '',
        barcode: button.dataset.barcode || ''
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

    const setStatus = (message, type = '') => {
        status.textContent = message;
        status.className = type ? `ocr-status ${type}` : 'ocr-status';
    };

    const loadImage = (source) => new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('No se pudo cargar el recorte.'));
        image.src = source;
    });

    const rotateImage = async (source, degrees) => {
        const image = await loadImage(source);
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');

        const swap = degrees === 90 || degrees === 270;
        canvas.width = swap ? image.height : image.width;
        canvas.height = swap ? image.width : image.height;

        context.translate(canvas.width / 2, canvas.height / 2);
        context.rotate(degrees * Math.PI / 180);
        context.drawImage(image, -image.width / 2, -image.height / 2);

        return canvas.toDataURL('image/png', 1);
    };

    const cropRegion = async (source, region, scale = 3) => {
        const image = await loadImage(source);
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');

        const sx = Math.round(image.width * region.x);
        const sy = Math.round(image.height * region.y);
        const sw = Math.round(image.width * region.w);
        const sh = Math.round(image.height * region.h);

        canvas.width = Math.max(1, sw * scale);
        canvas.height = Math.max(1, sh * scale);

        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(
            image,
            sx,
            sy,
            sw,
            sh,
            0,
            0,
            canvas.width,
            canvas.height
        );

        /*
         * Contraste sencillo, conservando color. El OCR suele responder mejor
         * con una ampliación grande que con un umbral agresivo.
         */
        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;

        for (let index = 0; index < data.length; index += 4) {
            const gray =
                (data[index] * 0.299)
                + (data[index + 1] * 0.587)
                + (data[index + 2] * 0.114);

            const adjusted = gray > 165
                ? Math.min(255, gray * 1.12)
                : Math.max(0, gray * 0.82);

            data[index] = adjusted;
            data[index + 1] = adjusted;
            data[index + 2] = adjusted;
        }

        context.putImageData(imageData, 0, 0);
        return canvas.toDataURL('image/png', 1);
    };

    const recognize = async (source, label) => {
        setStatus(`${label}: reconociendo texto...`);

        const response = await Tesseract.recognize(
            source,
            'spa+eng',
            {
                logger: (message) => {
                    if (message.status === 'recognizing text') {
                        setStatus(
                            `${label}: ${Math.round((message.progress || 0) * 100)}%`
                        );
                    }
                }
            }
        );

        return response?.data?.text || '';
    };

    const levenshtein = (left, right) => {
        const a = normalize(left);
        const b = normalize(right);

        if (!a.length) return b.length;
        if (!b.length) return a.length;

        const matrix = Array.from(
            { length: b.length + 1 },
            () => Array(a.length + 1).fill(0)
        );

        for (let i = 0; i <= b.length; i++) matrix[i][0] = i;
        for (let j = 0; j <= a.length; j++) matrix[0][j] = j;

        for (let i = 1; i <= b.length; i++) {
            for (let j = 1; j <= a.length; j++) {
                const cost = b[i - 1] === a[j - 1] ? 0 : 1;
                matrix[i][j] = Math.min(
                    matrix[i - 1][j] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j - 1] + cost
                );
            }
        }

        return matrix[b.length][a.length];
    };

    const fuzzyContains = (text, expectedValue) => {
        const haystack = normalize(text);
        const needle = normalize(expectedValue);

        if (!needle) return false;
        if (haystack.includes(needle)) return true;

        const tolerance = Math.max(1, Math.floor(needle.length * 0.18));

        if (haystack.length < needle.length) {
            return levenshtein(haystack, needle) <= tolerance;
        }

        for (let start = 0; start <= haystack.length - needle.length; start++) {
            const fragment = haystack.slice(start, start + needle.length);
            if (levenshtein(fragment, needle) <= tolerance) {
                return true;
            }
        }

        return false;
    };

    const readBarcode = async (source) => {
        if (
            !window.ZXingBrowser
            || typeof window.ZXingBrowser.BrowserMultiFormatReader !== 'function'
        ) {
            return '';
        }

        try {
            const reader = new window.ZXingBrowser.BrowserMultiFormatReader();
            const result = await reader.decodeFromImageUrl(source);

            if (typeof result?.getText === 'function') {
                return result.getText();
            }

            return result?.text || '';
        } catch {
            return '';
        }
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
            throw new Error(payload.message || 'No se pudo guardar el análisis.');
        }
    };

    button.addEventListener('click', async () => {
        if (!window.Tesseract) {
            setStatus('El motor OCR no está disponible.', 'danger');
            return;
        }

        const processed = window.VerifCateProcessed || {};
        const frontSource = processed.front?.croppedUrl || '';
        const backSource = processed.back?.croppedUrl || '';

        if (!frontSource || !backSource) {
            setStatus(
                'Primero presione “Recortar, mejorar y leer”.',
                'warning'
            );
            return;
        }

        button.disabled = true;
        results.innerHTML = '';
        preview.innerHTML = '';

        try {
            /*
             * Los recortes observados quedan boca abajo. Se normalizan a 180°
             * antes de aplicar las zonas.
             */
            const uprightFront = await rotateImage(frontSource, 180);
            const uprightBack = await rotateImage(backSource, 180);

            /*
             * Zonas relativas del formato institucional.
             * Frente: bloque de datos, excluyendo fotografía y encabezado.
             * Reverso: bloque azul de fechas/datos y zona amplia del código.
             */
            const frontDataRegion = {
                x: 0.28,
                y: 0.22,
                w: 0.68,
                h: 0.62
            };

            const backDataRegion = {
                x: 0.05,
                y: 0.27,
                w: 0.90,
                h: 0.42
            };

            const backBarcodeRegion = {
                x: 0.05,
                y: 0.58,
                w: 0.90,
                h: 0.38
            };

            const frontZone = await cropRegion(
                uprightFront,
                frontDataRegion,
                4
            );

            const backZone = await cropRegion(
                uprightBack,
                backDataRegion,
                4
            );

            const barcodeZone = await cropRegion(
                uprightBack,
                backBarcodeRegion,
                3
            );

            preview.innerHTML = `
                <article>
                    <strong>Zona de datos del frente</strong>
                    <img src="${frontZone}" alt="Zona del frente">
                </article>
                <article>
                    <strong>Zona de fechas del reverso</strong>
                    <img src="${backZone}" alt="Zona del reverso">
                </article>
                <article>
                    <strong>Zona del código de barras</strong>
                    <img src="${barcodeZone}" alt="Zona del código">
                </article>
            `;

            const frontText = await recognize(frontZone, 'Datos del frente');
            const backText = await recognize(backZone, 'Datos del reverso');
            const detectedBarcode = await readBarcode(barcodeZone);

            const expirationAlternatives = [
                expected.expiration,
                expected.expiration.split('-').reverse().join('/'),
                expected.expiration.split('-').reverse().join('-')
            ];

            const checks = [
                {
                    label: 'Nombre',
                    expected: expected.name,
                    matched: fuzzyContains(frontText, expected.name)
                },
                {
                    label: 'Cédula',
                    expected: expected.nationalId,
                    matched: fuzzyContains(frontText, expected.nationalId)
                },
                {
                    label: 'Rango',
                    expected: expected.rank,
                    matched: fuzzyContains(frontText, expected.rank)
                },
                {
                    label: 'Expiración',
                    expected: expected.expiration,
                    matched: expirationAlternatives.some(
                        (value) => fuzzyContains(backText, value)
                    )
                },
                {
                    label: 'Código de barras',
                    expected: expected.barcode,
                    detected: detectedBarcode,
                    matched:
                        normalize(detectedBarcode) !== ''
                        && normalize(detectedBarcode) === normalize(expected.barcode)
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

            scoreBox.textContent = `${score}%`;

            results.innerHTML = applicable.map((check) => `
                <div class="ocr-check ${check.matched ? 'matched' : 'not-matched'}">
                    <span>${check.matched ? '✓' : '!'}</span>
                    <div>
                        <strong>${escapeHtml(check.label)}</strong>
                        <small>
                            ${check.matched
                                ? 'Coincidencia encontrada'
                                : check.detected
                                    ? `Detectado: ${escapeHtml(check.detected)}`
                                    : `No se encontró: ${escapeHtml(check.expected)}`}
                        </small>
                    </div>
                </div>
            `).join('');

            const storedBackText = `${backText}\nCÓDIGO DETECTADO: ${detectedBarcode}`;
            await saveResult(frontText, storedBackText, score);

            setStatus(
                `Lectura por zonas completada y guardada. Coincidencia: ${score}%.`,
                score >= 60 ? 'success' : 'warning'
            );
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'No fue posible completar el análisis.', 'danger');
        } finally {
            button.disabled = false;
        }
    });
});
