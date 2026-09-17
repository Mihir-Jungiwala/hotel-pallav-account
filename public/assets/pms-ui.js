/* ===========================================================================
   Hotel Pallav — password tools, role-aware UI, themed date pickers & selects
   =========================================================================== */
(function () {
    'use strict';

    const body = document.body;
    const canWrite = body.dataset.canWrite !== '0';
    const canDelete = body.dataset.canDelete !== '0';

    /* -----------------------------------------------------------------------
       Password helpers
       ----------------------------------------------------------------------- */

    function wirePasswordTools() {
        document.addEventListener('click', (event) => {
            const reveal = event.target.closest('[data-reveal]');
            if (reveal) {
                const input = document.querySelector(reveal.dataset.reveal);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                reveal.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
                reveal.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                return;
            }

            const generate = event.target.closest('[data-generate-password]');
            if (generate) {
                const input = document.querySelector(generate.dataset.generatePassword);
                if (!input) return;
                const password = strongPassword();
                input.value = password;
                input.type = 'text';
                const confirm = input.form?.querySelector('[name="password_confirmation"]');
                if (confirm) confirm.value = password;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                navigator.clipboard?.writeText(password).then(
                    () => window.PMSToast?.push('success', 'Strong password generated and copied'),
                    () => {}
                );
            }
        });

        document.querySelectorAll('[data-strength]').forEach((input) => {
            const anchor = input.closest('.input-icon') || input;
            const meter = document.createElement('div');
            meter.className = 'strength';
            meter.innerHTML = '<span></span>';
            const label = document.createElement('div');
            label.className = 'strength-label';
            anchor.after(meter, label);

            const update = () => {
                const [score, text, color] = rate(input.value);
                meter.firstChild.style.width = (score * 25) + '%';
                meter.firstChild.style.background = color;
                label.textContent = input.value ? text : '';
            };
            input.addEventListener('input', update);
            update();
        });
    }

    function strongPassword() {
        const sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '@#$%&*!?'];
        const random = (max) => crypto.getRandomValues(new Uint32Array(1))[0] % max;
        const chars = sets.map((s) => s[random(s.length)]);
        const all = sets.join('');
        while (chars.length < 14) chars.push(all[random(all.length)]);
        for (let i = chars.length - 1; i > 0; i--) {
            const j = random(i + 1);
            [chars[i], chars[j]] = [chars[j], chars[i]];
        }
        return chars.join('');
    }

    function rate(value) {
        let score = 0;
        if (value.length >= 8) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;
        if (value.length < 8) score = Math.min(score, 1);
        return [
            [0, 'Too short', '#EF4444'],
            [1, 'Weak', '#EF4444'],
            [2, 'Fair', '#F59E0B'],
            [3, 'Good', '#8B5CF6'],
            [4, 'Strong', '#16A34A'],
        ][score];
    }

    /* -----------------------------------------------------------------------
       Role-aware UI — the server enforces the same rules; this just avoids
       offering actions that would be refused.
       ----------------------------------------------------------------------- */

    const SELF_SERVICE = /\/(logout|profile)(\/|$|\?)/;

    function isDeleteForm(form) {
        return form.querySelector('input[name="_method"]')?.value.toUpperCase() === 'DELETE';
    }

    function isWriteForm(form) {
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return false;
        if (form.hasAttribute('data-self-service')) return false;
        return !SELF_SERVICE.test(form.getAttribute('action') || '');
    }

    function hideExternalSubmitters(form) {
        if (!form.id) return;
        document.querySelectorAll(`[form="${CSS.escape(form.id)}"]`).forEach((btn) => { btn.hidden = true; });
    }

    function wireRoleGating() {
        if (canWrite && canDelete) return;

        document.querySelectorAll('form').forEach((form) => {
            if (!isWriteForm(form)) return;

            if (isDeleteForm(form)) {
                if (!canDelete) {
                    form.hidden = true;
                    hideExternalSubmitters(form);
                }
                return;
            }

            if (canWrite) return;

            // Status switches become a plain label rather than disappearing
            if (form.hasAttribute('data-status-toggle')) {
                const button = form.querySelector('button');
                const label = document.createElement('span');
                const active = button?.classList.contains('btn-outline-success');
                label.className = 'pill ' + (active ? 'pill-live' : 'pill-locked');
                label.innerHTML = (active ? '<span class="dot"></span> ' : '') + (button?.textContent.trim() || '');
                form.replaceWith(label);
                return;
            }

            form.dataset.readonly = 'true';
            form.querySelectorAll('input:not([type=hidden]), select, textarea').forEach((field) => { field.disabled = true; });
            form.querySelectorAll('button[type=submit], button:not([type]), input[type=submit]').forEach((btn) => { btn.hidden = true; });
            form.querySelectorAll('[data-generate-password]').forEach((btn) => { btn.hidden = true; });
            hideExternalSubmitters(form);

            // Wizards: let the viewer page through every step without a submit
            form.querySelectorAll('[data-wizard-submit]').forEach((btn) => { btn.hidden = true; });
        });

        if (!canWrite) {
            document.querySelectorAll('[data-open-record] .bi-pencil-square').forEach((icon) => {
                icon.className = 'bi bi-eye';
                icon.closest('[data-open-record]').title = 'View';
            });

            // "New …" buttons and anything explicitly marked as a write action
            document.querySelectorAll('[data-write-only], [data-bs-toggle="modal"]').forEach((trigger) => {
                const creates = trigger.hasAttribute('data-write-only') || trigger.querySelector('.bi-plus-lg, .bi-plus, .bi-plus-circle, .bi-person-plus, .bi-arrow-repeat');
                if (creates) trigger.hidden = true;
            });

            document.querySelectorAll('.modal-footer').forEach((footer) => {
                const modal = footer.closest('.modal');
                if (!modal?.querySelector('form[data-readonly]') || footer.querySelector('.read-only-note')) return;
                const note = document.createElement('span');
                note.className = 'read-only-note me-auto';
                note.innerHTML = '<i class="bi bi-eye"></i> View only';
                footer.prepend(note);
            });
        }
    }

    /* -----------------------------------------------------------------------
       Date pickers (flatpickr)
       ----------------------------------------------------------------------- */

    function footerFor(fp, isMonth) {
        const footer = document.createElement('div');
        footer.className = 'fp-footer';
        footer.innerHTML = `<button type="button" class="muted" data-act="clear">Clear</button>
                            <button type="button" data-act="today">${isMonth ? 'This month' : 'Today'}</button>`;
        footer.addEventListener('click', (e) => {
            const act = e.target.closest('button')?.dataset.act;
            if (act === 'clear') { fp.clear(); fp.close(); }
            if (act === 'today') { fp.setDate(new Date(), true); fp.close(); }
        });
        fp.calendarContainer.appendChild(footer);
    }

    function wireDatePickers(root = document) {
        if (typeof window.flatpickr !== 'function') return;

        const selector = 'input[type="date"]:not([data-native]), input[type="month"]:not([data-native]), input[type="datetime-local"]:not([data-native])';
        root.querySelectorAll(selector).forEach((input) => {
            if (input._flatpickr) return;
            const isMonth = input.type === 'month';
            const withTime = input.type === 'datetime-local';
            const required = input.required;

            const options = {
                altInput: true,
                allowInput: true,
                disableMobile: true,
                monthSelectorType: 'static',
                enableTime: withTime,
                time_24hr: true,
                dateFormat: isMonth ? 'Y-m' : (withTime ? 'Y-m-d\\TH:i' : 'Y-m-d'),
                altFormat: isMonth ? 'M Y' : (withTime ? 'd M Y, H:i' : 'd M Y'),
                minDate: input.min || null,
                maxDate: input.max || null,
                onReady(_, __, fp) {
                    const alt = fp.altInput;
                    alt.classList.add('flatpickr-alt');
                    alt.required = required;
                    alt.disabled = input.disabled;
                    alt.setAttribute('inputmode', 'none');
                    alt.setAttribute('autocomplete', 'off');
                    alt.dataset.noAutofocus = 'true';
                    if (input.getAttribute('aria-label')) alt.setAttribute('aria-label', input.getAttribute('aria-label'));
                    if (!input.id) return;
                    const label = document.querySelector(`label[for="${CSS.escape(input.id)}"]`);
                    if (label) { alt.id = input.id + '_alt'; label.htmlFor = alt.id; }
                },
                onChange(_, __, fp) {
                    fp.altInput.setCustomValidity('');
                },
            };

            if (isMonth) {
                if (typeof window.monthSelectPlugin !== 'function') return;
                options.plugins = [new window.monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'M Y' })];
            }

            const wrap = document.createElement('div');
            wrap.className = 'date-wrap' + (isMonth ? ' month' : '');
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            const fp = window.flatpickr(input, options);
            footerFor(fp, isMonth);

            // Keep min/max and disabled state in sync when other scripts change them
            new MutationObserver(() => {
                fp.set('minDate', input.min || null);
                fp.set('maxDate', input.max || null);
                fp.altInput.disabled = input.disabled;
                fp.altInput.required = input.required;
            }).observe(input, { attributes: true, attributeFilter: ['min', 'max', 'disabled', 'required'] });

            // Programmatic value changes (form.reset, prefill scripts)
            input.form?.addEventListener('reset', () => setTimeout(() => fp.setDate(input.defaultValue || null, false)));
            input.addEventListener('pms:sync', () => fp.setDate(input.value || null, false));
        });
    }

    /* -----------------------------------------------------------------------
       Selects (Tom Select)
       ----------------------------------------------------------------------- */

    function wireSelects(root = document) {
        if (typeof window.TomSelect !== 'function') return;

        root.querySelectorAll('select.form-select:not([data-native]):not(.tomselected):not([multiple])').forEach((select) => {
            if (select.closest('table, .attendance-grid, .company-switch')) return;

            const searchable = select.options.length > 8;

            const ts = new window.TomSelect(select, {
                create: false,
                allowEmptyOption: true,
                maxOptions: null,
                controlInput: searchable ? undefined : null,
                plugins: searchable ? ['dropdown_input'] : [],
                dropdownParent: 'body',
                render: {
                    no_results: () => '<div class="no-results">No matches</div>',
                },
            });

            if (select.classList.contains('form-select-sm')) ts.wrapper.classList.add('form-select-sm');
            if (select.disabled) ts.disable();

            new MutationObserver(() => {
                select.disabled ? ts.disable() : ts.enable();
            }).observe(select, { attributes: true, attributeFilter: ['disabled'] });

            select.form?.addEventListener('reset', () => setTimeout(() => ts.sync()));
            select.addEventListener('pms:sync', () => ts.sync());
        });
    }

    /* -----------------------------------------------------------------------
       Boot
       ----------------------------------------------------------------------- */

    function boot() {
        wirePasswordTools();
        wireRoleGating();
        wireDatePickers();
        wireSelects();
        window.PMS = Object.assign(window.PMS || {}, { enhance(root) { wireDatePickers(root); wireSelects(root); } });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
