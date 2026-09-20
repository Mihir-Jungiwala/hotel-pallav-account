/* Company Profiles: one pop-up for a new company or an edit, filled from the
   JSON on the page (a blank form, every company's values, and what was typed
   when the last save was refused). Also the live search box and the "leave
   without saving?" prompt. The server still validates everything. */
(function () {
    const modal = document.getElementById('companyModal');
    const form = document.getElementById('companyForm');
    const dataEl = document.getElementById('companyData');
    if (!modal || !form || !dataEl) return;

    const data = JSON.parse(dataEl.textContent || '{}');
    const readOnly = () => form.dataset.readonly === 'true';

    let dirty = false;
    let submitting = false;
    let pending = null;

    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });

    function fill(d) {
        const editing = !!d.id;

        form.action = d.action;
        form.querySelector('[data-method]').disabled = !editing;
        form.querySelector('[data-record-id]').value = d.id || '';
        form.querySelector('[data-title]').textContent = d.title;
        form.querySelector('[data-subtitle]').textContent = d.subtitle || 'Company Profiles · New';
        form.querySelector('[data-save-label]').innerHTML = '<i class="bi bi-check2"></i> ' + (editing ? 'Save changes' : 'Create Company');

        Object.keys(d.values || {}).forEach((name) => {
            const field = form.elements[name];
            if (field && !(field instanceof RadioNodeList)) field.value = d.values[name] == null ? '' : d.values[name];
        });

        // Always start from the first step
        form.querySelector('.wizard-steps .wz-step')?.click();

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

    /* Search runs as you type, like Payroll's, and covers every page */
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
