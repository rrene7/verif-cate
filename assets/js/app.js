(() => {
    const form = document.getElementById('validationForm');
    if (!form) return;

    const condition = document.getElementById('card_condition');
    const evidenceFields = ['card_front', 'card_back', 'person_with_card'].map(id => document.getElementById(id));
    const noCardConditions = new Set(['EXTRAVIADO_REPORTADO', 'EXTRAVIADO_NO_REPORTADO', 'ROBADO', 'NO_RECIBIDO']);

    function updateEvidenceRequirement() {
        const required = !noCardConditions.has(condition.value);
        evidenceFields.forEach(input => { if (input) input.required = required; });
    }
    condition.addEventListener('change', updateEvidenceRequirement);
    updateEvidenceRequirement();

    [['card_front', 'preview_front'], ['card_back', 'preview_back'], ['person_with_card', 'preview_person']]
        .forEach(([inputId, previewId]) => {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            if (!input || !preview) return;
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) { preview.style.display = 'none'; return; }
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    alert('Seleccione una imagen JPG, PNG o WEBP.'); input.value = ''; return;
                }
                if (file.size > 8 * 1024 * 1024) {
                    alert('La imagen no puede superar 8 MB.'); input.value = ''; return;
                }
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
            });
        });

    const firstLocationInput = document.getElementById('national_directorate');
    const locationGrid = firstLocationInput ? firstLocationInput.closest('.form-grid') : null;

    if (locationGrid) {
        locationGrid.innerHTML = `
            <div class="field half">
                <label class="required" for="institutional_unit_id">Unidad institucional</label>
                <select id="institutional_unit_id" name="institutional_unit_id" required>
                    <option value="">Cargando unidades…</option>
                </select>
                <span class="help">Seleccione una de las 21 direcciones, 18 zonas policiales o 10 servicios oficiales.</span>
            </div>
            <div class="field half">
                <label class="required" for="exact_work_location">Ubicación exacta de trabajo</label>
                <input id="exact_work_location" name="exact_work_location" maxlength="255" required placeholder="Ejemplo: Edificio 1000, piso 2, oficina de soporte">
                <span class="help">Indique edificio, sede, departamento, oficina, estación o lugar específico donde presta servicio.</span>
            </div>`;

        const unitSelect = document.getElementById('institutional_unit_id');
        fetch('api/locations.php', { headers: { Accept: 'application/json' } })
            .then(response => {
                if (!response.ok) throw new Error('No se pudo cargar el catálogo');
                return response.json();
            })
            .then(rows => {
                const labels = { DIRECCION: 'Direcciones', ZONA: 'Zonas policiales', SERVICIO: 'Servicios policiales' };
                const groups = {};
                rows.forEach(row => {
                    if (!groups[row.category]) groups[row.category] = [];
                    groups[row.category].push(row);
                });
                unitSelect.innerHTML = '<option value="">Seleccione su unidad institucional</option>';
                ['DIRECCION', 'ZONA', 'SERVICIO'].forEach(category => {
                    if (!groups[category]) return;
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = labels[category];
                    groups[category].forEach(row => {
                        const option = document.createElement('option');
                        option.value = row.id;
                        option.textContent = `${row.code} — ${row.name}`;
                        optgroup.appendChild(option);
                    });
                    unitSelect.appendChild(optgroup);
                });
            })
            .catch(() => {
                unitSelect.innerHTML = '<option value="">Error al cargar las unidades</option>';
            });
    }

    const cardCondition = document.getElementById('card_condition');
    const cardGrid = cardCondition ? cardCondition.closest('.form-grid') : null;
    if (cardGrid && !document.getElementById('card_expiration_date')) {
        const expirationField = document.createElement('div');
        expirationField.className = 'field half';
        expirationField.innerHTML = `
            <label class="required" for="card_expiration_date">Fecha de expiración del carné</label>
            <input type="date" id="card_expiration_date" name="card_expiration_date" required>
            <span class="help">Indique la fecha de vencimiento impresa en el carné.</span>`;
        cardGrid.insertBefore(expirationField, cardGrid.children[1] || null);
    }

    form.addEventListener('submit', event => {
        updateEvidenceRequirement();
        if (!form.checkValidity()) {
            event.preventDefault(); form.reportValidity(); return;
        }
        const submit = form.querySelector('button[type="submit"]');
        if (submit) { submit.disabled = true; submit.textContent = 'Enviando solicitud…'; }
    });
})();