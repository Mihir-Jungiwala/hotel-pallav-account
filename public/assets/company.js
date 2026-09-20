/* Company Profiles: the add/edit pop-up, the read-only view, the contacts
   list (any number of people, a role can repeat), live search and the
   "leave without saving?" prompt. Everything is filled from the JSON on the
   page; the server still validates all of it. */
(function () {
    const modal = document.getElementById('companyModal');
    const form = document.getElementById('companyForm');
    const viewModal = document.getElementById('companyView');
    const dataEl = document.getElementById('companyData');
    if (!modal || !form || !dataEl) return;

    const data = JSON.parse(dataEl.textContent || '{}');
    const roles = data.roles || [];
    const readOnly = () => form.dataset.readonly === 'true';
    const bs = () => window.bootstrap;

    let dirty = false;
    let submitting = false;
    let pending = null;

    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });

    /* -----------------------------------------------------------------------
       People: a list of rows the form sends as contacts[n][role|name|email|mobile]
       ----------------------------------------------------------------------- */

    const list = form.querySelector('[data-people-list]');
    const template = form.querySelector('[data-person-template]');
    const counter = form.querySelector('[data-people-count]');
    let nextIndex = 0;

    function refreshPeople() {
        const n = list.children.length;
        counter.textContent = n;

        // The first person is the required one; it cannot be removed, the rest can
        Array.from(list.children).forEach((row, i) => {
            row.querySelector('[data-person-no]').textContent = i + 1;
            row.querySelector('[data-person-tag]').textContent = i === 0 ? 'Main contact' : 'Also contact';
            row.querySelector('[data-person-tag]').classList.toggle('main', i === 0);
            row.querySelector('[data-remove-person]').hidden = n === 1;
        });
    }

    function addPerson(person) {
        person = person || {};
        const row = template.content.firstElementChild.cloneNode(true);
        const index = nextIndex++;

        row.querySelectorAll('[data-field]').forEach((el) => {
            el.name = 'contacts[' + index + '][' + el.dataset.field + ']';
            if (el.dataset.field === 'role') {
                const role = person.role || roles[0] || '';
                if (role && !Array.from(el.options).some((o) => o.value === role)) el.add(new Option(role, role));
                el.value = role;
            } else {
                el.value = person[el.dataset.field] || '';
            }
        });

        list.appendChild(row);
        refreshPeople();
        return row;
    }

    function setPeople(people) {
        list.innerHTML = '';
        nextIndex = 0;
        (people || []).forEach(addPerson);
        if (!list.children.length) addPerson({});   // one person is always asked for
        refreshPeople();
    }

    form.addEventListener('click', (event) => {
        const add = event.target.closest('[data-add-person]');
        if (add) {
            const row = addPerson({});
            row.querySelector('[data-field="name"]').focus();
            row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            dirty = true;
        }
        const remove = event.target.closest('[data-remove-person]');
        if (remove) {
            remove.closest('.co-person').remove();
            refreshPeople();
            dirty = true;
        }
    });

    /* -----------------------------------------------------------------------
       Add / edit
       ----------------------------------------------------------------------- */

    function fill(d) {
        const editing = !!d.id;
        const values = d.values || {};

        form.action = d.action;
        form.querySelector('[data-method]').disabled = !editing;
        form.querySelector('[data-record-id]').value = d.id || '';
        form.querySelector('[data-title]').textContent = d.title;
        form.querySelector('[data-subtitle]').textContent = d.subtitle || 'Company Profiles · New';
        form.querySelector('[data-save-label]').innerHTML = '<i class="bi bi-check2"></i> ' + (editing ? 'Save changes' : 'Create Company');

        // Delete sits in the footer of an existing company only
        const deleteBtn = form.querySelector('[data-delete-btn]');
        const deleteForm = document.getElementById('companyDeleteForm');
        deleteBtn.hidden = !editing;
        deleteForm.action = d.destroy || '#';
        deleteForm.dataset.confirmTitle = 'Delete ' + (d.name || 'company') + '?';
        deleteBtn.onclick = () => deleteForm.requestSubmit();

        Object.keys(values).forEach((name) => {
            if (name === 'contacts') return;
            const field = form.elements[name];
            if (field && !(field instanceof RadioNodeList)) field.value = values[name] == null ? '' : values[name];
        });
        setPeople(values.contacts);

        form.querySelector('.wizard-steps .wz-step')?.click();   // always start on the first step

        dirty = false;
        submitting = false;
    }

    modal.addEventListener('show.bs.modal', (event) => {
        if (pending) { fill(pending); pending = null; return; }
        const id = event.relatedTarget?.dataset.companyId;
        fill((id && data.records && data.records[id]) || data.blank);
    });

    /* Leaving with unsaved work asks first, with staying as the obvious choice */
    function askDiscard() {
        return new Promise((resolve) => {
            const editing = !!form.querySelector('[data-record-id]').value;
            const overlay = document.createElement('div');
            overlay.className = 'pms-dialog-backdrop cb-discard';
            overlay.innerHTML =
                '<div class="cb-discard-card" role="alertdialog" aria-modal="true" aria-labelledby="coDiscardTitle">' +
                '<div class="cb-discard-icon"><i class="bi bi-exclamation-lg"></i></div>' +
                '<h2 id="coDiscardTitle">' + (editing ? 'Leave without saving changes?' : 'Leave without saving?') + '</h2>' +
                '<p>' + (editing ? 'Your changes have not been saved.' : 'This company has not been saved yet.') + ' If you leave now, what you typed will be lost.</p>' +
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
            bs().Modal.getOrCreateInstance(modal).hide();
        });
    });

    form.addEventListener('submit', () => { submitting = true; });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty || submitting || !modal.classList.contains('show')) return;
        event.preventDefault();
        event.returnValue = '';
    });

    // A save the server refused comes straight back, as it was typed
    if (data.reopen && bs()) {
        pending = data.reopen;
        bs().Modal.getOrCreateInstance(modal).show();
    }

    /* -----------------------------------------------------------------------
       View: everything about one company, read only
       ----------------------------------------------------------------------- */

    if (viewModal) {
        const slot = (name) => viewModal.querySelector('[data-v="' + name + '"]');
        let viewing = null;

        const el = (tag, className, text) => {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text != null) node.textContent = text;
            return node;
        };
        const link = (href, text, icon) => {
            const a = el('a', 'co-link');
            a.href = href;
            a.innerHTML = '<i class="bi ' + icon + '"></i> ';
            a.appendChild(document.createTextNode(text));
            return a;
        };
        // A label and value pair, skipped when there is no value
        const pair = (dl, label, value) => {
            if (value == null || value === '') return;
            dl.appendChild(el('dt', null, label));
            const dd = el('dd');
            value instanceof Node ? dd.appendChild(value) : (dd.textContent = value);
            dl.appendChild(dd);
        };
        const nothing = (parent, text) => parent.appendChild(el('div', 'co-view-none', text));
        const initials = (name) => (name || '').trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0).toUpperCase()).join('');

        function showCompany(d) {
            const v = d.values || {};
            viewing = d;

            slot('initials').textContent = initials(d.name);
            slot('name').textContent = d.name || '';
            slot('sub').textContent = [v.gst_number ? 'GST ' + v.gst_number : 'No GST number', d.subtitle && d.subtitle.replace('Company profile · ', '')].filter(Boolean).join('  ·  ');

            const rates = slot('rates');
            rates.innerHTML = '';
            Object.entries(data.rates || {}).forEach(([field, label]) => {
                const n = parseFloat(v[field]);
                const tile = el('div', 'co-view-rate' + (n > 0 ? '' : ' off'));
                tile.appendChild(el('span', null, label));
                tile.appendChild(el('strong', null, n > 0 ? (Math.round(n * 100) / 100) + '%' : 'None'));
                rates.appendChild(tile);
            });

            const contact = slot('contact');
            contact.innerHTML = '';
            pair(contact, 'Email', v.email ? link('mailto:' + v.email, v.email, 'bi-envelope') : '');
            pair(contact, 'Mobile', v.mobile_number ? link('tel:' + v.mobile_number, v.mobile_number, 'bi-phone') : '');
            pair(contact, 'Landline', v.phone_number ? link('tel:' + v.phone_number, v.phone_number, 'bi-telephone') : '');
            if (!contact.children.length) nothing(contact, 'No contact details.');

            const location = slot('location');
            location.innerHTML = '';
            pair(location, 'Address', v.address);
            pair(location, 'Pincode', v.pincode);
            pair(location, 'Country', v.country);
            pair(location, 'Nationality', v.nationality);
            if (!location.children.length) nothing(location, 'No address.');

            const note = viewModal.querySelector('[data-v-section="instruction"]');
            note.hidden = !v.instruction;
            slot('instruction').textContent = v.instruction || '';

            const people = slot('people');
            const rows = v.contacts || [];
            people.innerHTML = '';
            slot('peopleCount').textContent = rows.length;
            rows.forEach((p) => {
                const card = el('div', 'co-view-person');
                const head = el('div', 'co-view-person-head');
                head.appendChild(el('strong', null, p.name || p.email || 'Unnamed'));
                if (p.role) head.appendChild(el('span', 'cb-chip kind', p.role));
                card.appendChild(head);
                if (p.email) card.appendChild(link('mailto:' + p.email, p.email, 'bi-envelope'));
                if (p.mobile) card.appendChild(link('tel:' + p.mobile, p.mobile, 'bi-phone'));
                people.appendChild(card);
            });
            if (!rows.length) nothing(people, 'No one added yet.');

            viewModal.querySelector('[data-view-pdf]').href = d.pdf || '#';

            const del = viewModal.querySelector('[data-view-delete]');
            del.action = d.destroy || '#';
            del.dataset.confirmTitle = 'Delete ' + (d.name || 'company') + '?';
        }

        viewModal.addEventListener('show.bs.modal', (event) => {
            const id = event.relatedTarget?.dataset.companyId;
            if (id && data.records[id]) showCompany(data.records[id]);
        });

        // Edit from the view: close this, then open the form on the same company
        viewModal.querySelector('[data-view-edit]').addEventListener('click', () => {
            if (!viewing) return;
            pending = viewing;
            viewModal.addEventListener('hidden.bs.modal', () => bs().Modal.getOrCreateInstance(modal).show(), { once: true });
            bs().Modal.getOrCreateInstance(viewModal).hide();
        });
    }

    /* -----------------------------------------------------------------------
       Search runs as you type, like Payroll's, and covers every page
       ----------------------------------------------------------------------- */

    const searchForm = document.querySelector('[data-cb-search]');
    if (searchForm) {
        const box = searchForm.querySelector('input[type="search"]');
        let timer = null;
        const go = () => { clearTimeout(timer); searchForm.requestSubmit ? searchForm.requestSubmit() : searchForm.submit(); };

        box.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(go, 450); });
        box.addEventListener('search', go);
        if (box.value) { box.focus(); box.setSelectionRange(box.value.length, box.value.length); }

        document.addEventListener('keydown', (event) => {
            if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
            if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName) || document.activeElement?.isContentEditable) return;
            event.preventDefault();
            box.focus();
        });
    }
})();
