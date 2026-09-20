/* Roles and permissions.

   The ladder can be dragged, but never only dragged: every rung also has up
   and down buttons, which the keyboard reaches, because dragging is a poor
   way to give an instruction you cannot see the result of.

   Ticking a permission saves straight away and the counts follow, so the
   screen always shows what is true rather than what is about to be true. */
(function () {
    const ladder = document.getElementById('roleLadder');
    const panel = document.getElementById('rolePanel');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;

    const toast = (tone, message) => window.PMSToast
        ? window.PMSToast.push(tone, message)
        : (tone === 'error' ? window.alert(message) : null);

    async function send(url, body, method = 'POST') {
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'That did not save. Try again.');
        return data;
    }

    /* ----------------------------------------------------------- the ladder */

    if (ladder && ladder.dataset.canEdit === '1') {
        const pinned = (rung) => rung.classList.contains('pinned');
        const rungs = () => Array.from(ladder.querySelectorAll('.ac-rung'));

        let dragged = null;

        const save = async () => {
            ladder.classList.add('saving');
            try {
                const order = rungs().map((rung) => Number(rung.dataset.roleId));
                const data = await send(ladder.dataset.reorder, { order });
                paintLevels(data.ladder || []);
                toast('success', data.message || 'Ladder saved.');
            } catch (error) {
                toast('error', error.message);
                window.location.reload(); // the screen no longer matches the server
            } finally {
                ladder.classList.remove('saving');
            }
        };

        // The "3 allowed" line under each rung changes as the ladder does
        function paintLevels(entries) {
            entries.forEach((entry) => {
                const rung = ladder.querySelector(`[data-role-id="${entry.id}"] .ac-rung-meta`);
                if (!rung) return;
                rung.textContent = rung.textContent.replace(/\d+ allowed/, `${entry.inherited.length} allowed`);
            });
        }

        ladder.addEventListener('dragstart', (event) => {
            const rung = event.target.closest('.ac-rung');
            if (!rung || pinned(rung)) return event.preventDefault();
            dragged = rung;
            rung.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', rung.dataset.roleKey);
        });

        ladder.addEventListener('dragover', (event) => {
            if (!dragged) return;
            event.preventDefault();
            const over = event.target.closest('.ac-rung');
            if (!over || over === dragged || pinned(over)) return;

            const box = over.getBoundingClientRect();
            const above = event.clientY < box.top + box.height / 2;
            ladder.insertBefore(dragged, above ? over : over.nextSibling);
        });

        ladder.addEventListener('drop', (event) => event.preventDefault());

        ladder.addEventListener('dragend', () => {
            if (!dragged) return;
            dragged.classList.remove('dragging');
            dragged = null;
            save();
        });

        // Arrows: the same move, reachable by keyboard
        ladder.addEventListener('click', (event) => {
            const button = event.target.closest('[data-move]');
            if (!button) return;

            const rung = button.closest('.ac-rung');
            const up = button.dataset.move === 'up';
            const sibling = up ? rung.previousElementSibling : rung.nextElementSibling;
            if (!sibling || (up && pinned(sibling))) return;

            ladder.insertBefore(up ? rung : sibling, up ? sibling : rung);
            rung.querySelector(`[data-move="${button.dataset.move}"]`)?.focus();
            save();
        });
    }

    /* ------------------------------------------------- permissions on a role */

    if (panel && panel.dataset.permissionUrl) {
        panel.addEventListener('change', async (event) => {
            const input = event.target.closest('[data-permission]');
            if (!input) return;

            const wrap = input.closest('.ac-toggle, .ac-ability');
            wrap?.classList.add('saving');

            try {
                const data = await send(panel.dataset.permissionUrl, {
                    permission: input.dataset.permission,
                    granted: input.checked,
                });

                // Counts, and the "comes from" marks, follow what just changed
                setCount('[data-count-own]', data.own.length);
                setCount('[data-count-effective]', data.effective.length);
                setCount('[data-count-sensitive]', data.sensitive);
                if (wrap) wrap.classList.toggle('inherited', false);
                wrap?.classList.add('just-saved');
                setTimeout(() => wrap?.classList.remove('just-saved'), 900);
            } catch (error) {
                input.checked = !input.checked;   // put the tick back
                toast('error', error.message);
            } finally {
                wrap?.classList.remove('saving');
            }
        });

        const setCount = (selector, value) => {
            const el = panel.querySelector(selector);
            if (el) el.textContent = value;
        };
    }

    /* ------------------------------------ one person, given or refused a thing */

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.up-flip');
        if (!button) return;

        const list = button.closest('[data-user-permissions]');
        const cell = button.closest('.up-action');
        if (!list || !list.dataset.userPermissions || !cell) return;

        button.disabled = true;
        try {
            await send(list.dataset.userPermissions, {
                permission: cell.dataset.permission,
                state: button.dataset.state,
            });

            // What it now says has to match what was just saved, and the
            // simplest honest way to show that is to read it back
            toast('success', 'Saved. Reopening to show what changed.');
            window.location.reload();
        } catch (error) {
            button.disabled = false;
            toast('error', error.message);
        }
    });

    /* ---------------------------------------------------- the role's own form */

    const form = document.getElementById('roleForm');
    if (form) {
        const modal = document.getElementById('roleModal');
        const method = form.querySelector('[data-method]');
        const title = form.querySelector('[data-form-title]');
        const eyebrow = form.querySelector('[data-form-eyebrow]');
        const save = form.querySelector('[data-form-save]');
        const systemNote = form.querySelector('[data-system-note]');
        const createAction = form.getAttribute('action');

        const pick = (name, value) => {
            const input = form.querySelector(`input[name="${name}"][value="${value}"]`);
            if (input) input.checked = true;
        };

        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const raw = trigger?.dataset.roleEdit;
            const role = raw ? JSON.parse(raw) : null;

            form.action = role ? role.action : createAction;
            method.disabled = !role;
            title.textContent = role ? `Edit ${role.name}` : 'New Role';
            eyebrow.textContent = role ? 'Access control · Edit' : 'Access control · New';
            save.textContent = role ? 'Save Changes' : 'Create Role';

            form.elements.name.value = role ? role.name : '';
            form.elements.name.readOnly = !!(role && role.system);
            systemNote.hidden = !(role && role.system);
            form.elements.description.value = role ? (role.description || '') : '';
            form.elements.inherits.checked = role ? !!role.inherits : true;
            pick('icon', role ? role.icon : 'bi-person');
            pick('accent', role ? role.accent : '#6D28D9');
        });
    }
})();
