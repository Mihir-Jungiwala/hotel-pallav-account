/* Shift handover pop-up: one form for new and edited handovers, a tap-to-count
   cash till with the total in figures and words, formatted note points and
   plain instruction points. The server recalculates and sanitises everything;
   this is only for the person filling it in. */
(function () {
    /* Search: the list is paged by the server, so typing asks the server for
       the matches a moment after you stop, and the cursor stays in the box. */
    const search = document.querySelector('[data-handover-search]');
    if (search) {
        let timer = null;
        let last = search.value;
        const go = () => { if (search.value !== last) { last = search.value; search.form.requestSubmit ? search.form.requestSubmit() : search.form.submit(); } };

        search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(go, 450); });
        search.addEventListener('keydown', (event) => { if (event.key === 'Escape' && search.value) { search.value = ''; go(); } });

        if (search.value) {
            search.focus({ preventScroll: true });
            search.setSelectionRange(search.value.length, search.value.length);
        }
    }

    const modal = document.getElementById('handoverModal');
    const form = document.getElementById('handoverForm');
    const dataEl = document.getElementById('handoverData');
    if (!modal || !form || !dataEl) return;

    const data = JSON.parse(dataEl.textContent || '{}');
    const readOnly = () => form.dataset.readonly === 'true';

    /* -----------------------------------------------------------------------
       Amount in words, Indian numbering. Mirrors App\Support\NumberToWords so
       the preview matches what gets saved.
       ----------------------------------------------------------------------- */

    const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function twoDigits(n) {
        if (n < 20) return ONES[n];
        return (TENS[Math.floor(n / 10)] + ' ' + ONES[n % 10]).trim();
    }

    function toWords(amount) {
        let n = Math.round(amount);
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

    const rupees = (n, decimals = 2) => '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

    let dirty = false;
    let submitting = false;
    const markDirty = () => { dirty = true; };

    /* -----------------------------------------------------------------------
       Cash till
       ----------------------------------------------------------------------- */

    const tiles = Array.from(form.querySelectorAll('.cash-tile'));

    function countOf(input) {
        const n = parseInt(input.value, 10);
        return Number.isFinite(n) && n > 0 ? n : 0;
    }

    function recalc() {
        let total = 0;
        let notes = 0;
        tiles.forEach((tile) => {
            const count = countOf(tile.querySelector('input'));
            const amount = count * Number(tile.dataset.denom);
            total += amount;
            if (!tile.classList.contains('coin')) notes += count;
            tile.querySelector('[data-amount]').textContent = rupees(amount, 0);
            tile.classList.toggle('has-count', count > 0);
        });

        form.querySelectorAll('[data-total]').forEach((el) => { el.textContent = rupees(total); });
        form.querySelector('[data-words]').textContent = toWords(total);
        form.querySelector('[data-pieces]').textContent = notes ? '· ' + notes + (notes === 1 ? ' note' : ' notes') : '';
    }

    function bump(tile, by) {
        const input = tile.querySelector('input');
        input.value = Math.max(0, countOf(input) + by);
        recalc();
        markDirty();
        tile.classList.remove('bumped');
        void tile.offsetWidth; // restart the little pulse
        tile.classList.add('bumped');
    }

    tiles.forEach((tile, index) => {
        const input = tile.querySelector('input');
        input.addEventListener('input', () => { recalc(); markDirty(); });
        input.addEventListener('focus', () => input.select());
        // Enter walks down the till; after the last note it moves to the next step
        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            event.stopPropagation();
            const next = tiles[index + 1];
            if (next) next.querySelector('input').focus();
            else form.querySelector('.wz-next:not([hidden])')?.click();
        });

        tile.querySelector('[data-add]').addEventListener('click', () => { if (!readOnly()) bump(tile, 1); });
        tile.querySelectorAll('[data-step]').forEach((button) => {
            button.addEventListener('click', () => { if (!readOnly()) bump(tile, Number(button.dataset.step)); });
        });
    });

    form.querySelector('[data-clear-count]').addEventListener('click', () => {
        if (readOnly()) return;
        tiles.forEach((tile) => { tile.querySelector('input').value = 0; });
        recalc();
        markDirty();
    });

    /* -----------------------------------------------------------------------
       Notes: a formatted bullet list, one point per line
       ----------------------------------------------------------------------- */

    const notesBox = form.querySelector('[data-points-editor]');
    const editor = notesBox.querySelector('.rt-editor');
    const noteInputs = notesBox.querySelector('[data-note-inputs]');
    const noteCount = form.querySelector('[data-note-count]');
    const toolButtons = Array.from(notesBox.querySelectorAll('[data-cmd]'));

    const escapeHtml = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const liIsEmpty = (li) => li.textContent.replace(/ /g, ' ').trim() === '';

    function list() {
        let ul = editor.querySelector(':scope > ul');
        if (!ul) {
            // Everything typed outside a list is folded into points
            ul = document.createElement('ul');
            const loose = Array.from(editor.childNodes);
            editor.appendChild(ul);
            loose.forEach((node) => {
                if (node.nodeName === 'UL' || node.nodeName === 'OL') {
                    Array.from(node.children).forEach((li) => ul.appendChild(li));
                    node.remove();
                } else if (node.textContent.trim() !== '') {
                    const li = document.createElement('li');
                    li.appendChild(node);
                    ul.appendChild(li);
                } else {
                    node.remove();
                }
            });
        }
        if (!ul.children.length) ul.innerHTML = '<li><br></li>';
        return ul;
    }

    function setNotes(points) {
        editor.innerHTML = '<ul>' + (points.length ? points.map((p) => '<li>' + p + '</li>').join('') : '<li><br></li>') + '</ul>';
        refreshNotes();
    }

    function notePoints() {
        return Array.from(list().children)
            .filter((li) => li.nodeName === 'LI' && !liIsEmpty(li))
            .map((li) => li.innerHTML.trim());
    }

    function refreshNotes() {
        const count = notePoints().length;
        noteCount.textContent = count;
        editor.classList.toggle('is-empty', count === 0 && list().children.length <= 1);
    }

    function inTag(tag) {
        let node = window.getSelection()?.anchorNode;
        while (node && node !== editor) {
            if (node.nodeName === tag) return true;
            node = node.parentNode;
        }
        return false;
    }

    function refreshToolbar() {
        toolButtons.forEach((button) => {
            const cmd = button.dataset.cmd;
            let on = false;
            try {
                if (['bold', 'italic', 'underline', 'strikeThrough'].includes(cmd)) on = document.queryCommandState(cmd);
                else if (cmd === 'link') on = inTag('A');
            } catch (e) { on = false; }
            button.classList.toggle('on', !!on);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function run(cmd) {
        if (readOnly()) return;
        editor.focus();
        if (cmd === 'link') {
            if (inTag('A')) {
                document.execCommand('unlink');
            } else {
                const url = (window.prompt('Link address (https://...)', 'https://') || '').trim();
                if (/^(https?:\/\/|mailto:)/i.test(url)) {
                    if (window.getSelection()?.isCollapsed) {
                        document.execCommand('insertHTML', false, '<a href="' + url.replace(/"/g, '&quot;') + '">' + escapeHtml(url) + '</a>');
                    } else {
                        document.execCommand('createLink', false, url);
                    }
                }
            }
        } else {
            document.execCommand(cmd);
        }
        list();
        refreshNotes();
        refreshToolbar();
        markDirty();
    }

    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* older engines */ }

    toolButtons.forEach((button) => {
        // Keep the selection in the editor while the button is pressed
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => run(button.dataset.cmd));
    });

    editor.addEventListener('input', () => { list(); refreshNotes(); markDirty(); });
    editor.addEventListener('keyup', refreshToolbar);
    editor.addEventListener('mouseup', refreshToolbar);
    document.addEventListener('selectionchange', () => {
        if (editor.contains(window.getSelection()?.anchorNode)) refreshToolbar();
    });

    editor.addEventListener('keydown', (event) => {
        // The first point can never be deleted away; the list always stays
        if (event.key === 'Backspace') {
            const ul = list();
            if (ul.children.length === 1 && liIsEmpty(ul.children[0])) event.preventDefault();
        }
        if (event.key === 'Enter') {
            // Enter belongs to the editor (a new point), not to the step form
            event.stopPropagation();
            // No run of empty points: Enter on an empty one stays put
            const sel = window.getSelection();
            const li = sel?.anchorNode && (sel.anchorNode.nodeName === 'LI' ? sel.anchorNode : sel.anchorNode.parentElement?.closest('li'));
            if (!event.shiftKey && li && liIsEmpty(li)) event.preventDefault();
        }
    });

    // Pasted lines each become a point, without another site's styling
    editor.addEventListener('paste', (event) => {
        const text = event.clipboardData?.getData('text/plain');
        if (text === undefined) return;
        event.preventDefault();
        const lines = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
        if (!lines.length) return;
        document.execCommand('insertText', false, lines[0]);
        if (lines.length === 1) return;

        const sel = window.getSelection();
        let li = sel.anchorNode && (sel.anchorNode.nodeName === 'LI' ? sel.anchorNode : sel.anchorNode.parentElement?.closest('li'));
        lines.slice(1).forEach((line) => {
            const next = document.createElement('li');
            next.textContent = line;
            if (li && li.parentNode) li.after(next); else list().appendChild(next);
            li = next;
        });
        const range = document.createRange();
        range.selectNodeContents(li);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
        refreshNotes();
        markDirty();
    });

    editor.addEventListener('drop', (event) => {
        if (event.dataTransfer?.files?.length) event.preventDefault();
    });

    /* -----------------------------------------------------------------------
       Special instructions: plain points
       ----------------------------------------------------------------------- */

    const pointList = form.querySelector('[data-point-list]');
    const pointTemplate = form.querySelector('[data-point-template]');
    const pointCount = form.querySelector('[data-instruction-count]');

    function renumber() {
        const items = Array.from(pointList.children);
        items.forEach((li, i) => { li.querySelector('.sh-point-no').textContent = i + 1; });
        pointCount.textContent = items.filter((li) => li.querySelector('input').value.trim() !== '').length;
    }

    function addPoint(value = '', after = null, focus = true) {
        const li = pointTemplate.content.firstElementChild.cloneNode(true);
        li.querySelector('input').value = value;
        if (after) after.after(li); else pointList.appendChild(li);
        renumber();
        if (focus) li.querySelector('input').focus();
        return li;
    }

    function setInstructions(points) {
        pointList.innerHTML = '';
        (points.length ? points : ['']).forEach((p) => addPoint(p, null, false));
    }

    pointList.addEventListener('input', () => { renumber(); markDirty(); });

    pointList.addEventListener('keydown', (event) => {
        const input = event.target;
        if (input.tagName !== 'INPUT') return;
        const li = input.closest('li');

        if (event.key === 'Enter') {
            event.preventDefault();
            event.stopPropagation();
            if (input.value.trim() === '') return;
            const next = li.nextElementSibling;
            if (next && next.querySelector('input').value.trim() === '') next.querySelector('input').focus();
            else addPoint('', li);
        }

        // Backspace on an empty point removes it and steps back to the one before
        if (event.key === 'Backspace' && input.value === '' && pointList.children.length > 1) {
            event.preventDefault();
            const target = (li.previousElementSibling || li.nextElementSibling).querySelector('input');
            li.remove();
            renumber();
            target.focus();
        }
    });

    pointList.addEventListener('click', (event) => {
        if (!event.target.closest('[data-point-remove]') || readOnly()) return;
        const li = event.target.closest('li');
        if (pointList.children.length === 1) li.querySelector('input').value = '';
        else li.remove();
        renumber();
        markDirty();
    });

    form.querySelector('[data-point-add]').addEventListener('click', () => {
        const empty = Array.from(pointList.querySelectorAll('input')).find((i) => i.value.trim() === '');
        if (empty) empty.focus(); else addPoint();
    });

    /* -----------------------------------------------------------------------
       Filling the form: blank, a saved record, or a refused save
       ----------------------------------------------------------------------- */

    function nowTime() {
        const d = new Date();
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    /* The shift a time of day falls in, by the same hours the server uses
       (ShiftHandover::SHIFT_HOURS), matched by name against the shifts on
       offer so a renamed "Morning Shift" still counts as morning. */
    const clock = data.shiftClock || { hours: {}, shifts: [] };
    let shiftFollowsClock = true;

    function shiftAt(time) {
        const hour = parseInt(String(time).slice(0, 2), 10);
        if (!Number.isFinite(hour)) return null;
        for (const [word, [from, to]] of Object.entries(clock.hours)) {
            const inside = from < to ? (hour >= from && hour < to) : (hour >= from || hour < to);
            if (!inside) continue;
            const match = clock.shifts.find((s) => s.toLowerCase().includes(word));
            if (match) return match;
        }
        return null;
    }

    // Changing the time moves the shift with it, until a shift is picked by hand
    form.addEventListener('pms:stamp', (event) => {
        if (!shiftFollowsClock) return;
        const picked = shiftAt(event.detail.time);
        if (picked) setShift(picked);
    });
    // setShift() changes the field silently, so any change event here is a person
    form.addEventListener('change', (event) => {
        if (event.target.name === 'shift') shiftFollowsClock = false;
    });

    function setShift(value) {
        const field = form.elements.shift;
        if (!field || value == null) return;
        if (field instanceof RadioNodeList) { field.value = value; return; }
        if (field.tomselect) {
            if (!field.tomselect.options[value]) field.tomselect.addOption({ value, text: value });
            field.tomselect.setValue(value, true);
            return;
        }
        if (field.tagName === 'SELECT' && !Array.from(field.options).some((o) => o.value === value)) {
            field.add(new Option(value, value));
        }
        field.value = value;
    }

    function fill(d) {
        const editing = !!d.id;
        form.action = d.action;
        form.querySelector('[data-method]').disabled = !editing;
        form.querySelector('[data-record-id]').value = d.id || '';
        form.querySelector('[data-title]').textContent = d.title;
        form.querySelector('[data-subtitle]').textContent = d.subtitle || 'Handover · New';
        form.querySelector('[data-save-label]').innerHTML = '<i class="bi bi-check2"></i> ' + (editing ? 'Save changes' : 'Save Handover');

        // A new handover follows the clock: its shift is picked from the time
        // until someone chooses one by hand. An existing one keeps its shift.
        shiftFollowsClock = !editing;
        const stampField = form.querySelector('[data-entry-stamp-field]');
        const time = d.time || nowTime();
        if (stampField && window.PMSStamp) {
            window.PMSStamp.set(stampField, d.date || '', time);
        } else {
            form.elements.date.value = d.date || '';
            form.elements.time.value = time;
        }
        setShift(editing ? d.shift : (shiftAt(time) || d.shift));

        // Always start from the first step
        form.querySelector('.wizard-steps .wz-step')?.click();

        tiles.forEach((tile) => {
            const input = tile.querySelector('input');
            const key = input.name.replace('_count', '');
            input.value = (d.counts && d.counts[key]) || 0;
        });
        recalc();

        setNotes(d.notes || []);
        setInstructions(d.instructions || []);

        dirty = false;
        submitting = false;
    }

    let pending = null;

    modal.addEventListener('show.bs.modal', (event) => {
        if (pending) {
            fill(pending);
            pending = null;
            return;
        }
        const id = event.relatedTarget?.dataset.handoverId;
        fill((id && data.records && data.records[id]) || data.blank);
    });

    modal.addEventListener('shown.bs.modal', () => {
        editor.contentEditable = readOnly() ? 'false' : 'true';
        form.querySelectorAll('[data-point-add], [data-clear-count], .rt-toolbar, .sh-point-remove').forEach((el) => {
            el.hidden = readOnly();
        });
    });

    /* A closer look before throwing work away: what exactly would be lost,
       with staying as the obvious choice. Resolves true to discard. */
    function askDiscard() {
        return new Promise((resolve) => {
            const total = form.querySelector('[data-total]').textContent;
            const cashCounted = tiles.some((t) => countOf(t.querySelector('input')) > 0);
            const notes = notePoints().length;
            const instructions = Array.from(pointList.querySelectorAll('input')).filter((i) => i.value.trim() !== '').length;
            const editing = !!form.querySelector('[data-record-id]').value;

            const lost = [];
            if (cashCounted) lost.push(['bi-cash-coin', total + ' counted']);
            if (notes) lost.push(['bi-sticky', notes + (notes === 1 ? ' note' : ' notes')]);
            if (instructions) lost.push(['bi-exclamation-triangle', instructions + (instructions === 1 ? ' instruction' : ' instructions')]);
            if (!lost.length) lost.push(['bi-pencil', 'Shift details']);

            const overlay = document.createElement('div');
            overlay.className = 'pms-dialog-backdrop sh-discard';
            overlay.innerHTML = `
                <div class="sh-discard-card" role="alertdialog" aria-modal="true" aria-labelledby="shDiscardTitle" aria-describedby="shDiscardText">
                    <div class="sh-discard-icon"><i class="bi bi-exclamation-lg"></i></div>
                    <h2 id="shDiscardTitle">${editing ? 'Leave without saving changes?' : 'Leave without saving?'}</h2>
                    <p id="shDiscardText">${editing ? 'Your changes to this Handover have not been saved.' : 'This Handover has not been saved yet.'} If you leave now, this will be lost:</p>
                    <ul class="sh-discard-list"></ul>
                    <div class="sh-discard-actions">
                        <button type="button" class="btn btn-p" data-keep><i class="bi bi-arrow-return-left"></i> Keep editing</button>
                        <button type="button" class="btn sh-discard-btn" data-discard>${editing ? 'Discard changes' : 'Discard Handover'}</button>
                    </div>
                </div>`;
            const listEl = overlay.querySelector('.sh-discard-list');
            lost.forEach(([icon, text]) => {
                const li = document.createElement('li');
                li.innerHTML = '<i class="bi ' + icon + '"></i><span></span>';
                li.querySelector('span').textContent = text;
                listEl.appendChild(li);
            });

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
                if (event.key === 'Tab') {
                    const buttons = [...overlay.querySelectorAll('button')];
                    const i = buttons.indexOf(document.activeElement);
                    event.preventDefault();
                    buttons[(i + (event.shiftKey ? -1 : 1) + buttons.length) % buttons.length].focus();
                }
            };
            overlay.querySelector('[data-keep]').addEventListener('click', () => close(false));
            overlay.querySelector('[data-discard]').addEventListener('click', () => close(true));
            overlay.addEventListener('mousedown', (event) => { if (event.target === overlay) close(false); });
            document.addEventListener('keydown', onKey, true);
            overlay.querySelector('[data-keep]').focus();
        });
    }

    // Closing with unsaved work asks first
    modal.addEventListener('hide.bs.modal', (event) => {
        if (!dirty || submitting || readOnly()) return;
        event.preventDefault();
        askDiscard().then((discard) => {
            if (!discard) return;
            dirty = false;
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        });
    });

    // Note points travel as hidden fields, one per point
    form.addEventListener('submit', () => {
        noteInputs.innerHTML = '';
        notePoints().forEach((html) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'notes[]';
            input.value = html;
            noteInputs.appendChild(input);
        });
        submitting = true;
    });

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
})();
