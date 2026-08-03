document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tab-button').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.tab-button').forEach((item) => item.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach((panel) => panel.classList.remove('active'));

            button.classList.add('active');
            const panel = document.getElementById(`tab-${button.dataset.tab}`);
            if (panel) panel.classList.add('active');
        });
    });

    document.querySelectorAll('.copy-button').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copy || '';
            if (!value) return;

            try {
                await navigator.clipboard.writeText(value);
                const original = button.textContent;
                button.textContent = 'Copiado';
                setTimeout(() => { button.textContent = original; }, 1400);
            } catch {
                window.prompt('Copie el código:', value);
            }
        });
    });

    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalLabel = document.getElementById('modalLabel');
    const modalClose = document.getElementById('modalClose');

    document.querySelectorAll('.image-open').forEach((button) => {
        button.addEventListener('click', () => {
            if (!modal || !modalImage) return;
            modalImage.src = button.dataset.image || '';
            modalImage.alt = button.dataset.label || '';
            if (modalLabel) modalLabel.textContent = button.dataset.label || '';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    const closeModal = () => {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    };

    modalClose?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    const detectButton = document.getElementById('detectBarcode');
    const result = document.getElementById('barcodeDetectionResult');
    const detectedInput = document.getElementById('barcode_detected_value');
    const verifiedCheckbox = document.getElementById('barcode_verified');

    detectButton?.addEventListener('click', async () => {
        const imageUrl = detectButton.dataset.image || '';
        const expected = (detectButton.dataset.expected || '').replace(/\s+/g, '');

        if (!imageUrl) {
            result.textContent = 'No existe fotografía del reverso.';
            result.className = 'detection danger';
            return;
        }

        if (!('BarcodeDetector' in window)) {
            result.textContent = 'Este navegador no permite lectura automática. Compare visualmente el código y marque la casilla.';
            result.className = 'detection warning';
            return;
        }

        detectButton.disabled = true;
        result.textContent = 'Analizando fotografía...';
        result.className = 'detection';

        try {
            const response = await fetch(imageUrl, { cache: 'no-store' });
            const blob = await response.blob();
            const bitmap = await createImageBitmap(blob);
            const detector = new BarcodeDetector({
                formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'codabar']
            });
            const codes = await detector.detect(bitmap);

            if (!codes.length) {
                result.textContent = 'No se pudo leer automáticamente. Amplíe la foto y valide visualmente.';
                result.className = 'detection warning';
                return;
            }

            const detected = String(codes[0].rawValue || '').replace(/\s+/g, '');
            if (detectedInput) detectedInput.value = detected;

            if (detected === expected && expected !== '') {
                result.textContent = `Coincidencia confirmada: ${detected}`;
                result.className = 'detection success';
                if (verifiedCheckbox) verifiedCheckbox.checked = true;
            } else {
                result.textContent = `Código detectado: ${detected}. No coincide con el registrado: ${expected || 'vacío'}.`;
                result.className = 'detection danger';
                if (verifiedCheckbox) verifiedCheckbox.checked = false;
            }
        } catch (error) {
            console.error(error);
            result.textContent = 'No fue posible analizar la fotografía. Realice la validación visual.';
            result.className = 'detection warning';
        } finally {
            detectButton.disabled = false;
        }
    });
});
