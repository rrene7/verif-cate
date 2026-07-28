(() => {
    const form = document.getElementById('validationForm');
    if (!form) return;

    const condition = document.getElementById('card_condition');
    const evidenceFields = [
        document.getElementById('card_front'),
        document.getElementById('card_back'),
        document.getElementById('person_with_card')
    ];

    const noCardConditions = new Set(['EXTRAVIADO_REPORTADO', 'EXTRAVIADO_NO_REPORTADO', 'ROBADO', 'NO_RECIBIDO']);

    function updateEvidenceRequirement() {
        const required = !noCardConditions.has(condition.value);
        evidenceFields.forEach(input => {
            if (input) input.required = required;
        });
    }

    condition.addEventListener('change', updateEvidenceRequirement);
    updateEvidenceRequirement();

    const previewPairs = [
        ['card_front', 'preview_front'],
        ['card_back', 'preview_back'],
        ['person_with_card', 'preview_person']
    ];

    previewPairs.forEach(([inputId, previewId]) => {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!input || !preview) return;

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) {
                preview.style.display = 'none';
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                alert('Seleccione una imagen JPG, PNG o WEBP.');
                input.value = '';
                return;
            }
            if (file.size > 8 * 1024 * 1024) {
                alert('La imagen no puede superar 8 MB.');
                input.value = '';
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        });
    });

    form.addEventListener('submit', event => {
        updateEvidenceRequirement();
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }
        const submit = form.querySelector('button[type="submit"]');
        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Enviando solicitud…';
        }
    });
})();
