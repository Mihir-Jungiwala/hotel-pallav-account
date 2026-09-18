/* ===========================================================================
   Hotel Pallav - password tools, role-aware UI, themed date pickers & selects
   =========================================================================== */
(function () {
    'use strict';

    const body = document.body;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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
       Role-aware UI - the server enforces the same rules; this just avoids
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

    /* -----------------------------------------------------------------------
       Business switcher
       ----------------------------------------------------------------------- */

    function wireUnitSwitch() {
        const root = document.getElementById('unitSwitch');
        if (!root) return;

        const button = root.querySelector('.unit-switch-btn');
        const close = () => { root.classList.remove('open'); button.setAttribute('aria-expanded', 'false'); };

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = root.classList.toggle('open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) close();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
        });
    }

    /* -----------------------------------------------------------------------
       Force mode - the browser must stop refusing input too, or the server
       never sees what the SuperAdmin is trying to save.
       ----------------------------------------------------------------------- */

    function wireForceMode() {
        if (body.dataset.forceMode !== '1') return;

        const relax = (root) => {
            root.querySelectorAll('form:not([data-self-service]) :is(input, select, textarea)').forEach((field) => {
                ['required', 'min', 'max', 'step', 'pattern', 'maxlength', 'minlength'].forEach((attr) => {
                    if (field.hasAttribute(attr)) {
                        field.dataset['kept' + attr] = field.getAttribute(attr);
                        field.removeAttribute(attr);
                    }
                });
                field.setCustomValidity('');
            });

            root.querySelectorAll('form:not([data-self-service])').forEach((form) => {
                form.setAttribute('novalidate', 'novalidate');
            });
        };

        relax(document);

        // Modals and rows rendered later get the same treatment
        document.addEventListener('show.bs.modal', (event) => relax(event.target));
    }

    /* -----------------------------------------------------------------------
       Night mode. The choice is remembered per browser; the very first visit
       follows the operating system.
       ----------------------------------------------------------------------- */

    function wireTheme() {
        const root = document.documentElement;
        const button = document.getElementById('themeToggle');

        const paint = (theme) => {
            root.dataset.theme = theme;
            if (button) {
                button.innerHTML = theme === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
                button.title = theme === 'dark' ? 'Switch to day mode' : 'Switch to night mode';
                button.setAttribute('aria-label', button.title);
            }
            document.dispatchEvent(new CustomEvent('pms:theme', { detail: { theme } }));
        };

        paint(root.dataset.theme || 'light');

        button?.addEventListener('click', () => {
            const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('pms-theme', next); } catch (e) { /* private window */ }
            paint(next);
        });
    }

    /* -----------------------------------------------------------------------
       Dashboard trend chart
       ----------------------------------------------------------------------- */

    function wireCharts() {
        const canvas = document.getElementById('trendChart');
        if (!canvas || typeof window.Chart !== 'function') return;

        const read = (name) => JSON.parse(canvas.dataset[name] || '[]');
        const css = (token) => getComputedStyle(document.documentElement).getPropertyValue(token).trim();

        const fill = (ctx, from, to) => {
            const gradient = ctx.createLinearGradient(0, 0, 0, 190);
            gradient.addColorStop(0, from);
            gradient.addColorStop(1, to);
            return gradient;
        };

        const context = canvas.getContext('2d');

        const chart = new window.Chart(context, {
            type: 'line',
            data: {
                labels: read('labels'),
                datasets: [
                    {
                        label: 'In', data: read('income'), tension: .38, borderWidth: 2.5,
                        borderColor: '#10B981', backgroundColor: fill(context, 'rgba(16,185,129,.28)', 'rgba(16,185,129,0)'),
                        fill: true, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#10B981',
                    },
                    {
                        label: 'Out', data: read('expense'), tension: .38, borderWidth: 2.5,
                        borderColor: '#F43F5E', backgroundColor: fill(context, 'rgba(244,63,94,.22)', 'rgba(244,63,94,0)'),
                        fill: true, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#F43F5E',
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: reduceMotion ? false : { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1B1235', padding: 10, cornerRadius: 10, displayColors: true,
                        callbacks: {
                            label: (item) => ' ' + item.dataset.label + ': Rs ' + Number(item.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }),
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: css('--muted'), font: { size: 10 } } },
                    y: {
                        grid: { color: css('--line') }, border: { display: false },
                        ticks: {
                            color: css('--muted'), font: { size: 10 },
                            callback: (value) => value >= 1000 ? (value / 1000) + 'k' : value,
                        },
                    },
                },
            },
        });

        // Repaint the axes when the theme changes
        document.addEventListener('pms:theme', () => {
            chart.options.scales.x.ticks.color = css('--muted');
            chart.options.scales.y.ticks.color = css('--muted');
            chart.options.scales.y.grid.color = css('--line');
            chart.update('none');
        });
    }

    /* -----------------------------------------------------------------------
       Sign-in code: digits only, submits itself once six are in, and the
       resend button counts itself down.
       ----------------------------------------------------------------------- */

    function wireOtp() {
        document.querySelectorAll('[data-otp]').forEach((input) => {
            input.addEventListener('input', () => {
                const digits = input.value.replace(/\D/g, '').slice(0, 6);
                if (digits !== input.value) input.value = digits;

                if (digits.length === 6) input.form?.requestSubmit();
            });

            input.addEventListener('paste', (event) => {
                const text = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                if (!text) return;
                event.preventDefault();
                input.value = text;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });

        const resend = document.querySelector('[data-resend-at]');
        if (!resend) return;

        let left = parseInt(resend.dataset.resendAt, 10) || 0;
        const label = resend.querySelector('[data-resend-label]');

        const tick = () => {
            if (left <= 0) {
                resend.disabled = false;
                if (label) label.textContent = 'Send another code';
                return;
            }

            resend.disabled = true;
            if (label) label.textContent = 'Send another code in ' + left + 's';
            left -= 1;
            setTimeout(tick, 1000);
        };

        tick();
    }

    /* -----------------------------------------------------------------------
       The rail has no scrollbar, so a soft fade at its foot says there is
       more menu below, and it disappears once you reach the end.
       ----------------------------------------------------------------------- */

    function wireRailScroll() {
        const nav = document.querySelector('.sidebar-nav');
        if (!nav) return;

        const sync = () => {
            const scrollable = nav.scrollHeight > nav.clientHeight + 2;
            const atEnd = nav.scrollTop + nav.clientHeight >= nav.scrollHeight - 4;
            body.classList.toggle('rail-scrollable', scrollable);
            body.classList.toggle('rail-at-end', atEnd);
        };

        nav.addEventListener('scroll', sync, { passive: true });
        window.addEventListener('resize', sync);
        new ResizeObserver(sync).observe(nav);
        sync();

        // Keep the current page in view when the menu is long
        nav.querySelector('a.active')?.scrollIntoView({ block: 'nearest' });
    }

    function boot() {
        wireTheme();
        wireRailScroll();
        wireOtp();
        wireCharts();
        wirePasswordTools();
        wireUnitSwitch();
        wireForceMode();
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
