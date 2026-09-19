/* Amounts written out in words as they are typed, in the same Indian
   numbering App\Support\NumberToWords puts on the receipt. */
(function () {
    const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function twoDigits(n) {
        if (n < 20) return ONES[n];
        return (TENS[Math.floor(n / 10)] + ' ' + ONES[n % 10]).trim();
    }

    function toWords(amount) {
        let n = Math.round(Number(amount) || 0);
        if (n === 0) return 'Zero Rupees Only';
        if (n < 0 || n > 1000000000) return String(n);

        const crore = Math.floor(n / 10000000); n %= 10000000;
        const lakh = Math.floor(n / 100000); n %= 100000;
        const thousand = Math.floor(n / 1000); n %= 1000;
        const hundred = Math.floor(n / 100);
        const rest = n % 100;

        const parts = [];
        if (crore) parts.push(twoDigits(crore) + ' Crore');
        if (lakh) parts.push(twoDigits(lakh) + ' Lakh');
        if (thousand) parts.push(twoDigits(thousand) + ' Thousand');
        if (hundred) parts.push(ONES[hundred] + ' Hundred');
        if (rest) parts.push(twoDigits(rest));

        return parts.join(' ').trim() + ' Rupees Only';
    }

    window.PMSMoney = { toWords };

    function refresh(field) {
        const input = field.querySelector('input');
        field.querySelector('[data-amount-words]').textContent = toWords(input.value);
    }

    document.addEventListener('input', (event) => {
        const field = event.target.closest?.('[data-amount-field]');
        if (field) refresh(field);
    });
    document.addEventListener('shown.bs.modal', (event) => event.target.querySelectorAll('[data-amount-field]').forEach(refresh));

    const all = () => document.querySelectorAll('[data-amount-field]').forEach(refresh);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', all);
    else all();
})();
