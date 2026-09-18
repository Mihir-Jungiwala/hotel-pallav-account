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
            button.classList.add('is-busy');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>' +
                (button.dataset.busyLabel || 'Working…');

            // If the navigation is cancelled (validation redirect, back button), restore
            setTimeout(() => {
                if (!button.isConnected) return;
                button.classList.remove('is-busy');
                button.disabled = false;
                button.innerHTML = label;
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

            // When a pager owns this table it decides row visibility; the filter
            // only marks which rows are eligible.
            const paginated = table.hasAttribute('data-paginate');

            const apply = () => {
                const term = input.value.trim().toLowerCase();
                let shown = 0;

                table.querySelectorAll('tbody tr[data-row]').forEach((row) => {
                    const match = !term || row.dataset.row.toLowerCase().includes(term);

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
                if (noMatch) noMatch.hidden = !(term && shown === 0);

                if (counter) counter.textContent = shown;

                if (paginated) table.dispatchEvent(new CustomEvent('pms:filtered'));
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

            // Only the visible step is validated, so hidden steps can't block submit
            function stepValid() {
                const fields = steps[index].querySelectorAll('input, select, textarea');

                for (const field of fields) {
                    if (field.disabled || field.closest('[hidden]')) continue;
                    if (!field.checkValidity()) {
                        field.reportValidity();
                        return false;
                    }
                }

                return true;
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

            // If the browser rejects a field on a hidden step, surface that step
            form.addEventListener('submit', (e) => {
                if (form.checkValidity()) return;

                const invalid = form.querySelector(':invalid');
                if (!invalid) return;

                const owner = steps.findIndex((s) => s.contains(invalid));
                if (owner >= 0 && owner !== index) {
                    e.preventDefault();
                    index = owner;
                    render();
                    setTimeout(() => invalid.reportValidity(), 120);
                }
            });

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

    function wireConditionalBank() {
        document.querySelectorAll('[data-bank-scope]').forEach((scope) => {
            const mode = scope.querySelector('.payment-mode');
            if (!mode) return;

            const fields = scope.querySelectorAll('.bank-details');

            const sync = () => {
                const show = mode.value === 'Bank';

                fields.forEach((el) => {
                    el.hidden = !show;

                    // Bank details are only mandatory when the salary is actually paid to a bank
                    el.querySelectorAll('[data-require-when-bank]').forEach((field) => {
                        field.required = show;
                        if (!show) field.setCustomValidity('');
                    });
                });
            };

            mode.addEventListener('change', sync);
            sync();
        });
    }

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

        const syncLabels = () => {
            if (sidebarToggle) {
                const collapsed = body.classList.contains('sidebar-collapsed');
                sidebarToggle.title = collapsed ? 'Expand menu' : 'Collapse menu';
                sidebarToggle.setAttribute('aria-label', sidebarToggle.title);
                sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
            }
            if (subnavToggle) {
                const hidden = body.classList.contains('subnav-hidden');
                subnavToggle.title = hidden ? 'Show panel' : 'Hide panel';
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

        if (subnavToggle) {
            subnavToggle.addEventListener('click', () => {
                body.classList.toggle('subnav-hidden');
                write('pms.subnavHidden', body.classList.contains('subnav-hidden'));
                syncLabels();
            });
        }

        // Keyboard: [ toggles the secondary panel, \ toggles the sidebar
        document.addEventListener('keydown', (e) => {
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            const tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;

            if (e.key === '[' && subnavToggle) { e.preventDefault(); subnavToggle.click(); }
            if (e.key === '\\' && sidebarToggle) { e.preventDefault(); sidebarToggle.click(); }
        });

        syncLabels();
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
                    const match = !term || (item.dataset.name || '').toLowerCase().includes(term);
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
            render();
        });
    }

    /* -----------------------------------------------------------------------
       Boot
       ----------------------------------------------------------------------- */

    function boot() {
        wirePanels();
        flushFlash();
        wireSubmitStates();
        wireModals();
        wireTabs();
        wireCompanySwitch();
        wireReveal();
        wireCountUps();
        wirePagination();   // before filters, so the first filter pass can repaginate
        wireFilters();
        wireUniqueChecks();
        wireAttendanceGrid();
        wireConditionalBank();
        wireWizards();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
