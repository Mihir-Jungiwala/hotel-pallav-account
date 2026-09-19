/* ===========================================================================
   Hotel Pallav - PMS interaction layer
   Vanilla, dependency-free. Everything degrades gracefully without JS.
   =========================================================================== */

(function () {
    'use strict';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* -----------------------------------------------------------------------
       Toasts
       ----------------------------------------------------------------------- */

    const Toast = {
        stack: null,

        mount() {
            if (this.stack) return this.stack;
            this.stack = document.createElement('div');
            this.stack.className = 'toast-stack';
            this.stack.setAttribute('role', 'status');
            this.stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(this.stack);
            return this.stack;
        },

        push(type, message, timeout) {
            if (!message) return;
            const stack = this.mount();
            const icons = { success: 'bi-check-lg', error: 'bi-exclamation-lg', info: 'bi-info-lg' };

            const el = document.createElement('div');
            el.className = 'toast-item ' + type;
            el.style.position = 'relative';
            el.innerHTML =
                '<div class="ti-icon"><i class="bi ' + (icons[type] || icons.info) + '"></i></div>' +
                '<div class="ti-body"></div>' +
                '<button class="ti-close" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>';
            el.querySelector('.ti-body').textContent = message;

            stack.appendChild(el);
            requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('in')));

            const dismiss = () => {
                if (!el.isConnected) return;
                el.classList.remove('in');
                el.classList.add('out');
                setTimeout(() => el.remove(), 260);
            };

            el.querySelector('.ti-close').addEventListener('click', dismiss);

            const life = timeout || (type === 'error' ? 7000 : 4200);
            let timer = setTimeout(dismiss, life);

            // Pause the countdown while the pointer rests on the toast
            el.addEventListener('pointerenter', () => clearTimeout(timer));
            el.addEventListener('pointerleave', () => { timer = setTimeout(dismiss, 1800); });
        }
    };

    window.PMSToast = Toast;

    function flushFlash() {
        document.querySelectorAll('[data-flash]').forEach((node) => {
            Toast.push(node.dataset.flash, node.textContent.trim());
            node.remove();
        });
    }

    /* -----------------------------------------------------------------------
       Submit buttons - commit visibly, never double-fire
       ----------------------------------------------------------------------- */

    function wireSubmitStates() {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.noBusy === 'true') return;
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

            const button = form.querySelector('button[type="submit"], button:not([type="button"])');
            if (!button || button.classList.contains('is-busy')) return;

            const label = button.innerHTML;
            // Small controls (status switches, icon buttons) keep their exact size and show
            // only a spinner; "Working..." beside it would spill out of them
            const compact = form.hasAttribute('data-status-toggle') || button.classList.contains('btn-icon') || button.classList.contains('btn-sm');
            if (compact) {
                const box = button.getBoundingClientRect();
                button.style.width = box.width + 'px';
                button.style.height = box.height + 'px';
                button.style.padding = '0';
            }
            button.classList.add('is-busy');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"' +
                (compact ? ' style="margin:0"' : '') + '></span>' +
                (compact ? '' : (button.dataset.busyLabel || 'Working…'));

            // If the navigation is cancelled (validation redirect, back button), restore
            setTimeout(() => {
                if (!button.isConnected) return;
                button.classList.remove('is-busy');
                button.disabled = false;
                button.innerHTML = label;
                button.style.width = button.style.height = button.style.padding = '';
            }, 12000);
        }, true);
    }

    /* -----------------------------------------------------------------------
       Modals - materialize from the element that opened them
       ----------------------------------------------------------------------- */

    function wireModals() {
        document.querySelectorAll('.modal').forEach((modal) => modal.classList.add('pms-modal'));

        let lastTrigger = null;
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-bs-toggle="modal"]');
            if (trigger) lastTrigger = trigger;
        }, true);

        document.addEventListener('show.bs.modal', (event) => {
            const content = event.target.querySelector('.modal-content');
            if (!content || reduceMotion || !lastTrigger) return;

            // Anchor the growth to the trigger so the spatial relationship reads
            const t = lastTrigger.getBoundingClientRect();
            const originX = ((t.left + t.width / 2) / window.innerWidth) * 100;
            const originY = ((t.top + t.height / 2) / window.innerHeight) * 100;
            content.style.transformOrigin = originX.toFixed(1) + '% ' + originY.toFixed(1) + '%';
        });

        // Focus the first real field so the keyboard is immediately useful
        document.addEventListener('shown.bs.modal', (event) => {
            const field = event.target.querySelector(
                '.modal-body input:not([type=hidden]):not([type=file]):not([data-no-autofocus]):not(:disabled), .modal-body select:not(.tomselected):not(:disabled), .modal-body textarea:not(:disabled)'
            );
            if (field) field.focus({ preventScroll: true });
        });
    }

    /* -----------------------------------------------------------------------
       Tab bar - one indicator that slides between tabs
       ----------------------------------------------------------------------- */

    function wireTabs() {
        document.querySelectorAll('.pms-tabs').forEach((bar) => {
            const indicator = document.createElement('span');
            indicator.className = 'pms-tab-indicator no-anim';
            bar.appendChild(indicator);

            const place = (animate) => {
                const active = bar.querySelector('.pms-tab.active');
                if (!active) { indicator.style.opacity = '0'; return; }
                indicator.style.opacity = '1';
                indicator.classList.toggle('no-anim', !animate || reduceMotion);
                indicator.style.width = active.offsetWidth + 'px';
                indicator.style.height = active.offsetHeight + 'px';
                indicator.style.transform = 'translate(' + active.offsetLeft + 'px,' + active.offsetTop + 'px)';
            };

            place(false);
            requestAnimationFrame(() => indicator.classList.remove('no-anim'));

            // Move the indicator on hover intent, snap back on leave
            bar.querySelectorAll('.pms-tab').forEach((tab) => {
                tab.addEventListener('pointerenter', () => {
                    if (reduceMotion || tab.classList.contains('active')) return;
                    indicator.style.width = tab.offsetWidth + 'px';
                    indicator.style.height = tab.offsetHeight + 'px';
                    indicator.style.transform = 'translate(' + tab.offsetLeft + 'px,' + tab.offsetTop + 'px)';
                    indicator.style.opacity = '.45';
                });
            });
            bar.addEventListener('pointerleave', () => { indicator.style.opacity = '1'; place(true); });

            window.addEventListener('resize', () => place(false));
        });
    }

    /* -----------------------------------------------------------------------
       Staggered reveal on load
       ----------------------------------------------------------------------- */

    function wireReveal() {
        const items = document.querySelectorAll('.reveal');
        if (!items.length) return;

        if (reduceMotion) {
            items.forEach((el) => el.classList.add('in'));
            return;
        }

        items.forEach((el, index) => {
            setTimeout(() => el.classList.add('in'), Math.min(index * 28, 320));
        });
    }

    /* -----------------------------------------------------------------------
       Count-up for stat values
       ----------------------------------------------------------------------- */

    function wireCountUps() {
        document.querySelectorAll('[data-countup]').forEach((el) => {
            const target = parseFloat(el.dataset.countup);
            if (isNaN(target)) return;

            const decimals = parseInt(el.dataset.countupDecimals || '0', 10);
            const prefix = el.dataset.countupPrefix || '';

            const format = (value) =>
                prefix + value.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

            if (reduceMotion || target === 0) { el.textContent = format(target); return; }

            const duration = 900;
            const start = performance.now();

            const tick = (now) => {
                const p = Math.min((now - start) / duration, 1);
                // expo-out so it decelerates into place
                const eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
                el.textContent = format(target * eased);
                if (p < 1) requestAnimationFrame(tick);
            };

            requestAnimationFrame(tick);
        });
    }

    /* -----------------------------------------------------------------------
       Live table filtering
       ----------------------------------------------------------------------- */

    function wireFilters() {
        document.querySelectorAll('[data-filter-target]').forEach((input) => {
            const table = document.querySelector(input.dataset.filterTarget);
            if (!table) return;

            const counter = input.dataset.filterCount ? document.querySelector(input.dataset.filterCount) : null;
            const smart = window.PMSSearch || null;

            // When a pager owns this table it decides row visibility; the filter
            // only marks which rows are eligible.
            const paginated = table.hasAttribute('data-paginate');

            if (smart) smart.enhanceInput(input, table);

            const apply = () => {
                const raw = input.value.trim();
                // The smart matcher reads everything a row shows (names, amounts
                // as printed, dates, statuses) and understands "two words",
                // "quoted phrases", -exclusions, >amounts, dates and typos.
                // Without it, fall back to a plain contains-check.
                const query = smart ? smart.compileFor(raw, table) : null;
                const plain = raw.toLowerCase();
                let shown = 0;

                table.querySelectorAll('tbody tr[data-row]').forEach((row) => {
                    const match = query
                        ? (query.empty || query.test(smart.index(row)))
                        : (!plain || row.dataset.row.toLowerCase().includes(plain));

                    if (match) {
                        delete row.dataset.filteredOut;
                        shown++;
                    } else {
                        row.dataset.filteredOut = '1';
                    }

                    if (!paginated) row.hidden = !match;
                });

                const emptyRow = table.querySelector('tbody tr[data-empty]');
                if (emptyRow) emptyRow.hidden = shown !== 0;

                const noMatch = table.querySelector('tbody tr[data-no-match]');
                if (noMatch) noMatch.hidden = !(raw && shown === 0);

                if (counter) counter.textContent = shown;

                if (paginated) table.dispatchEvent(new CustomEvent('pms:filtered'));
                else renumber(table); // keep S.No. contiguous over the matches

                // Highlight what matched, or explain an empty result. After the
                // pager has decided which rows are on the page.
                if (smart) smart.decorate(table, input, query, shown);
            };

            input.addEventListener('input', apply);
            apply();
        });
    }


    /* -----------------------------------------------------------------------
       Inline uniqueness validation
       ----------------------------------------------------------------------- */

    function wireUniqueChecks() {
        document.querySelectorAll('[data-unique-among]').forEach((input) => {
            let taken;
            try { taken = JSON.parse(input.dataset.uniqueAmong); } catch (e) { return; }

            const own = (input.dataset.uniqueSelf || '').toLowerCase();
            const hint = document.createElement('div');
            hint.className = 'field-hint';
            input.insertAdjacentElement('afterend', hint);

            const check = () => {
                const value = input.value.trim().toLowerCase();
                const clash = value && value !== own && taken.some((v) => String(v).toLowerCase() === value);

                input.classList.toggle('is-invalid-live', !!clash);
                input.setCustomValidity(clash ? (input.dataset.uniqueMessage || 'Already in use') : '');

                if (clash) {
                    hint.className = 'field-hint error';
                    hint.innerHTML = '<i class="bi bi-exclamation-circle"></i>' + (input.dataset.uniqueMessage || 'Already in use');
                } else if (value) {
                    hint.className = 'field-hint ok';
                    hint.innerHTML = '<i class="bi bi-check-circle"></i>Available';
                } else {
                    hint.className = 'field-hint';
                    hint.textContent = '';
                }
            };

            input.addEventListener('input', check);
        });
    }

    /* -----------------------------------------------------------------------
       Attendance grid - keyboard-first month entry
       ----------------------------------------------------------------------- */

    function wireAttendanceGrid() {
        const grid = document.querySelector('[data-attendance-grid]');
        if (!grid) return;

        let statuses = {};
        try { statuses = JSON.parse(grid.dataset.statuses || '{}'); } catch (e) { statuses = {}; }

        const cellAt = (employeeId, day) =>
            grid.querySelector('.status-cell[data-employee="' + employeeId + '"][data-day="' + day + '"]');

        const employeeIds = Array.from(grid.querySelectorAll('.employee-row')).map((row) => row.dataset.employee);

        function paint(cell) {
            const key = cell.value.trim().toUpperCase();
            const status = statuses[key];

            if (status) {
                cell.style.background = status.color;
                cell.classList.add('filled');
                cell.classList.remove('invalid-key');
                cell.setCustomValidity('');
                cell.title = status.name + ' - ' + status.percentage + '%';
            } else {
                cell.style.background = '';
                cell.classList.remove('filled');
                cell.classList.toggle('invalid-key', key !== '');
                cell.setCustomValidity(key === '' ? '' : 'Unknown attendance status key: ' + key);
                cell.title = key === '' ? '' : 'Not a configured shortcut key';
            }
        }

        function recalc(employeeId, animate) {
            const row = grid.querySelector('.employee-row[data-employee="' + employeeId + '"]');
            if (!row) return;

            const counts = { 0: 0, 25: 0, 50: 0, 75: 0, 100: 0 };
            let payable = 0;

            row.querySelectorAll('.status-cell').forEach((cell) => {
                const status = statuses[cell.value.trim().toUpperCase()];
                if (!status) return;
                counts[status.percentage] = (counts[status.percentage] || 0) + 1;
                payable += status.percentage / 100;
            });

            let overtime = 0;
            const otRow = grid.querySelector('.overtime-row[data-employee="' + employeeId + '"]');
            if (otRow) otRow.querySelectorAll('.ot-cell input').forEach((c) => { overtime += parseFloat(c.value) || 0; });

            const set = (selector, value) => {
                const node = row.querySelector(selector);
                if (!node) return;
                const next = String(value);
                if (node.textContent === next) return;
                node.textContent = next;
                if (animate && !reduceMotion) {
                    node.classList.remove('bump');
                    void node.offsetWidth;
                    node.classList.add('bump');
                }
            };

            set('.summary-0', counts[0] || 0);
            set('.summary-25', counts[25] || 0);
            set('.summary-50', counts[50] || 0);
            set('.summary-75', counts[75] || 0);
            set('.summary-100', counts[100] || 0);
            set('.summary-ot', overtime);
            set('.summary-total', payable.toFixed(2));
        }

        grid.querySelectorAll('.status-cell').forEach((cell) => {
            cell.addEventListener('input', () => {
                cell.value = cell.value.toUpperCase();
                paint(cell);
                recalc(cell.dataset.employee, true);

                // Auto-advance once the typed value is a complete, valid key
                const key = cell.value.trim().toUpperCase();
                const isComplete = statuses[key] && !Object.keys(statuses).some(
                    (k) => k !== key && k.startsWith(key)
                );
                if (isComplete) moveFocus(cell, 1, 0, false);
            });

            cell.addEventListener('focus', () => cell.select());
            cell.addEventListener('keydown', (event) => handleGridKey(event, cell));
            paint(cell);
        });

        grid.querySelectorAll('.ot-cell input').forEach((cell) => {
            cell.addEventListener('input', () => recalc(cell.dataset.employee, true));
            cell.addEventListener('keydown', (event) => handleGridKey(event, cell));
        });

        function moveFocus(cell, dx, dy, wrap) {
            const day = parseInt(cell.dataset.day, 10);
            const employeeId = cell.dataset.employee;

            if (dy !== 0) {
                const index = employeeIds.indexOf(employeeId);
                const next = employeeIds[index + dy];
                if (!next) return;
                const target = cellAt(next, day);
                if (target) { target.focus(); target.select(); }
                return;
            }

            let target = cellAt(employeeId, day + dx);

            if (!target && wrap !== false) {
                // Roll onto the next employee at the start of the month
                const index = employeeIds.indexOf(employeeId);
                const nextEmployee = employeeIds[index + (dx > 0 ? 1 : -1)];
                if (nextEmployee) target = cellAt(nextEmployee, dx > 0 ? 1 : 31);
            }

            if (target) { target.focus(); target.select(); }
        }

        function handleGridKey(event, cell) {
            const map = { ArrowRight: [1, 0], ArrowLeft: [-1, 0], ArrowDown: [0, 1], ArrowUp: [0, -1] };

            if (map[event.key]) {
                // Let the caret move within a partly typed value first
                if ((event.key === 'ArrowLeft' || event.key === 'ArrowRight') &&
                    cell.selectionStart !== cell.selectionEnd) {
                    // text is selected - treat as navigation
                } else if (event.key === 'ArrowLeft' && cell.selectionStart > 0) {
                    return;
                } else if (event.key === 'ArrowRight' && cell.selectionStart < cell.value.length) {
                    return;
                }

                event.preventDefault();
                moveFocus(cell, map[event.key][0], map[event.key][1], true);
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                moveFocus(cell, 0, 1, false);
            }
        }

        employeeIds.forEach((id) => recalc(id, false));

        // Fill-down: copy the focused cell's value across the rest of its row
        grid.addEventListener('keydown', (event) => {
            if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'd') return;
            const cell = document.activeElement;
            if (!cell || !cell.classList.contains('status-cell')) return;

            event.preventDefault();
            const value = cell.value.trim().toUpperCase();
            if (!statuses[value]) return;

            let filled = 0;
            const row = grid.querySelector('.employee-row[data-employee="' + cell.dataset.employee + '"]');
            row.querySelectorAll('.status-cell').forEach((other) => {
                if (parseInt(other.dataset.day, 10) <= parseInt(cell.dataset.day, 10)) return;
                if (other.disabled || other.value.trim() !== '') return;
                other.value = value;
                paint(other);
                filled++;
            });

            recalc(cell.dataset.employee, true);
            Toast.push('info', filled ? 'Filled ' + filled + ' empty day(s) with ' + value : 'No empty days left to fill');
        });
    }

    /* -----------------------------------------------------------------------
       Multi-step forms
       ----------------------------------------------------------------------- */

    function wireWizards() {
        document.querySelectorAll('[data-wizard]').forEach((wizard) => {
            const steps = [...wizard.querySelectorAll('.form-step')];
            if (steps.length < 2) return;

            const form = wizard.closest('form');
            const footer = form?.querySelector('.modal-footer');
            if (!form || !footer) return;

            const submitButton = footer.querySelector('button[type="submit"], button:not([type="button"])');
            let index = 0;

            // Progress header
            const header = document.createElement('div');
            header.className = 'wizard-steps';
            steps.forEach((step, i) => {
                const item = document.createElement('div');
                item.className = 'wz-step';
                item.innerHTML = '<span class="wz-dot">' + (i + 1) + '</span><span class="wz-name"></span>';
                item.querySelector('.wz-name').textContent = step.dataset.step || 'Step ' + (i + 1);
                header.appendChild(item);
            });
            wizard.prepend(header);

            // Navigation buttons
            const back = document.createElement('button');
            back.type = 'button';
            back.className = 'btn btn-outline-p wz-back';
            back.innerHTML = '<i class="bi bi-chevron-left"></i> Back';

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'btn btn-p wz-next';
            next.innerHTML = 'Continue <i class="bi bi-chevron-right"></i>';

            footer.prepend(next);
            footer.prepend(back);

            function render() {
                steps.forEach((step, i) => step.classList.toggle('active', i === index));

                [...header.children].forEach((item, i) => {
                    item.classList.toggle('current', i === index);
                    item.classList.toggle('done', i < index);
                    item.querySelector('.wz-dot').innerHTML = i < index ? '<i class="bi bi-check-lg"></i>' : String(i + 1);
                });

                const last = index === steps.length - 1;
                back.hidden = index === 0;
                next.hidden = last;
                if (submitButton) submitButton.hidden = !last || form.dataset.readonly === 'true';

                wizard.closest('.modal-body')?.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
            }

            // Only the step on screen is checked, so a later step can never
            // block moving forward, and the message is drawn in the page.
            function stepValid() {
                return window.PMSForms ? window.PMSForms.validate(steps[index]) : true;
            }

            next.addEventListener('click', () => {
                if (!stepValid()) return;
                index = Math.min(index + 1, steps.length - 1);
                render();
            });

            back.addEventListener('click', () => {
                index = Math.max(index - 1, 0);
                render();
            });

            // Jump straight to a completed step
            header.addEventListener('click', (e) => {
                const item = e.target.closest('.wz-step');
                if (!item) return;
                const target = [...header.children].indexOf(item);
                if (target < index) { index = target; render(); }
            });

            /*
             * Submitting from the last step still has to answer for the steps
             * behind it. A field on another step cannot be shown a message
             * where it stands, so the wizard moves to that step first and
             * only then draws it. This runs before the page-wide validation
             * handler and tells it the form is already accounted for.
             */
            form.dataset.pmsWizardHandled = '1';

            form.addEventListener('submit', (e) => {
                if (!window.PMSForms) return;

                // Check every step, not just this one
                const firstBadStep = steps.findIndex((step) => !window.PMSForms.validate(step, { focus: false }));

                if (firstBadStep === -1) return;

                e.preventDefault();
                e.stopImmediatePropagation();

                if (firstBadStep !== index) {
                    index = firstBadStep;
                    render();
                }

                // Redraw with focus now the right step is on screen
                setTimeout(() => window.PMSForms.validate(steps[index]), reduceMotion ? 0 : 160);
            }, true);

            // Enter should advance rather than submit from an early step
            form.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                const tag = (e.target.tagName || '').toLowerCase();
                if (tag === 'textarea') return;
                if (index < steps.length - 1) { e.preventDefault(); next.click(); }
            });

            render();
        });
    }

    /* -----------------------------------------------------------------------
       Payment mode → bank fields, deduction rows
       ----------------------------------------------------------------------- */

    /* Conditional fields now live in pms-forms.js, behind one data-show-when
       rule, so every "show this only when that" behaves the same way and a
       hidden field never keeps a requirement that would block submit. */

    /* -----------------------------------------------------------------------
       Collapsible panels - primary sidebar + docked secondary panel
       ----------------------------------------------------------------------- */

    function wirePanels() {
        const body = document.body;

        const read = (key) => {
            try { return localStorage.getItem(key) === '1'; } catch (e) { return false; }
        };
        const write = (key, value) => {
            try { localStorage.setItem(key, value ? '1' : '0'); } catch (e) { /* private mode */ }
        };

        // Restore before paint so there's no visible jump
        if (read('pms.sidebarCollapsed')) body.classList.add('sidebar-collapsed');
        if (read('pms.subnavHidden')) body.classList.add('subnav-hidden');

        const sidebarToggle = document.getElementById('sidebarToggle');
        const subnavToggle = document.getElementById('subnavToggle');

        const subnavPeek = document.getElementById('subnavPeek');

        const syncLabels = () => {
            if (sidebarToggle) {
                const collapsed = body.classList.contains('sidebar-collapsed');
                const label = sidebarToggle.querySelector('span');
                sidebarToggle.title = collapsed ? 'Expand the menu' : 'Collapse the menu';
                sidebarToggle.setAttribute('aria-label', sidebarToggle.title);
                sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
                if (label) label.textContent = collapsed ? 'Expand' : 'Collapse menu';
            }
            if (subnavToggle) {
                const hidden = body.classList.contains('subnav-hidden');
                subnavToggle.title = hidden ? 'Show this panel' : 'Hide this panel';
                subnavToggle.setAttribute('aria-label', subnavToggle.title);
                subnavToggle.setAttribute('aria-expanded', String(!hidden));
            }
        };

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                body.classList.toggle('sidebar-collapsed');
                write('pms.sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
                syncLabels();
            });
        }

        const setSubnav = (hidden) => {
            body.classList.toggle('subnav-hidden', hidden);
            write('pms.subnavHidden', hidden);
            syncLabels();
        };

        if (subnavToggle) {
            subnavToggle.addEventListener('click', () => setSubnav(!body.classList.contains('subnav-hidden')));
        }

        // The tab on the panel's own edge brings it back the way it left
        if (subnavPeek) {
            subnavPeek.addEventListener('click', () => {
                setSubnav(false);
                document.getElementById('subnav')?.querySelector('.rail-item')?.focus({ preventScroll: true });
            });
        }

        // Keyboard: [ toggles the secondary panel, \ toggles the sidebar
        document.addEventListener('keydown', (e) => {
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            const tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;

            if (e.key === '[') {
                e.preventDefault();
                if (body.classList.contains('subnav-hidden')) { subnavPeek?.click(); } else { subnavToggle?.click(); }
            }
            if (e.key === '\\' && sidebarToggle) { e.preventDefault(); sidebarToggle.click(); }
        });

        syncLabels();
        wireHoverPeek();
    }

    /* -----------------------------------------------------------------------
       Open on hover, when shut

       A shut rail or panel slides out over the page while the pointer rests
       on it, and back when the pointer leaves. It waits a beat before opening
       so a pointer merely crossing the rail on its way somewhere does not
       flash it, and a little longer before closing so a small overshoot off
       the edge does not snap it shut. Touch screens have no hover, so they
       keep the click behaviour and nothing here runs.
       ----------------------------------------------------------------------- */

    function wireHoverPeek() {
        const body = document.body;
        if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        const OPEN_DELAY = 140;
        const CLOSE_DELAY = 260;

        /**
         * Keeps `cls` on <body> while the pointer is over any of `zones`, as
         * long as `shut()` says the panel is currently closed.
         */
        function peek(zones, cls, shut) {
            zones = zones.filter(Boolean);
            if (!zones.length) return;

            let openTimer = null;
            let closeTimer = null;

            const enter = () => {
                clearTimeout(closeTimer);
                if (!shut() || body.classList.contains(cls)) return;
                openTimer = setTimeout(() => body.classList.add(cls), OPEN_DELAY);
            };

            const leave = (event) => {
                // Moving between two zones of the same panel is not leaving it
                if (event.relatedTarget && zones.some((z) => z.contains(event.relatedTarget))) return;
                clearTimeout(openTimer);
                closeTimer = setTimeout(() => {
                    // A menu opened from inside the panel keeps it open
                    if (zones.some((z) => z.querySelector('.open, .show'))) return;
                    body.classList.remove(cls);
                }, CLOSE_DELAY);
            };

            zones.forEach((zone) => {
                zone.addEventListener('mouseenter', enter);
                zone.addEventListener('mouseleave', leave);
            });

            // Pinning it open with the real control ends the peek. Only touch
            // the class when it is actually there: writing the attribute, even
            // to the same value, queues another mutation and would loop.
            new MutationObserver(() => {
                if (!shut() && body.classList.contains(cls)) body.classList.remove(cls);
            }).observe(body, { attributes: true, attributeFilter: ['class'] });
        }

        const narrow = window.matchMedia('(max-width: 1080px)');

        peek(
            [document.getElementById('sidebar')],
            'sidebar-peeking',
            () => body.classList.contains('sidebar-collapsed') || narrow.matches
        );

        peek(
            [document.getElementById('subnav'), document.getElementById('subnavPeek')],
            'subnav-peeking',
            () => body.classList.contains('subnav-hidden')
        );

        // Escape closes whichever is peeking
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            ['sidebar-peeking', 'subnav-peeking'].forEach((c) => {
                if (body.classList.contains(c)) body.classList.remove(c);
            });
        });
    }

    /* -----------------------------------------------------------------------
       Company switcher
       ----------------------------------------------------------------------- */

    function wireCompanySwitch() {
        const root = document.getElementById('companySwitch');
        if (!root) return;

        const button = root.querySelector('.company-switch-btn');
        const menu = root.querySelector('.company-menu');
        const search = menu.querySelector('.cm-search input');
        const items = () => [...menu.querySelectorAll('.cm-list .cm-item:not([hidden])')];

        const close = () => {
            root.classList.remove('open');
            button.setAttribute('aria-expanded', 'false');
        };

        const open = () => {
            root.classList.add('open');
            button.setAttribute('aria-expanded', 'true');
            if (search) setTimeout(() => search.focus(), 60);
        };

        button.addEventListener('click', (e) => {
            e.stopPropagation();
            root.classList.contains('open') ? close() : open();
        });

        document.addEventListener('click', (e) => { if (!root.contains(e.target)) close(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

        if (search) {
            search.addEventListener('input', () => {
                const term = search.value.trim().toLowerCase();
                let shown = 0;

                menu.querySelectorAll('.cm-list .cm-item').forEach((item) => {
                    const match = !term || (window.PMSSearch ? window.PMSSearch.textMatches(term, item.dataset.name || '') : (item.dataset.name || '').toLowerCase().includes(term));
                    item.hidden = !match;
                    if (match) shown++;
                });

                menu.querySelector('.cm-empty').hidden = shown !== 0;
            });
        }

        // Arrow-key navigation through the list
        root.addEventListener('keydown', (e) => {
            if (!root.classList.contains('open')) return;
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Enter') return;

            const list = items();
            if (!list.length) return;

            const current = list.findIndex((i) => i.classList.contains('cursor'));

            if (e.key === 'Enter') {
                if (current >= 0) { e.preventDefault(); list[current].click(); }
                return;
            }

            e.preventDefault();
            const next = e.key === 'ArrowDown'
                ? Math.min(current + 1, list.length - 1)
                : Math.max(current - 1, 0);

            list.forEach((i) => i.classList.remove('cursor'));
            list[next < 0 ? 0 : next].classList.add('cursor');
            list[next < 0 ? 0 : next].scrollIntoView({ block: 'nearest' });
        });
    }

    /* -----------------------------------------------------------------------
       Client-side pagination for record tables
       ----------------------------------------------------------------------- */

    function wirePagination() {
        document.querySelectorAll('[data-paginate]').forEach((table) => {
            const perPageDefault = parseInt(table.dataset.paginate, 10) || 10;
            const body = table.querySelector('tbody');
            if (!body) return;

            const pager = document.querySelector(table.dataset.pager || '');
            if (!pager) return;

            let perPage = perPageDefault;
            let page = 1;

            const dataRows = () => [...body.querySelectorAll('tr[data-row]')].filter((r) => !r.dataset.filteredOut);

            function render() {
                const rows = dataRows();
                const pages = Math.max(1, Math.ceil(rows.length / perPage));
                page = Math.min(page, pages);

                const start = (page - 1) * perPage;
                const end = start + perPage;

                // Rows excluded by a filter must disappear, not just drop out of the count
                body.querySelectorAll('tr[data-row][data-filtered-out]').forEach((row) => { row.hidden = true; });
                rows.forEach((row, index) => { row.hidden = index < start || index >= end; });

                // S.No. counts through the whole list rather than restarting on
                // each page, so page 2 of ten-a-page begins at 11.
                rows.forEach((row, index) => {
                    const cell = row.querySelector('.sno');
                    if (cell) cell.textContent = index + 1;
                });

                const info = pager.querySelector('.pg-info');
                if (info) {
                    info.textContent = rows.length === 0
                        ? 'No records'
                        : `Showing ${start + 1}-${Math.min(end, rows.length)} of ${rows.length}`;
                }

                const controls = pager.querySelector('.pg-controls');
                if (!controls) return;
                controls.innerHTML = '';

                const addButton = (label, target, opts = {}) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.innerHTML = label;
                    if (opts.current) b.classList.add('current');
                    if (opts.disabled) b.disabled = true;
                    if (opts.label) b.setAttribute('aria-label', opts.label);
                    b.addEventListener('click', () => { page = target; render(); });
                    controls.appendChild(b);
                };

                addButton('<i class="bi bi-chevron-left"></i>', page - 1, { disabled: page === 1, label: 'Previous page' });

                // Window the page numbers so long lists stay compact
                const window_ = [];
                for (let n = 1; n <= pages; n++) {
                    if (n === 1 || n === pages || Math.abs(n - page) <= 1) window_.push(n);
                    else if (window_[window_.length - 1] !== '…') window_.push('…');
                }

                window_.forEach((n) => {
                    if (n === '…') {
                        const span = document.createElement('button');
                        span.type = 'button';
                        span.textContent = '…';
                        span.disabled = true;
                        controls.appendChild(span);
                    } else {
                        addButton(String(n), n, { current: n === page });
                    }
                });

                addButton('<i class="bi bi-chevron-right"></i>', page + 1, { disabled: page === pages, label: 'Next page' });
            }

            const sizeSelect = pager.querySelector('select');
            if (sizeSelect) {
                sizeSelect.value = String(perPage);
                sizeSelect.addEventListener('change', () => {
                    perPage = parseInt(sizeSelect.value, 10) || perPageDefault;
                    page = 1;
                    render();
                });
            }

            table.addEventListener('pms:filtered', () => { page = 1; render(); });
            table.addEventListener('pms:sorted', () => { page = 1; render(); });
            render();
        });
    }

    /* -----------------------------------------------------------------------
       Column sorting

       The server already delivers every list newest-first, which is the order
       the module is meant to be read in. This only re-orders when someone
       deliberately asks for something else, and that choice then stands until
       the page is left.
       ----------------------------------------------------------------------- */

    /** Fills the S.No. column of a table the pager does not own. */
    function renumber(table) {
        const rows = [...table.querySelectorAll('tbody tr[data-row]')].filter((r) => !r.dataset.filteredOut);
        rows.forEach((row, index) => {
            const cell = row.querySelector('.sno');
            if (cell) cell.textContent = index + 1;
        });
    }

    function sortValue(row, index, kind) {
        const cell = row.children[index];
        if (!cell) return kind === 'text' ? '' : 0;

        // An explicit data-sort-value wins: it lets a cell display "12 / 30"
        // or "2 Mar 2026" and still sort on the number or the timestamp.
        const explicit = cell.dataset.sortValue;
        const raw = explicit !== undefined ? explicit : cell.textContent.trim();

        if (kind === 'num') {
            const n = parseFloat(raw.replace(/[^0-9.-]/g, ''));
            return Number.isNaN(n) ? -Infinity : n;
        }

        if (kind === 'date') {
            if (explicit !== undefined) return Number(explicit) || 0;
            // Stored dates are d-m-Y; Date.parse reads those the wrong way round
            const m = raw.match(/(\d{1,2})[-/](\d{1,2})[-/](\d{4})/);
            if (m) return Number(m[3] + m[2].padStart(2, '0') + m[1].padStart(2, '0'));
            const t = Date.parse(raw);
            return Number.isNaN(t) ? 0 : t;
        }

        return raw.toLowerCase();
    }

    function wireSortableTables() {
        document.querySelectorAll('table[data-sortable]').forEach((table) => {
            const body = table.querySelector('tbody');
            const headers = [...table.querySelectorAll('thead th')];
            if (!body) return;

            // Remember the delivered order so "sort off" can restore it
            const original = [...body.querySelectorAll('tr[data-row]')];

            let activeIndex = null;
            let direction = 'desc';

            const paint = () => {
                headers.forEach((th, i) => {
                    if (!th.dataset.sort) return;
                    const on = i === activeIndex;
                    th.classList.toggle('sorted', on);
                    th.dataset.dir = on ? direction : '';
                    th.setAttribute('aria-sort', on ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');
                });
            };

            const apply = () => {
                let rows = original;

                if (activeIndex !== null) {
                    const kind = headers[activeIndex].dataset.sort || 'text';
                    const factor = direction === 'asc' ? 1 : -1;

                    rows = [...original].sort((a, b) => {
                        const av = sortValue(a, activeIndex, kind);
                        const bv = sortValue(b, activeIndex, kind);
                        if (av < bv) return -1 * factor;
                        if (av > bv) return 1 * factor;
                        // Stable tie-break, so equal values never shuffle about
                        return original.indexOf(a) - original.indexOf(b);
                    });
                }

                // Keep the empty/no-match rows at the bottom where they belong
                const tail = [...body.children].filter((r) => !r.hasAttribute('data-row'));
                rows.forEach((row) => body.appendChild(row));
                tail.forEach((row) => body.appendChild(row));

                paint();
                // A paginated table renumbers from its pager; one without a
                // pager has to do it here or S.No. would keep the old order.
                if (!table.hasAttribute('data-paginate')) renumber(table);
                table.dispatchEvent(new CustomEvent('pms:sorted'));
            };

            headers.forEach((th, index) => {
                if (!th.dataset.sort) return;

                th.classList.add('sortable');
                th.tabIndex = 0;
                th.setAttribute('role', 'columnheader');
                th.setAttribute('aria-sort', 'none');
                th.insertAdjacentHTML('beforeend', '<i class="bi sort-caret" aria-hidden="true"></i>');

                const activate = () => {
                    if (activeIndex === index) {
                        // Third click on the same column returns to the
                        // delivered newest-first order rather than a third state
                        if (direction === 'desc') { direction = 'asc'; }
                        else { activeIndex = null; direction = 'desc'; }
                    } else {
                        activeIndex = index;
                        direction = th.dataset.sort === 'text' ? 'asc' : 'desc';
                    }
                    apply();
                };

                th.addEventListener('click', activate);
                th.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); activate(); }
                });
            });
        });
    }

    /* -----------------------------------------------------------------------
       Confirmations

       Only destructive and irreversible actions ask. The dialog names the
       record so nobody confirms a delete they did not mean.
       ----------------------------------------------------------------------- */

    /**
     * Our own confirmation dialog, in place of window.confirm().
     *
     * The browser's is an operating-system box: unthemed, white in night
     * mode, titled with the site's address, and with buttons that only ever
     * say OK and Cancel - so "OK" to a delete reads exactly like "OK" to
     * anything else. This one names the action on its button, colours a
     * destructive one red, and puts focus on Cancel so a stray Enter never
     * deletes anything.
     *
     * @returns {Promise<boolean>}
     */
    function confirmDialog({ title, message, confirmLabel = 'Continue', cancelLabel = 'Cancel', tone = 'primary', icon } = {}) {
        return new Promise((resolve) => {
            const previouslyFocused = document.activeElement;
            const icons = { danger: 'bi-trash3', warning: 'bi-exclamation-triangle', primary: 'bi-question-circle' };

            const overlay = document.createElement('div');
            overlay.className = 'pms-dialog-backdrop';
            overlay.innerHTML = `
                <div class="pms-dialog tone-${tone}" role="alertdialog" aria-modal="true"
                     aria-labelledby="pmsDialogTitle" aria-describedby="pmsDialogMessage">
                    <div class="pd-icon"><i class="bi ${icon || icons[tone] || icons.primary}"></i></div>
                    <div class="pd-body">
                        <h2 class="pd-title" id="pmsDialogTitle"></h2>
                        <p class="pd-message" id="pmsDialogMessage"></p>
                    </div>
                    <div class="pd-actions">
                        <button type="button" class="btn btn-ghost pd-cancel"></button>
                        <button type="button" class="btn pd-confirm"></button>
                    </div>
                </div>`;

            // Text is set as text, never as HTML: messages carry record names
            overlay.querySelector('.pd-title').textContent = title || 'Are you sure?';
            overlay.querySelector('.pd-message').textContent = message || '';
            overlay.querySelector('.pd-message').hidden = !message;
            overlay.querySelector('.pd-cancel').textContent = cancelLabel;
            const confirmButton = overlay.querySelector('.pd-confirm');
            confirmButton.textContent = confirmLabel;
            confirmButton.classList.add(tone === 'danger' ? 'btn-danger-solid' : 'btn-p');

            document.body.appendChild(overlay);
            document.body.classList.add('pms-dialog-open');
            requestAnimationFrame(() => overlay.classList.add('in'));

            const close = (answer) => {
                overlay.classList.remove('in');
                document.removeEventListener('keydown', onKey, true);
                setTimeout(() => {
                    overlay.remove();
                    if (!document.querySelector('.pms-dialog-backdrop')) document.body.classList.remove('pms-dialog-open');
                    previouslyFocused?.focus?.({ preventScroll: true });
                }, reduceMotion ? 0 : 180);
                resolve(answer);
            };

            const onKey = (event) => {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(false); }
                // Keep Tab inside the dialog
                if (event.key === 'Tab') {
                    const focusable = [...overlay.querySelectorAll('button')];
                    const i = focusable.indexOf(document.activeElement);
                    event.preventDefault();
                    focusable[(i + (event.shiftKey ? -1 : 1) + focusable.length) % focusable.length].focus();
                }
            };

            overlay.querySelector('.pd-cancel').addEventListener('click', () => close(false));
            confirmButton.addEventListener('click', () => close(true));
            overlay.addEventListener('mousedown', (event) => { if (event.target === overlay) close(false); });
            document.addEventListener('keydown', onKey, true);

            // Cancel is the safe default for anything destructive
            (tone === 'danger' ? overlay.querySelector('.pd-cancel') : confirmButton).focus();
        });
    }

    /** Reads a confirmation's wording off the element that asked for it. */
    function dialogOptions(el, fallbackIsDelete) {
        const isDelete = el.dataset.confirmTone
            ? el.dataset.confirmTone === 'danger'
            : fallbackIsDelete;

        return {
            title: el.dataset.confirmTitle || (isDelete ? 'Delete this record?' : 'Are you sure?'),
            message: el.dataset.confirm || el.dataset.confirmClick || '',
            confirmLabel: el.dataset.confirmLabel || (isDelete ? 'Delete' : 'Continue'),
            tone: el.dataset.confirmTone || (isDelete ? 'danger' : 'primary'),
            icon: el.dataset.confirmIcon,
        };
    }

    function wireConfirmations() {
        window.PMSDialog = { confirm: confirmDialog };

        // Whole forms that ask first - most often a delete
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
            if (form.dataset.confirmed === '1') return;

            // Swallow this submit entirely rather than just cancelling the
            // navigation: the busy-state handler also listens here, and if it
            // saw a submit the user then declined it would leave the button
            // spinning on a form that never went anywhere.
            event.preventDefault();
            event.stopImmediatePropagation();

            const submitter = event.submitter;
            const isDelete = form.querySelector('input[name="_method"]')?.value.toUpperCase() === 'DELETE';

            // Lets a form whose wording depends on live state (how many rows
            // are selected, which status is chosen) bring it up to date first
            form.dispatchEvent(new CustomEvent('pms:before-confirm'));

            confirmDialog(dialogOptions(form, isDelete)).then((ok) => {
                if (!ok) return;
                form.dataset.confirmed = '1';
                form.requestSubmit(submitter && form.contains(submitter) ? submitter : undefined);
                // Reset, so declining the next one on the same form still asks
                setTimeout(() => { delete form.dataset.confirmed; }, 0);
            });
        }, true);

        // Single buttons that ask first - one form with several actions, such
        // as Save / Generate Salary on the attendance sheet, where only the
        // irreversible one should ask.
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-confirm-click]');
            if (!button || button.dataset.confirmed === '1') return;

            event.preventDefault();
            event.stopImmediatePropagation();

            confirmDialog(dialogOptions(button, false)).then((ok) => {
                if (!ok) return;
                button.dataset.confirmed = '1';
                if (button.form) button.form.requestSubmit(button);
                else button.click();
                setTimeout(() => { delete button.dataset.confirmed; }, 0);
            });
        }, true);
    }

    /* -----------------------------------------------------------------------
       Boot
       ----------------------------------------------------------------------- */

    function boot() {
        wirePanels();
        flushFlash();
        wireConfirmations();  // before the busy state, so a declined confirm never spins a button
        wireSubmitStates();
        wireModals();
        wireTabs();
        wireCompanySwitch();
        wireReveal();
        wireCountUps();
        wireSortableTables(); // before the pager, so it paginates the sorted order
        wirePagination();   // before filters, so the first filter pass can repaginate
        wireFilters();
        // Tables without a pager still need their S.No. column filled
        document.querySelectorAll('table:not([data-paginate])').forEach(renumber);
        wireUniqueChecks();
        wireAttendanceGrid();
        wireWizards();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
