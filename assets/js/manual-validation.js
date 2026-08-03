document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('manualValidationForm');

    if (!form) {
        return;
    }

    const checks = Array.from(
        form.querySelectorAll('input[data-manual-check]')
    );

    const scoreNumber = document.getElementById('manualScoreNumber');
    const scoreBar = document.getElementById('manualScoreBar');
    const riskLabel = document.getElementById('manualRiskLabel');
    const statusInput = form.querySelector('input[name="status"]');
    const decisionButtons = Array.from(
        form.querySelectorAll('[data-decision]')
    );

    const updateScore = () => {
        const checked = checks.filter((item) => item.checked).length;
        const score = checks.length
            ? Math.round((checked / checks.length) * 100)
            : 0;

        if (scoreNumber) {
            scoreNumber.textContent = `${score}%`;
        }

        if (scoreBar) {
            scoreBar.style.width = `${score}%`;
        }

        if (riskLabel) {
            riskLabel.classList.remove('risk-low', 'risk-medium', 'risk-high');

            if (score >= 85) {
                riskLabel.textContent = 'RIESGO BAJO';
                riskLabel.classList.add('risk-low');
            } else if (score >= 55) {
                riskLabel.textContent = 'RIESGO MEDIO';
                riskLabel.classList.add('risk-medium');
            } else {
                riskLabel.textContent = 'RIESGO ALTO';
                riskLabel.classList.add('risk-high');
            }
        }
    };

    checks.forEach((item) => {
        item.addEventListener('change', updateScore);
    });

    decisionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const decision = button.dataset.decision || '';

            if (statusInput) {
                statusInput.value = decision;
            }

            decisionButtons.forEach((item) => {
                item.classList.remove('selected');
            });

            button.classList.add('selected');
        });
    });

    document.querySelectorAll('[data-copy-value]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copyValue || '';

            try {
                await navigator.clipboard.writeText(value);
                const original = button.textContent;
                button.textContent = 'Copiado';
                setTimeout(() => {
                    button.textContent = original;
                }, 1200);
            } catch {
                window.prompt('Copie el valor:', value);
            }
        });
    });

    form.addEventListener('submit', (event) => {
        if (!statusInput?.value) {
            event.preventDefault();
            alert('Seleccione Validar, Observar o Rechazar.');
        }
    });

    updateScore();
});
