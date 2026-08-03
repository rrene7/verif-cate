document.addEventListener('DOMContentLoaded', () => {
    const originalButton = document.getElementById('detectBarcode');

    if (!originalButton) {
        return;
    }

    /*
     * Sustituye el botón para eliminar el manejador anterior basado únicamente
     * en BarcodeDetector. Así se evita que aparezcan dos mensajes distintos.
     */
    const detectButton = originalButton.cloneNode(true);
    originalButton.replaceWith(detectButton);

    const resultElement = document.getElementById('barcodeDetectionResult');
    const detectedInput = document.getElementById('barcode_detected_value');
    const verifiedCheckbox = document.getElementById('barcode_verified');

    const normalizeCode = (value) =>
        String(value ?? '')
            .replace(/\s+/g, '')
            .replace(/[^0-9A-Za-z]/g, '')
            .toUpperCase();

    const showResult = (message, type = '') => {
        if (!resultElement) {
            return;
        }

        resultElement.textContent = message;
        resultElement.className = type ? `detection ${type}` : 'detection';
    };

    const evaluateCode = (detectedValue, expectedValue) => {
        const detected = normalizeCode(detectedValue);
        const expected = normalizeCode(expectedValue);

        if (detectedInput) {
            detectedInput.value = detected;
        }

        if (detected !== '' && expected !== '' && detected === expected) {
            showResult(`Coincidencia confirmada: ${detected}`, 'success');

            if (verifiedCheckbox) {
                verifiedCheckbox.checked = true;
                verifiedCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
            }

            return;
        }

        showResult(
            `Código detectado: ${detected || 'ninguno'}. No coincide con el registrado: ${expected || 'vacío'}.`,
            'danger'
        );

        if (verifiedCheckbox) {
            verifiedCheckbox.checked = false;
            verifiedCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const detectWithNativeApi = async (imageUrl) => {
        if (!window.isSecureContext || !('BarcodeDetector' in window)) {
            return null;
        }

        const supportedFormats = await BarcodeDetector.getSupportedFormats();
        const preferredFormats = [
            'code_128',
            'code_39',
            'ean_13',
            'ean_8',
            'upc_a',
            'upc_e',
            'itf',
            'codabar'
        ].filter((format) => supportedFormats.includes(format));

        const detector = new BarcodeDetector(
            preferredFormats.length ? { formats: preferredFormats } : undefined
        );

        const response = await fetch(imageUrl, { cache: 'no-store' });

        if (!response.ok) {
            throw new Error(`No se pudo abrir la imagen: HTTP ${response.status}`);
        }

        const blob = await response.blob();
        const bitmap = await createImageBitmap(blob);
        const detections = await detector.detect(bitmap);

        return detections[0]?.rawValue ?? '';
    };

    const detectWithZxing = async (imageUrl) => {
        if (
            !window.ZXingBrowser ||
            typeof window.ZXingBrowser.BrowserMultiFormatReader !== 'function'
        ) {
            throw new Error('El lector alternativo ZXing no está disponible.');
        }

        const reader = new window.ZXingBrowser.BrowserMultiFormatReader();
        const decoded = await reader.decodeFromImageUrl(imageUrl);

        if (!decoded) {
            return '';
        }

        if (typeof decoded.getText === 'function') {
            return decoded.getText();
        }

        return decoded.text ?? String(decoded);
    };

    detectButton.addEventListener('click', async () => {
        const imageUrl = detectButton.dataset.image ?? '';
        const expectedValue = detectButton.dataset.expected ?? '';

        if (!imageUrl) {
            showResult('No existe una fotografía del reverso para analizar.', 'danger');
            return;
        }

        detectButton.disabled = true;
        showResult('Analizando la fotografía del reverso...');

        try {
            let detectedValue = null;

            try {
                detectedValue = await detectWithNativeApi(imageUrl);
            } catch (nativeError) {
                console.warn('Falló BarcodeDetector; se utilizará ZXing.', nativeError);
            }

            if (!detectedValue) {
                detectedValue = await detectWithZxing(imageUrl);
            }

            if (!detectedValue) {
                showResult(
                    'No se pudo leer el código. Amplíe la imagen, verifique que esté enfocada y compare visualmente.',
                    'warning'
                );
                return;
            }

            evaluateCode(detectedValue, expectedValue);
        } catch (error) {
            console.error(error);
            showResult(
                'No fue posible leer el código automáticamente. La foto puede estar inclinada, borrosa, recortada o con reflejo.',
                'warning'
            );
        } finally {
            detectButton.disabled = false;
        }
    });
});
