document.addEventListener('DOMContentLoaded', () => {
    const unifiedButton = document.getElementById('runUnifiedValidation');

    if (!unifiedButton) {
        return;
    }

    const visionButton = document.getElementById('runVisionOcr');
    const zoneButton = document.getElementById('runZoneOcr');
    const generalButton = document.getElementById('runOcrValidation');
    const status = document.getElementById('unifiedValidationStatus');

    const waitUntil = async (condition, timeoutMs = 120000, intervalMs = 250) => {
        const started = Date.now();

        while (Date.now() - started < timeoutMs) {
            if (condition()) {
                return true;
            }

            await new Promise((resolve) => setTimeout(resolve, intervalMs));
        }

        return false;
    };

    const setStatus = (message, type = '') => {
        if (!status) return;

        status.textContent = message;
        status.className = type
            ? `ocr-status ${type}`
            : 'ocr-status';
    };

    unifiedButton.addEventListener('click', async () => {
        if (!visionButton || !zoneButton) {
            setStatus(
                'No se encontraron los módulos de recorte y lectura por zonas.',
                'danger'
            );
            return;
        }

        unifiedButton.disabled = true;

        try {
            setStatus('Paso 1 de 2: detectando y recortando el carné...');
            visionButton.click();

            const visionCompleted = await waitUntil(() => {
                const processed = window.VerifCateProcessed || {};
                const finished =
                    Boolean(processed.front?.croppedUrl)
                    && Boolean(processed.back?.croppedUrl);

                const visionStatus = document.getElementById('visionStatus');
                const failed =
                    visionStatus?.classList.contains('danger') === true;

                return finished || failed;
            });

            if (!visionCompleted) {
                throw new Error(
                    'El recorte demoró demasiado. Recargue la página e intente nuevamente.'
                );
            }

            const processed = window.VerifCateProcessed || {};

            if (!processed.front?.croppedUrl || !processed.back?.croppedUrl) {
                const visionMessage =
                    document.getElementById('visionStatus')?.textContent
                    || 'No se pudieron obtener ambos recortes.';

                throw new Error(visionMessage);
            }

            setStatus('Paso 2 de 2: leyendo las zonas del carné...');
            zoneButton.click();

            const zoneCompleted = await waitUntil(() => {
                const zoneStatus = document.getElementById('zoneOcrStatus');

                return zoneStatus?.classList.contains('success')
                    || zoneStatus?.classList.contains('warning')
                    || zoneStatus?.classList.contains('danger');
            });

            if (!zoneCompleted) {
                throw new Error(
                    'La lectura por zonas demoró demasiado.'
                );
            }

            const zoneStatus = document.getElementById('zoneOcrStatus');

            if (zoneStatus?.classList.contains('danger')) {
                throw new Error(zoneStatus.textContent || 'Falló la lectura por zonas.');
            }

            setStatus(
                zoneStatus?.textContent || 'Validación asistida completada.',
                zoneStatus?.classList.contains('success') ? 'success' : 'warning'
            );
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'No fue posible completar la validación.', 'danger');
        } finally {
            unifiedButton.disabled = false;
        }
    });

    /*
     * Oculta el OCR general anterior para evitar que el revisor presione
     * accidentalmente el botón equivocado. Se conserva en el código como
     * respaldo técnico.
     */
    if (generalButton) {
        const generalSection = generalButton.closest('.ocr-panel');
        if (generalSection) {
            generalSection.classList.add('legacy-ocr-hidden');
        } else {
            generalButton.hidden = true;
        }
    }

    if (visionButton) {
        visionButton.closest('.vision-tools')?.classList.add('technical-step-hidden');
    }

    if (zoneButton) {
        zoneButton.closest('.zone-ocr-panel')?.classList.add('technical-step-hidden');
    }
});
