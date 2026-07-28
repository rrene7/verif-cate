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

    const definitions = [
        { oldId: 'national_directorate', id: 'national_directorate_id', type: 'directorates', placeholder: 'Seleccione la Dirección Nacional' },
        { oldId: 'zone_name', id: 'zone_id', type: 'zones', placeholder: 'Seleccione la zona' },
        { oldId: 'area_name', id: 'area_id', type: 'areas', placeholder: 'Seleccione el área' },
        { oldId: 'service_name', id: 'service_id', type: 'services', placeholder: 'Seleccione el servicio o dependencia' }
    ];

    const selects = {};
    definitions.forEach(def => {
        const old = document.getElementById(def.oldId);
        if (!old) return;
        const select = document.createElement('select');
        select.id = def.id;
        select.name = def.id;
        select.required = old.required;
        select.innerHTML = `<option value="">${def.placeholder}</option>`;
        old.replaceWith(select);
        const label = document.querySelector(`label[for="${def.oldId}"]`);
        if (label) label.htmlFor = def.id;
        selects[def.id] = select;
    });

    async function loadOptions(select, type, parentId = '') {
        select.disabled = true;
        select.innerHTML = '<option value="">Cargando…</option>';
        const url = `api/locations.php?type=${encodeURIComponent(type)}${parentId ? `&parent_id=${encodeURIComponent(parentId)}` : ''}`;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('No se pudo cargar el catálogo');
            const rows = await response.json();
            const placeholder = definitions.find(item => item.id === select.id).placeholder;
            select.innerHTML = `<option value="">${placeholder}</option>` + rows.map(row => `<option value="${row.id}">${row.name}</option>`).join('');
            select.disabled = false;
        } catch (error) {
            select.innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    const directorate = selects.national_directorate_id;
    const zone = selects.zone_id;
    const area = selects.area_id;
    const service = selects.service_id;
    if (directorate) {
        loadOptions(directorate, 'directorates');
        directorate.addEventListener('change', () => {
            zone.innerHTML = '<option value="">Seleccione la zona</option>';
            area.innerHTML = '<option value="">Seleccione el área</option>';
            service.innerHTML = '<option value="">Seleccione el servicio o dependencia</option>';
            if (directorate.value) loadOptions(zone, 'zones', directorate.value);
        });
        zone.addEventListener('change', () => {
            area.innerHTML = '<option value="">Seleccione el área</option>';
            service.innerHTML = '<option value="">Seleccione el servicio o dependencia</option>';
            if (zone.value) loadOptions(area, 'areas', zone.value);
        });
        area.addEventListener('change', () => {
            service.innerHTML = '<option value="">Seleccione el servicio o dependencia</option>';
            if (area.value) loadOptions(service, 'services', area.value);
        });
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
