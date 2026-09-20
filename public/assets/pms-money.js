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

    // Whole rupees in words, without the currency
    function rupeeWords(number) {
        let n = number;
        const crore = Math.floor(n / 10000000); n %= 10000000;
        const lakh = Math.floor(n / 100000); n %= 100000;
        const thousand = Math.floor(n / 1000); n %= 1000;
        const hundred = Math.floor(n / 100);
        const rest = n % 100;

        const parts = [];
        if (crore) parts.push(rupeeWords(crore) + ' Crore');
        if (lakh) parts.push(twoDigits(lakh) + ' Lakh');
        if (thousand) parts.push(twoDigits(thousand) + ' Thousand');
        if (hundred) parts.push(ONES[hundred] + ' Hundred');
        if (rest) parts.push(twoDigits(rest));
        return parts.join(' ').trim();
    }

    // Mirrors the server's NumberToWords: 12500.5 -> "Twelve Thousand Five Hundred Rupees and Fifty Paise Only"
    function toWords(amount) {
        const paiseTotal = Math.round((Number(amount) || 0) * 100);
        if (paiseTotal === 0) return 'Zero Rupees Only';
        if (paiseTotal < 0 || paiseTotal > 100000000000) return String(amount);

        const rupees = Math.floor(paiseTotal / 100);
        const paise = paiseTotal % 100;

        let words = rupees > 0 ? rupeeWords(rupees) + (rupees === 1 ? ' Rupee' : ' Rupees') : '';
        if (paise > 0) words += (words ? ' and ' : '') + twoDigits(paise) + ' Paise';
        return words + ' Only';
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
