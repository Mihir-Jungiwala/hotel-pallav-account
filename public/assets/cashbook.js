/* Revenue and Expense pop-up: one form for a new entry or an edit. The page
   hands it a JSON blob (blank form, every record's values, and what was typed
   if the last save was refused); this fills the form from it, shows only the
   fields that belong to the chosen kind of entry, and works out where to post.
   The server still validates everything. */
(function () {
    const modal = document.getElementById('cashModal');
    const form = document.getElementById('cashForm');
    const dataEl = document.getElementById('cashData');
    if (!modal || !form || !dataEl) return;

    const data = JSON.parse(dataEl.textContent || '{}');
    const expense = data.mode === 'expense';
    const readOnly = () => form.dataset.readonly === 'true';
    const pad = (n) => String(n).padStart(2, '0');
    const nowTime = () => { const d = new Date(); return pad(d.getHours()) + ':' + pad(d.getMinutes()); };

    let dirty = false;
    let submitting = false;
    let pending = null;
    const markDirty = () => { dirty = true; };

    const checked = (name) => form.querySelector('input[name="' + name + '"]:checked')?.value;
    const kind = () => (expense ? checked('_kind') : 'deposit');
    const book = () => checked('_book') || 'hotel';
    // The key the server routes use: hotel, food, hotel-misc, staff-advance...
    const typeKey = () => {
        if (!expense) return book();
        return kind() === 'advance' ? 'staff-advance' : book() + '-' + kind();
    };

    /* -----------------------------------------------------------------------
       Fields
       ----------------------------------------------------------------------- */

    function syncPerson(box) {
        const select = box.querySelector('[data-person-select]');
        const input = box.querySelector('[data-person-new]');
        const other = select.value === '__other__';
        input.hidden = !other;
        input.disabled = !other || select.disabled;
        input.required = other;
        if (!other) input.value = '';
    }

    function setSelect(select, value) {
        value = value == null ? '' : String(value);
        if (value !== '' && !Array.from(select.options).some((o) => o.value === value)) {
            if (select.tomselect) select.tomselect.addOption({ value, text: value });
            else select.add(new Option(value, value));
        }
        if (select.tomselect) select.tomselect.setValue(value, true);
        else select.value = value;
    }

    // A name that is not on the Master Data list opens the "Other" box with it typed in
    function setPerson(name, value) {
        const select = form.elements[name];
        if (!select) return;
        const box = select.closest('[data-person]');
        const listed = value && Array.from(select.options).some((o) => o.value === value && o.value !== '__other__');
        if (listed || !value) {
            setSelect(select, value || '');
            box.querySelector('[data-person-new]').value = '';
        } else {
            setSelect(select, '__other__');
            box.querySelector('[data-person-new]').value = value;
        }
        syncPerson(box);
    }

    function setField(name, value) {
        if (name === 'depositor' && !expense) return loadDepositors(book(), value);
        if (name === 'withdrawer') return setPerson(name, value);
        const field = form.elements[name];
        if (!field || field instanceof RadioNodeList) return;

        if (field.tagName === 'SELECT') return setSelect(field, value);

        field.value = value == null ? '' : value;
        if (name === 'amount') field.dispatchEvent(new Event('input', { bubbles: true })); // refresh the words
        if (name === 'year_month') field.dispatchEvent(new Event('pms:sync'));              // refresh the month picker
    }

    const FIELDS = ['depositor', 'withdrawer', 'reason', 'expense_name', 'expense_head', 'employee_id', 'year_month', 'amount', 'instruction'];


    /* -----------------------------------------------------------------------
       Revenue depositors: each book has its own list (Master Data), so the
       choices change when the book does. A name that has since been hidden
       is kept on an existing deposit so editing it does not lose the name.
       ----------------------------------------------------------------------- */

    const depositorSelect = form.querySelector('[data-depositor-select]');
    const depositorNote = form.querySelector('[data-depositor-empty]');

    function loadDepositors(bookKey, selected) {
        if (!depositorSelect) return;
        const names = (data.depositors && data.depositors[bookKey]) || [];
        const options = names.slice();
        if (selected && !options.includes(selected)) options.push(selected);

        const ts = depositorSelect.tomselect;
        if (ts) {
            ts.clear(true);
            ts.clearOptions();
            ts.addOption({ value: '', text: 'Choose a depositor…' });
            options.forEach((n) => ts.addOption({ value: n, text: n }));
            ts.refreshOptions(false);
            ts.setValue(selected || '', true);
        } else {
            depositorSelect.innerHTML = '';
            depositorSelect.add(new Option('Choose a depositor…', ''));
            options.forEach((n) => depositorSelect.add(new Option(n, n)));
            depositorSelect.value = selected || '';
        }

        // Nothing to pick: say where to add names, and link there for the SuperAdmin
        const empty = names.length === 0 && !selected;
        if (depositorNote) {
            depositorNote.hidden = !empty;
            if (empty) {
                const label = (data.bookNames && data.bookNames[bookKey]) || 'this book';
                const text = depositorNote.querySelector('[data-depositor-empty-text]');
                text.textContent = 'No depositors are set up for ' + label + ' yet. ';
                const link = data.masterLinks && data.masterLinks[bookKey];
                if (link) {
                    const a = document.createElement('a');
                    a.href = link;
                    a.textContent = 'Add them in Master Data';
                    text.appendChild(a);
                } else {
                    text.appendChild(document.createTextNode('Ask the SuperAdmin to add them in Master Data.'));
                }
            }
        }
    }

    // Choosing the other book swaps the list and clears the previous pick
    form.addEventListener('change', (event) => {
        if (!expense && event.target.name === '_book') loadDepositors(event.target.value, '');
    });


    /* -----------------------------------------------------------------------
       Show only what belongs to the chosen kind, and post to its route
       ----------------------------------------------------------------------- */

    function applyKind() {
        const current = kind();
        form.querySelectorAll('[data-show-for]').forEach((el) => {
            const show = el.dataset.showFor.split(' ').includes(current);
            el.hidden = !show;
            // A hidden field must not be sent, nor block the form as "required"
            el.querySelectorAll('input, select, textarea').forEach((f) => { f.disabled = !show; });
        });
        form.querySelectorAll('[data-person]').forEach(syncPerson);
        form.querySelector('[data-type-field]')?.setAttribute('value', typeKey());
    }

    function pointAtNewRoute() {
        if (form.querySelector('[data-record-id]').value) return;
        form.action = data.actions[typeKey()] || form.action;
        form.querySelector('[data-type-field]')?.setAttribute('value', typeKey());
    }

    form.addEventListener('change', (event) => {
        const t = event.target;
        if (t.matches('[data-person-select]')) {
            syncPerson(t.closest('[data-person]'));
            if (t.value === '__other__') t.closest('[data-person]').querySelector('[data-person-new]').focus();
        }
        if (t.name === '_kind') applyKind();
        if (t.name === '_kind' || t.name === '_book') pointAtNewRoute();
        markDirty();
    });
    form.addEventListener('input', markDirty);

    /* -----------------------------------------------------------------------
       Fill the form
       ----------------------------------------------------------------------- */

    function fill(d) {
        const editing = !!d.id;
        const values = d.values || {};

        form.querySelector('[data-method]').disabled = !editing;
        form.querySelector('[data-record-id]').value = d.id || '';
        form.querySelector('[data-title]').textContent = d.title || (expense ? 'New Entry' : 'New Deposit');
        form.querySelector('[data-subtitle]').textContent = d.subtitle || (expense ? 'Expenses · New' : 'Revenue · New');
        form.querySelector('[data-save-label]').innerHTML = '<i class="bi bi-check2"></i> ' + (editing ? 'Save changes' : (expense ? 'Save Entry' : 'Save Deposit'));

        // Which kind and book: chosen for a new entry, fixed for an existing one
        if (expense && d.kind) form.querySelector('input[name="_kind"][value="' + d.kind + '"]').checked = true;
        if (d.book) form.querySelector('input[name="_book"][value="' + d.book + '"]').checked = true;
        form.querySelectorAll('[data-choice] input').forEach((r) => { r.disabled = editing; });
        form.querySelectorAll('[data-choice]').forEach((g) => g.classList.toggle('is-locked', editing));

        applyKind();   // before the values, so hidden fields are switched off first
        form.action = d.action || data.actions[typeKey()];

        FIELDS.forEach((name) => {
            const fallback = name === 'year_month' ? (values.year_month || '') : '';
            setField(name, values[name] != null ? values[name] : fallback);
        });

        const stamp = form.querySelector('[data-entry-stamp-field]');
        const time = values.time || nowTime();
        if (stamp && window.PMSStamp) window.PMSStamp.set(stamp, values.date || '', time);
        else { form.elements.date.value = values.date || ''; form.elements.time.value = time; }

        // choices were disabled for an edit; keep them out of applyKind's way
        form.querySelectorAll('[data-show-for] [data-choice] input').forEach((r) => { r.disabled = editing || r.disabled; });

        dirty = false;
        submitting = false;
    }

    modal.addEventListener('show.bs.modal', (event) => {
        if (pending) { fill(pending); pending = null; return; }
        const key = event.relatedTarget?.dataset.cashKey;
        const blank = Object.assign({}, data.blank, { action: null });
        fill((key && data.records && data.records[key]) || blank);
    });

    /* -----------------------------------------------------------------------
       Leaving with unsaved work asks first
       ----------------------------------------------------------------------- */

    function askDiscard() {
        return new Promise((resolve) => {
            const editing = !!form.querySelector('[data-record-id]').value;
            const overlay = document.createElement('div');
            overlay.className = 'pms-dialog-backdrop cb-discard';
            overlay.innerHTML =
                '<div class="cb-discard-card" role="alertdialog" aria-modal="true" aria-labelledby="cbDiscardTitle">' +
                '<div class="cb-discard-icon"><i class="bi bi-exclamation-lg"></i></div>' +
                '<h2 id="cbDiscardTitle">' + (editing ? 'Leave without saving changes?' : 'Leave without saving?') + '</h2>' +
                '<p>' + (editing ? 'Your changes have not been saved.' : 'This entry has not been saved yet.') + ' If you leave now, what you typed will be lost.</p>' +
                '<div class="cb-discard-actions">' +
                '<button type="button" class="btn btn-p" data-keep><i class="bi bi-arrow-return-left"></i> Keep editing</button>' +
                '<button type="button" class="btn cb-discard-btn" data-discard>Discard</button></div></div>';
            document.body.appendChild(overlay);
            requestAnimationFrame(() => overlay.classList.add('in'));

            const close = (answer) => {
                overlay.classList.remove('in');
                document.removeEventListener('keydown', onKey, true);
                setTimeout(() => overlay.remove(), 180);
                resolve(answer);
            };
            const onKey = (event) => {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(false); }
            };
            overlay.querySelector('[data-keep]').addEventListener('click', () => close(false));
            overlay.querySelector('[data-discard]').addEventListener('click', () => close(true));
            overlay.addEventListener('mousedown', (event) => { if (event.target === overlay) close(false); });
            document.addEventListener('keydown', onKey, true);
            overlay.querySelector('[data-keep]').focus();
        });
    }

    modal.addEventListener('hide.bs.modal', (event) => {
        if (!dirty || submitting || readOnly()) return;
        event.preventDefault();
        askDiscard().then((discard) => {
            if (!discard) return;
            dirty = false;
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        });
    });

    form.addEventListener('submit', () => { submitting = true; });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty || submitting || !modal.classList.contains('show')) return;
        event.preventDefault();
        event.returnValue = '';
    });

    // A save the server refused comes straight back, as it was typed
    if (data.reopen && window.bootstrap) {
        pending = data.reopen;
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    /* -----------------------------------------------------------------------
       Search: runs as you type, like Payroll's, and covers every page
       ----------------------------------------------------------------------- */

    const searchForm = document.querySelector('[data-cb-search]');
    if (searchForm) {
        const box = searchForm.querySelector('input[type="search"]');
        let timer = null;
        const go = () => { clearTimeout(timer); searchForm.requestSubmit ? searchForm.requestSubmit() : searchForm.submit(); };

        box.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(go, 450); });
        box.addEventListener('search', go);   // the browser's own clear (x) button

        // Back on the box after the page reloads, caret at the end
        if (box.value) { box.focus(); box.setSelectionRange(box.value.length, box.value.length); }

        // "/" jumps to the search box, as it does in Payroll
        document.addEventListener('keydown', (event) => {
            if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
            if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName) || document.activeElement?.isContentEditable) return;
            event.preventDefault();
            box.focus();
        });
    }
})();
