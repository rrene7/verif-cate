document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-v3-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-v3-tab]').forEach((item) => item.classList.remove('active'));
            document.querySelectorAll('.v3-tab-panel').forEach((panel) => panel.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(`v3-tab-${button.dataset.v3Tab}`)?.classList.add('active');
        });
    });

    const modal = document.getElementById('v3Modal');
    const modalImage = document.getElementById('v3ModalImage');
    const modalTitle = document.getElementById('v3ModalTitle');

    document.querySelectorAll('[data-v3-image]').forEach((button) => {
        button.addEventListener('click', () => {
            modalImage.src = button.dataset.v3Image || '';
            modalTitle.textContent = button.dataset.v3Label || '';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    document.getElementById('v3ModalClose')?.addEventListener('click', () => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    });

    const form = document.getElementById('v3ValidationForm');

    if (!form) {
        return;
    }

    const checks = [...form.querySelectorAll('[data-v3-check]')];
    const score = document.getElementById('v3Score');
    const bar = document.getElementById('v3ScoreBar');
    const risk = document.getElementById('v3Risk');
    const status = document.getElementById('v3DecisionStatus');
    const observation = document.getElementById('v3Observation');
    const decisions = [...form.querySelectorAll('[data-v3-decision]')];

    const updateScore = () => {
        const value = checks.length
            ? Math.round((checks.filter((item) => item.checked).length / checks.length) * 100)
            : 0;

        score.textContent = `${value}%`;
        bar.style.width = `${value}%`;
        risk.className = '';

        if (value >= 85) {
            risk.textContent = 'RIESGO BAJO';
            risk.classList.add('risk-low');
        } else if (value >= 55) {
            risk.textContent = 'RIESGO MEDIO';
            risk.classList.add('risk-medium');
        } else {
            risk.textContent = 'RIESGO ALTO';
            risk.classList.add('risk-high');
        }
    };

    checks.forEach((item) => item.addEventListener('change', updateScore));

    decisions.forEach((button) => {
        button.addEventListener('click', () => {
            status.value = button.dataset.v3Decision || '';
            decisions.forEach((item) => item.classList.remove('selected'));
            button.classList.add('selected');
        });
    });

    form.addEventListener('submit', (event) => {
        if (!status.value) {
            event.preventDefault();
            alert('Seleccione Validar, Observar o Rechazar.');
            return;
        }

        if (
            ['EN_REVISION', 'RECHAZADA'].includes(status.value)
            && !observation.value.trim()
        ) {
            event.preventDefault();
            alert('Debe escribir una observación para Observar o Rechazar.');
        }
    });

    updateScore();
});
