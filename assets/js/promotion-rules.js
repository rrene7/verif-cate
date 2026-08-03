document.addEventListener('DOMContentLoaded', () => {
    const rank = document.querySelector('[name="rank_name"]');
    const type = document.querySelector('[name="promotion_type"]');
    const number = document.querySelector('[name="promotion_number"]');

    if (!rank || !type || !number) return;

    const typeBox = type.closest('.field') || type.parentElement;
    const numberBox = number.closest('.field') || number.parentElement;

    const normalize = (value) =>
        String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toUpperCase();

    const applyRule = () => {
        const exempt = ['DIRECTOR', 'MNJ'].includes(normalize(rank.value));

        [typeBox, numberBox].forEach((box) => {
            if (box) box.hidden = exempt;
        });

        type.required = !exempt;
        number.required = !exempt;
        type.disabled = exempt;
        number.disabled = exempt;

        if (exempt) {
            type.value = '';
            number.value = '';
        }
    };

    rank.addEventListener('change', applyRule);
    applyRule();
});
