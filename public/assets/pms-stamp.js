/* One "Date and time" field on screen, two values sent (date and time), so
   every controller keeps reading `date` and `time` as before.

   Other scripts set the stamp with PMSStamp.set(field, 'Y-m-d', 'H:i') and
   hear about changes through a `pms:stamp` event on the field. */
(function () {
    const pad = (n) => String(n).padStart(2, '0');

    function parts(field) {
        return {
            when: field.querySelector('[data-stamp-when]'),
            date: field.querySelector('[data-stamp-date]'),
            time: field.querySelector('[data-stamp-time]'),
            text: field.querySelector('[data-entry-stamp-text]'),
        };
    }

    function announce(field) {
        const { date, time } = parts(field);
        field.dispatchEvent(new CustomEvent('pms:stamp', { bubbles: true, detail: { date: date.value, time: time.value } }));
    }

    /** Copy the visible field into the two values the form sends. */
    function split(field) {
        const { when, date, time } = parts(field);
        if (!when || !when.value) return;
        const [d, t] = when.value.split('T');
        date.value = d;
        time.value = (t || '00:00').slice(0, 5);
        announce(field);
    }

    function set(field, dateValue, timeValue) {
        const { when, date, time, text } = parts(field);
        const now = new Date();
        const today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        const nowMax = today + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());

        date.value = dateValue || today;
        time.value = (timeValue || pad(now.getHours()) + ':' + pad(now.getMinutes())).slice(0, 5);

        if (when) {
            let value = date.value + 'T' + time.value;
            // Never further ahead than "now", which is what the picker allows
            if (value > nowMax) {
                value = nowMax;
                date.value = today;
                time.value = nowMax.slice(11);
            }

            // The picker was built when the page loaded, so its "latest allowed"
            // is that moment. Refresh it first; otherwise the current time falls
            // just past the limit and the picker leaves the field empty.
            const picker = when._flatpickr;
            when.max = nowMax;
            if (picker) picker.set('maxDate', nowMax);

            when.value = value;
            if (picker) picker.setDate(value, false);
            else when.dispatchEvent(new Event('pms:sync'));
        }
        if (text) {
            const [y, m, day] = date.value.split('-').map(Number);
            const shown = y ? new Date(y, m - 1, day) : new Date();
            text.textContent = shown.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' + time.value;
        }
        announce(field);
    }

    document.addEventListener('change', (event) => {
        const field = event.target.closest?.('[data-entry-stamp-field]');
        if (field && event.target.matches('[data-stamp-when]')) split(field);
    });
    document.addEventListener('input', (event) => {
        const field = event.target.closest?.('[data-entry-stamp-field]');
        if (field && event.target.matches('[data-stamp-when]')) split(field);
    });

    window.PMSStamp = { set, split };
})();
