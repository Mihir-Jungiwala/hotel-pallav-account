/* ===========================================================================
   Hotel Pallav - forms

   Three things the browser does badly on its own:

   1. Conditional fields. Every "show this only when that" rule used to be its
      own inline script, so each behaved slightly differently and a field that
      was hidden could still be required - which stops submit on a control
      nobody can see. There is one declarative rule now, and hiding a field
      always releases its requirement.

   2. Validation messages. The native bubble is an operating-system popup: it
      cannot be themed, it vanishes on the next click, and it cannot be shown
      at all on a hidden control. Every form here is marked novalidate and the
      messages are drawn in the page instead.

   3. Select menus. A native <select> drops an OS menu that ignores the theme
      entirely. Anywhere the menu is part of the design, it is drawn here.
   =========================================================================== */
(function () {
    'use strict';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* -----------------------------------------------------------------------
       Conditional fields

           <div data-show-when="payment_mode=Bank">
           <div data-show-when="payment_status=Paid|Partially Paid">
           <div data-show-when="!use_company_signatory">     (unchecked)
           <div data-show-when="use_company_signatory">      (checked)

       The named control is looked up inside the same form, so the same rule
       can appear in a dozen modals on one page without them interfering.
       ----------------------------------------------------------------------- */

    function controlValue(control) {
        if (control.type === 'checkbox') return control.checked;
        if (control.type === 'radio') {
            const picked = (control.form || document).querySelector(
                'input[type=radio][name="' + CSS.escape(control.name) + '"]:checked'
            );
            return picked ? picked.value : '';
        }
        return control.value;
    }

    function ruleMatches(rule, control) {
        const value = controlValue(control);

        if (rule.negated) return !value;
        if (rule.expected === null) return !!value;

        return rule.expected.some((candidate) => String(value) === candidate);
    }

    function parseRule(raw) {
        let text = raw.trim();
        const negated = text.startsWith('!');
        if (negated) text = text.slice(1);

        const eq = text.indexOf('=');
        if (eq === -1) return { name: text, expected: null, negated };

        return {
            name: text.slice(0, eq).trim(),
            expected: text.slice(eq + 1).split('|').map((v) => v.trim()),
            negated: false,
        };
    }

    /**
     * Hiding a field must also release anything that would block submit on it:
     * a hidden required input is invalid forever and cannot be focused to say
     * so. The original state is parked on the element so showing it again
     * restores exactly what the server expects.
     */
    function setHidden(container, hidden) {
        container.hidden = hidden;

        container.querySelectorAll('input, select, textarea').forEach((field) => {
            if (hidden) {
                if (field.required) {
                    field.dataset.pmsWasRequired = '1';
                    field.required = false;
                }
                field.setCustomValidity('');
                clearFieldError(field);
            } else if (field.dataset.pmsWasRequired === '1') {
                field.required = true;
                delete field.dataset.pmsWasRequired;
            }
        });
    }

    function wireConditionalFields(root = document) {
        root.querySelectorAll('[data-show-when]').forEach((container) => {
            if (container.dataset.pmsConditional === '1') return;
            container.dataset.pmsConditional = '1';

            const rule = parseRule(container.dataset.showWhen);
            const scope = container.closest('form') || document;
            const controls = [...scope.querySelectorAll('[name="' + CSS.escape(rule.name) + '"]')];
            if (!controls.length) return;

            const sync = () => setHidden(container, !ruleMatches(rule, controls[0]));

            controls.forEach((control) => {
                control.addEventListener('change', sync);
                control.addEventListener('input', sync);
                // Tom Select writes through to the original and fires change,
                // but a programmatic .value = x does not - pages that prefill
                // a form dispatch this instead.
                control.addEventListener('pms:sync', sync);
            });

            sync();
        });
    }

    /* -----------------------------------------------------------------------
       Validation

       The browser still decides what is valid - required, type, min, max,
       step, pattern and maxlength all keep working - but it never draws the
       message, and a control nobody can see is never allowed to block submit.
       ----------------------------------------------------------------------- */

    const MESSAGES = {
        valueMissing: (field) => (field.tagName === 'SELECT' ? 'Choose an option.' : 'This is needed.'),
        typeMismatch: (field) => (field.type === 'email' ? 'That does not look like an email address.' : 'That value is not in the right format.'),
        patternMismatch: () => 'That is not in the expected format.',
        tooShort: (field) => 'Use at least ' + field.minLength + ' characters.',
        tooLong: (field) => 'Use at most ' + field.maxLength + ' characters.',
        rangeUnderflow: (field) => 'Must be ' + field.min + ' or more.',
        rangeOverflow: (field) => 'Must be ' + field.max + ' or less.',
        stepMismatch: () => 'That value is not allowed here.',
        badInput: () => 'Enter a number.',
    };

    function messageFor(field) {
        if (field.validity.customError) return field.validationMessage;

        // Fields that know what shape they want say so, rather than the generic
        // "not in the expected format"
        if (field.validity.patternMismatch && field.dataset.patternMessage) return field.dataset.patternMessage;

        for (const key of Object.keys(MESSAGES)) {
            if (field.validity[key]) return MESSAGES[key](field);
        }

        return field.validationMessage || 'Check this field.';
    }

    /** A field the user cannot see must never stop the form being submitted. */
    function isReachable(field) {
        if (field.disabled || field.type === 'hidden') return false;
        if (field.closest('[hidden]')) return false;
        // Tom Select and flatpickr park the original control off-screen while
        // showing their own; those are still very much reachable.
        if (field.classList.contains('tomselected') || field._flatpickr) return true;

        return field.offsetParent !== null || field.getClientRects().length > 0;
    }

    /** The element the message should sit under and the ring should go around. */
    function anchorFor(field) {
        if (field._flatpickr && field._flatpickr.altInput) return field._flatpickr.altInput;
        if (field.tomselect) return field.tomselect.wrapper;
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.closest('.form-check, .status-options, .option-chips') || field;
        }

        return field.closest('.input-group, .date-wrap, .phone-field') || field;
    }

    function clearFieldError(field) {
        const anchor = anchorFor(field);
        anchor.classList.remove('is-invalid-live');
        field.classList.remove('is-invalid-live');

        const holder = field.closest('.col-12, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, .mb-3') || field.parentElement;
        holder?.querySelector('.field-error')?.remove();
    }

    function showFieldError(field, message) {
        const anchor = anchorFor(field);
        anchor.classList.add('is-invalid-live');

        const holder = field.closest('.col-12, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, .mb-3') || field.parentElement;
        if (!holder) return;

        let note = holder.querySelector('.field-error');
        if (!note) {
            note = document.createElement('div');
            note.className = 'field-error';
            note.setAttribute('role', 'alert');
            // After the control, but before any helper text, so the error is
            // the first thing read under the field
            (anchor.nextElementSibling && holder.contains(anchor.nextElementSibling))
                ? anchor.after(note)
                : holder.appendChild(note);
        }

        note.innerHTML = '<i class="bi bi-exclamation-circle"></i><span></span>';
        note.querySelector('span').textContent = message;

        if (!field.id) field.id = 'f' + Math.random().toString(36).slice(2, 9);
        note.id = field.id + '_error';
        field.setAttribute('aria-describedby', note.id);
        field.setAttribute('aria-invalid', 'true');
    }

    /**
     * @returns {HTMLElement[]} the invalid fields, in document order
     */
    function invalidFields(scope) {
        return [...scope.querySelectorAll('input, select, textarea')]
            .filter((field) => isReachable(field) && !field.checkValidity());
    }

    function paint(field) {
        if (!isReachable(field)) { clearFieldError(field); return true; }

        if (field.checkValidity()) {
            clearFieldError(field);
            field.removeAttribute('aria-invalid');
            return true;
        }

        showFieldError(field, messageFor(field));
        return false;
    }

    /** Focus and reveal a field, whichever kind of control it has become. */
    function reveal(field) {
        const anchor = anchorFor(field);
        anchor.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });

        if (field._flatpickr && field._flatpickr.altInput) field._flatpickr.altInput.focus({ preventScroll: true });
        else if (field.tomselect) field.tomselect.focus();
        else field.focus({ preventScroll: true });
    }

    /**
     * Checks a form or a single wizard step and draws the messages.
     * @returns {boolean} true when everything reachable is valid
     */
    function validate(scope, { focus = true } = {}) {
        const bad = invalidFields(scope);

        // Repaint everything so messages that no longer apply disappear
        scope.querySelectorAll('input, select, textarea').forEach((field) => {
            if (!bad.includes(field)) { clearFieldError(field); field.removeAttribute('aria-invalid'); }
        });

        bad.forEach(paint);

        if (bad.length && focus) reveal(bad[0]);

        return bad.length === 0;
    }

    function wireValidation() {
        // The browser must stop drawing its own popup. Force mode already sets
        // this for its own reasons; setting it twice is harmless.
        document.querySelectorAll('form').forEach((form) => form.setAttribute('novalidate', 'novalidate'));

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.dataset.pmsNoValidate === 'true') return;
            // Force mode deliberately strips the rules; do not reinstate them
            if (document.body.dataset.forceMode === '1' && !form.hasAttribute('data-self-service')) return;

            // A wizard gets first refusal: it has to move to the right step
            // before a message is drawn, or the message lands out of sight.
            if (form.dataset.pmsWizardHandled === '1') return;

            if (!validate(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);

        // Once a field has been told off, correct it as they type
        document.addEventListener('input', (event) => {
            const field = event.target;
            if (!(field instanceof HTMLElement)) return;
            if (!field.matches('input, select, textarea')) return;
            if (!field.closest('form')) return;
            if (field.getAttribute('aria-invalid') !== 'true') return;
            paint(field);
        }, true);

        document.addEventListener('change', (event) => {
            const field = event.target;
            if (!(field instanceof HTMLElement)) return;
            if (!field.matches('input, select, textarea')) return;
            if (field.getAttribute('aria-invalid') !== 'true') return;
            paint(field);
        }, true);

        // Opening a form afresh should not show last time's complaints
        document.addEventListener('show.bs.modal', (event) => {
            event.target.querySelectorAll('[aria-invalid="true"]').forEach(clearFieldError);
            event.target.querySelectorAll('.field-error').forEach((note) => note.remove());
            wireConditionalFields(event.target);
        });
    }

    /* -----------------------------------------------------------------------
       Select menus drawn in the page

       Marked with data-pms-select. Tom Select already covers the big
       searchable pickers inside forms; this is for the small ones where an
       OS-drawn menu is the only thing on screen that ignores the theme.
       ----------------------------------------------------------------------- */

    function buildSelect(select) {
        if (select.dataset.pmsSelectReady === '1') return;
        select.dataset.pmsSelectReady = '1';

        const root = document.createElement('div');
        root.className = 'pms-select';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pms-select-btn';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        if (select.getAttribute('aria-label')) button.setAttribute('aria-label', select.getAttribute('aria-label'));

        const label = document.createElement('span');
        label.className = 'ps-label';
        button.appendChild(label);
        button.insertAdjacentHTML('beforeend', '<i class="bi bi-chevron-down ps-caret" aria-hidden="true"></i>');

        const menu = document.createElement('div');
        menu.className = 'pms-select-menu';
        menu.setAttribute('role', 'listbox');

        [...select.options].forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'ps-option';
            item.setAttribute('role', 'option');
            item.dataset.value = option.value;
            item.textContent = option.textContent;
            menu.appendChild(item);
        });

        select.parentNode.insertBefore(root, select);
        root.append(button, menu, select);
        select.classList.add('pms-select-native');

        const paintLabel = () => {
            label.textContent = select.options[select.selectedIndex]?.textContent ?? '';
            menu.querySelectorAll('.ps-option').forEach((item) => {
                const on = item.dataset.value === select.value;
                item.classList.toggle('selected', on);
                item.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        };

        const close = () => {
            root.classList.remove('open');
            button.setAttribute('aria-expanded', 'false');
        };

        const open = () => {
            // A menu near the bottom of the window should rise instead
            const space = window.innerHeight - button.getBoundingClientRect().bottom;
            root.classList.toggle('drop-up', space < 210);
            root.classList.add('open');
            button.setAttribute('aria-expanded', 'true');
            menu.querySelector('.ps-option.selected')?.focus({ preventScroll: true });
        };

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            root.classList.contains('open') ? close() : open();
        });

        menu.addEventListener('click', (event) => {
            const item = event.target.closest('.ps-option');
            if (!item) return;
            select.value = item.dataset.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            paintLabel();
            close();
            button.focus({ preventScroll: true });
        });

        menu.addEventListener('keydown', (event) => {
            const items = [...menu.querySelectorAll('.ps-option')];
            const at = items.indexOf(document.activeElement);

            if (event.key === 'ArrowDown') { event.preventDefault(); items[Math.min(at + 1, items.length - 1)]?.focus(); }
            if (event.key === 'ArrowUp') { event.preventDefault(); items[Math.max(at - 1, 0)]?.focus(); }
            if (event.key === 'Escape') { event.preventDefault(); close(); button.focus(); }
        });

        button.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) close();
        });

        select.addEventListener('pms:sync', paintLabel);
        paintLabel();
    }

    function wireCustomSelects(root = document) {
        root.querySelectorAll('select[data-pms-select]').forEach(buildSelect);
    }

    /* -----------------------------------------------------------------------
       Profile photo, with a crop step

       Choosing a picture opens a small editor: drag to position, slide to
       zoom, and Save photo keeps exactly what is inside the circle. What is
       submitted is that cropped square, resized and compressed, not the
       original - so a 9 MB phone photo becomes a ~100 KB avatar, and the
       server never sees anything it would refuse for size.
       ----------------------------------------------------------------------- */

    const PHOTO_SOURCE_MAX = 15 * 1024 * 1024;   // what we will open to crop
    const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const PHOTO_OUTPUT = 512;                    // saved square, in pixels

    /**
     * Opens the crop editor for a picture.
     * @returns {Promise<Blob|null>} the cropped square, or null if cancelled
     */
    function cropPhoto(sourceUrl) {
        return new Promise((resolve) => {
            const STAGE = 300;
            const previouslyFocused = document.activeElement;

            const overlay = document.createElement('div');
            overlay.className = 'pms-dialog-backdrop pms-crop-backdrop';
            overlay.innerHTML =
                '<div class="pms-dialog pms-crop" role="dialog" aria-modal="true" aria-labelledby="pmsCropTitle">' +
                    '<div class="pc-head"><h2 class="pd-title" id="pmsCropTitle">Adjust photo</h2>' +
                    '<p class="pd-message">Drag to move it, use the slider to zoom. What is inside the circle is what gets saved.</p></div>' +
                    '<div class="pc-stage" style="width:' + STAGE + 'px;height:' + STAGE + 'px;">' +
                        '<img class="pc-image" alt="" draggable="false">' +
                        '<div class="pc-mask" aria-hidden="true"></div>' +
                    '</div>' +
                    '<div class="pc-zoom">' +
                        '<i class="bi bi-image" aria-hidden="true"></i>' +
                        '<input type="range" class="pc-range" min="1" max="4" step="0.01" value="1" aria-label="Zoom">' +
                        '<i class="bi bi-image-fill" aria-hidden="true"></i>' +
                    '</div>' +
                    '<div class="pd-actions">' +
                        '<button type="button" class="btn btn-ghost pc-cancel">Cancel</button>' +
                        '<button type="button" class="btn btn-p pc-save"><i class="bi bi-check2"></i> Save photo</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(overlay);
            document.body.classList.add('pms-dialog-open');
            requestAnimationFrame(() => overlay.classList.add('in'));

            const img = overlay.querySelector('.pc-image');
            const range = overlay.querySelector('.pc-range');
            const stage = overlay.querySelector('.pc-stage');
            const save = overlay.querySelector('.pc-save');

            let base = 1;          // scale at which the picture just covers the stage
            let zoom = 1;          // 1 = covers exactly, up to 4x beyond that
            let ox = 0, oy = 0;    // top-left of the picture inside the stage
            let ready = false;

            const clamp = () => {
                const w = img.naturalWidth * base * zoom;
                const h = img.naturalHeight * base * zoom;
                // The picture must always cover the whole circle: never show a gap
                ox = Math.min(0, Math.max(STAGE - w, ox));
                oy = Math.min(0, Math.max(STAGE - h, oy));
            };

            const paint = () => {
                clamp();
                const s = base * zoom;
                img.style.width = (img.naturalWidth * s) + 'px';
                img.style.height = (img.naturalHeight * s) + 'px';
                img.style.transform = 'translate(' + ox + 'px,' + oy + 'px)';
            };

            /** Zooms about a point in the stage, so the picture does not slide away. */
            const setZoom = (next, cx = STAGE / 2, cy = STAGE / 2) => {
                next = Math.min(4, Math.max(1, next));
                const before = base * zoom;
                const after = base * next;
                // The picture coordinate under (cx, cy) stays under it
                const px = (cx - ox) / before;
                const py = (cy - oy) / before;
                zoom = next;
                ox = cx - px * after;
                oy = cy - py * after;
                range.value = String(zoom);
                paint();
            };

            img.addEventListener('load', () => {
                base = Math.max(STAGE / img.naturalWidth, STAGE / img.naturalHeight);
                zoom = 1;
                ox = (STAGE - img.naturalWidth * base) / 2;
                oy = (STAGE - img.naturalHeight * base) / 2;
                ready = true;
                save.disabled = false;
                paint();
            });
            img.addEventListener('error', () => finish(null));
            save.disabled = true;
            img.src = sourceUrl;

            range.addEventListener('input', () => setZoom(parseFloat(range.value)));

            // Drag to move
            let drag = null;
            stage.addEventListener('pointerdown', (e) => {
                if (!ready) return;
                drag = { x: e.clientX, y: e.clientY, ox, oy };
                stage.setPointerCapture(e.pointerId);
                stage.classList.add('dragging');
            });
            stage.addEventListener('pointermove', (e) => {
                if (!drag) return;
                ox = drag.ox + (e.clientX - drag.x);
                oy = drag.oy + (e.clientY - drag.y);
                paint();
            });
            const endDrag = () => { drag = null; stage.classList.remove('dragging'); };
            stage.addEventListener('pointerup', endDrag);
            stage.addEventListener('pointercancel', endDrag);

            // Scroll wheel zooms
            stage.addEventListener('wheel', (e) => {
                if (!ready) return;
                e.preventDefault();
                const rect = stage.getBoundingClientRect();
                setZoom(zoom * (e.deltaY < 0 ? 1.08 : 1 / 1.08), e.clientX - rect.left, e.clientY - rect.top);
            }, { passive: false });

            // Arrow keys nudge, for anyone without a mouse
            const onKey = (e) => {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); finish(null); return; }
                const step = e.shiftKey ? 24 : 8;
                const move = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] }[e.key];
                if (move && ready && !(e.target instanceof HTMLInputElement)) {
                    e.preventDefault(); ox += move[0]; oy += move[1]; paint();
                }
            };
            document.addEventListener('keydown', onKey, true);

            function finish(blob) {
                overlay.classList.remove('in');
                document.removeEventListener('keydown', onKey, true);
                setTimeout(() => {
                    overlay.remove();
                    if (!document.querySelector('.pms-dialog-backdrop')) document.body.classList.remove('pms-dialog-open');
                    previouslyFocused?.focus?.({ preventScroll: true });
                }, reduceMotion ? 0 : 180);
                resolve(blob);
            }

            overlay.querySelector('.pc-cancel').addEventListener('click', () => finish(null));
            overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) finish(null); });

            save.addEventListener('click', () => {
                if (!ready) return;
                const s = base * zoom;
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = PHOTO_OUTPUT;
                const ctx = canvas.getContext('2d');
                ctx.imageSmoothingQuality = 'high';
                // The part of the original that sits inside the stage
                ctx.drawImage(img, -ox / s, -oy / s, STAGE / s, STAGE / s, 0, 0, PHOTO_OUTPUT, PHOTO_OUTPUT);
                canvas.toBlob((blob) => finish(blob), 'image/jpeg', 0.9);
            });

            save.focus();
        });
    }

    function wirePhotoFields(root = document) {
        root.querySelectorAll('[data-photo-field]').forEach((field) => {
            if (field.dataset.pmsPhotoReady === '1') return;
            field.dataset.pmsPhotoReady = '1';

            const input = field.querySelector('[data-photo-input]');
            const drop = field.querySelector('.photo-drop');
            const preview = field.querySelector('.photo-preview');
            const placeholder = field.querySelector('.photo-placeholder');
            const clear = field.querySelector('[data-photo-clear]');
            const adjust = field.querySelector('[data-photo-adjust]');
            const nameOut = field.querySelector('[data-photo-name]');
            const label = field.querySelector('[data-photo-label]');
            if (!input || !preview) return;

            // What was showing before anything was chosen, so Undo can go back
            const original = { src: preview.getAttribute('src') || '', had: !preview.hidden, label: label?.textContent };
            let previewUrl = null;   // the cropped result on show
            let sourceUrl = null;    // the picture as chosen, kept so it can be re-cropped

            const revoke = () => {
                if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
                if (sourceUrl) { URL.revokeObjectURL(sourceUrl); sourceUrl = null; }
            };

            const error = (message) => {
                let note = field.querySelector('.field-error');
                if (!message) { note?.remove(); drop.classList.remove('is-invalid-live'); return; }
                if (!note) {
                    note = document.createElement('div');
                    note.className = 'field-error';
                    note.setAttribute('role', 'alert');
                    field.querySelector('.photo-text').appendChild(note);
                }
                note.innerHTML = '<i class="bi bi-exclamation-circle"></i><span></span>';
                note.querySelector('span').textContent = message;
                drop.classList.add('is-invalid-live');
            };

            const restore = () => {
                revoke();
                input.value = '';
                preview.src = original.src;
                preview.hidden = !original.had;
                placeholder.hidden = original.had;
                drop.classList.toggle('has-photo', original.had);
                if (label) label.textContent = original.label;
                clear.hidden = true;
                if (adjust) adjust.hidden = true;
                nameOut.hidden = true;
                error(null);
            };

            /** Puts the cropped square in the form field, and on show. */
            const apply = (blob) => {
                const file = new File([blob], 'profile-photo.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;

                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = URL.createObjectURL(blob);

                preview.src = previewUrl;
                preview.hidden = false;
                placeholder.hidden = true;
                drop.classList.add('has-photo', 'just-set');
                setTimeout(() => drop.classList.remove('just-set'), 500);

                if (label) label.textContent = 'Choose another';
                clear.hidden = false;
                if (adjust) adjust.hidden = false;
                nameOut.hidden = false;
                nameOut.textContent = 'Cropped ' + PHOTO_OUTPUT + ' × ' + PHOTO_OUTPUT + ' · ' + Math.max(1, Math.round(blob.size / 1024)) + ' KB';
                error(null);
            };

            const openCropper = async () => {
                if (!sourceUrl) return;
                const blob = await cropPhoto(sourceUrl);
                if (blob) apply(blob);
            };

            input.addEventListener('change', async () => {
                const file = input.files && input.files[0];
                if (!file) { restore(); return; }

                // A file the crop step produced comes back through here once
                // it is placed in the input; it is already done, so leave it.
                if (file.name === 'profile-photo.jpg' && previewUrl) return;

                if (!PHOTO_TYPES.includes(file.type)) {
                    restore();
                    error('Choose a JPG, PNG or WebP image.');
                    return;
                }
                if (file.size > PHOTO_SOURCE_MAX) {
                    restore();
                    error('That photo is ' + (file.size / 1048576).toFixed(1) + ' MB. Choose one under 15 MB.');
                    return;
                }

                error(null);
                if (sourceUrl) URL.revokeObjectURL(sourceUrl);
                sourceUrl = URL.createObjectURL(file);

                // Leave the field empty until they Save: cancelling the crop
                // must not submit the uncropped original.
                input.value = '';
                const blob = await cropPhoto(sourceUrl);
                if (blob) apply(blob);
                else if (!previewUrl) restore();
            });

            clear.addEventListener('click', restore);
            adjust?.addEventListener('click', openCropper);

            // Drag a photo straight onto the circle
            ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (e) => {
                e.preventDefault();
                drop.classList.add('drag-over');
            }));
            ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, (e) => {
                e.preventDefault();
                drop.classList.remove('drag-over');
            }));
            drop.addEventListener('drop', (e) => {
                if (!e.dataTransfer?.files?.length) return;
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            // A form opened afresh starts from the saved photo again
            input.form?.addEventListener('reset', () => setTimeout(restore));
            field.closest('.modal')?.addEventListener('hidden.bs.modal', restore);
        });
    }


    /* -----------------------------------------------------------------------
       Phone numbers, with a country

       A chip shows the country code and opens a searchable list; the number
       sits beside it. India is preselected. Typing or pasting a number that
       begins with "+" or "00" names its own country ("+971 50 123 4567"
       switches the chip to the UAE and keeps the rest as the number), and
       whatever is typed is reduced to digits as it arrives.

       The countries and their number rules come from the server (see
       PhoneCountries.php), so the browser and the server always agree about
       what a valid number is. The server normalises again regardless.
       ----------------------------------------------------------------------- */

    let phoneList = null;

    function phoneCountries() {
        if (phoneList) return phoneList;
        try { phoneList = JSON.parse(document.getElementById('pmsPhoneCountries').textContent); }
        catch (e) { phoneList = []; }
        return phoneList;
    }

    const countryByDial = (dial) => phoneCountries().find((c) => c.dial === String(dial)) || null;

    /** "+971 50 123 4567" -> { country, national }; null if it does not start that way. */
    function splitInternational(raw) {
        const text = raw.trim();
        if (!(text.startsWith('+') || text.startsWith('00'))) return null;

        let digits = text.replace(/\D+/g, '');
        if (text.startsWith('00')) digits = digits.slice(2);

        // The longest code that fits, so +9198... is India and not something starting with 9
        const dials = phoneCountries().map((c) => c.dial).sort((a, b) => b.length - a.length);
        const dial = dials.find((d) => digits.startsWith(d));

        return dial ? { country: countryByDial(dial), national: digits.slice(dial.length) } : null;
    }

    /** Trunk zero (098765...) and a code pasted without its "+": the same rules as the server. */
    function tidyNational(digits, country) {
        if (digits.length === country.max + 1 && digits.startsWith('0')) {
            digits = digits.slice(1);
        } else if (digits.length > country.max && digits.startsWith(country.dial)) {
            const rest = digits.length - country.dial.length;
            if (rest >= country.min && rest <= country.max) digits = digits.slice(country.dial.length);
        }
        // Room for a pasted code the server will strip, and no more
        return digits.slice(0, country.max + country.dial.length);
    }

    function phonePattern(country) {
        return (country.lead || '[0-9]') + '[0-9]{' + (country.min - 1) + ',' + (country.max - 1) + '}';
    }

    function phoneMessage(country) {
        if (country.iso === 'IN') return 'Enter a 10-digit mobile number starting with 6, 7, 8 or 9.';
        const wanted = country.min === country.max ? String(country.min) : country.min + ' to ' + country.max;
        return 'Enter a valid ' + country.name + ' number of ' + wanted + ' digits.';
    }

    /* ---- the one shared list ---- */

    let phoneMenu = null;
    let phoneMenuOwner = null;

    function closePhoneMenu() {
        if (!phoneMenu || !phoneMenuOwner) return;
        phoneMenu.classList.remove('open');
        phoneMenuOwner.button.setAttribute('aria-expanded', 'false');
        const owner = phoneMenuOwner;
        phoneMenuOwner = null;
        setTimeout(() => { if (!phoneMenuOwner) phoneMenu.hidden = true; }, reduceMotion ? 0 : 140);
        return owner;
    }

    function buildPhoneMenu() {
        if (phoneMenu) return phoneMenu;

        phoneMenu = document.createElement('div');
        phoneMenu.className = 'phone-menu';
        phoneMenu.hidden = true;
        phoneMenu.innerHTML =
            '<div class="pm-search"><i class="bi bi-search" aria-hidden="true"></i>' +
            '<input type="text" placeholder="Search country or code" aria-label="Search country or code" autocomplete="off" spellcheck="false"></div>' +
            '<div class="pm-list" role="listbox"></div>' +
            '<div class="pm-empty" hidden>No country matches that.</div>';

        const list = phoneMenu.querySelector('.pm-list');
        phoneCountries().forEach((c) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'pm-item';
            item.setAttribute('role', 'option');
            item.dataset.dial = c.dial;
            item.dataset.search = c.name + ' ' + c.iso + ' +' + c.dial;
            item.innerHTML = '<span class="pm-iso"></span><span class="pm-name"></span><span class="pm-code"></span>';
            item.querySelector('.pm-iso').textContent = c.iso;
            item.querySelector('.pm-name').textContent = c.name;
            item.querySelector('.pm-code').textContent = '+' + c.dial;
            list.appendChild(item);
        });

        const search = phoneMenu.querySelector('.pm-search input');
        const empty = phoneMenu.querySelector('.pm-empty');
        const visible = () => [...list.querySelectorAll('.pm-item:not([hidden])')];

        search.addEventListener('input', () => {
            const term = search.value.trim();
            let shown = 0;
            list.querySelectorAll('.pm-item').forEach((item) => {
                const ok = !term || (window.PMSSearch
                    ? window.PMSSearch.textMatches(term, item.dataset.search)
                    : item.dataset.search.toLowerCase().includes(term.toLowerCase()));
                item.hidden = !ok;
                if (ok) shown++;
            });
            empty.hidden = shown !== 0;
        });

        phoneMenu.addEventListener('click', (event) => {
            const item = event.target.closest('.pm-item');
            if (!item || !phoneMenuOwner) return;
            const owner = closePhoneMenu();
            owner.setCountry(countryByDial(item.dataset.dial), true);
            owner.number.focus();
        });

        phoneMenu.addEventListener('keydown', (event) => {
            const items = visible();
            const at = items.indexOf(document.activeElement);

            if (event.key === 'ArrowDown') { event.preventDefault(); (items[at + 1] || items[0])?.focus(); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); (at <= 0 ? search : items[at - 1])?.focus(); }
            else if (event.key === 'Enter' && document.activeElement === search) { event.preventDefault(); items[0]?.click(); }
            else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                const owner = closePhoneMenu();
                owner?.button.focus();
            }
        });

        document.addEventListener('mousedown', (event) => {
            if (!phoneMenuOwner) return;
            if (phoneMenu.contains(event.target) || phoneMenuOwner.button.contains(event.target)) return;
            closePhoneMenu();
        });
        window.addEventListener('resize', () => closePhoneMenu());
        // Scrolling the form moves the chip out from under a fixed list
        document.addEventListener('scroll', (event) => {
            if (phoneMenuOwner && !phoneMenu.contains(event.target)) closePhoneMenu();
        }, true);

        document.body.appendChild(phoneMenu);
        return phoneMenu;
    }

    function openPhoneMenu(owner) {
        const menu = buildPhoneMenu();
        phoneMenuOwner = owner;

        const search = menu.querySelector('.pm-search input');
        search.value = '';
        search.dispatchEvent(new Event('input'));

        menu.querySelectorAll('.pm-item').forEach((item) => {
            const on = item.dataset.dial === owner.hidden.value;
            item.classList.toggle('selected', on);
            item.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        menu.hidden = false;
        const rect = owner.button.getBoundingClientRect();
        const width = Math.max(300, rect.width);
        menu.style.width = width + 'px';
        menu.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';

        // Below the chip, or above it when there is no room
        const height = menu.offsetHeight;
        const below = window.innerHeight - rect.bottom;
        menu.style.top = (below < height + 16 && rect.top > below ? Math.max(8, rect.top - height - 6) : rect.bottom + 6) + 'px';

        requestAnimationFrame(() => menu.classList.add('open'));
        owner.button.setAttribute('aria-expanded', 'true');

        const selected = menu.querySelector('.pm-item.selected');
        selected?.scrollIntoView({ block: 'center' });
        search.focus({ preventScroll: true });
    }

    function wirePhoneFields(root = document) {
        root.querySelectorAll('[data-phone]').forEach((field) => {
            if (field.dataset.pmsPhoneReady === '1') return;
            field.dataset.pmsPhoneReady = '1';

            const button = field.querySelector('.phone-country');
            const hidden = field.querySelector('[data-phone-country]');
            const number = field.querySelector('[data-phone-number]');
            if (!button || !hidden || !number) return;

            const owner = { button, hidden, number, setCountry };

            /** Changes the chip, the rule the number is held to, and the example shown. */
            function setCountry(country, retidy) {
                if (!country) return;
                hidden.value = country.dial;
                button.querySelector('.pc-iso').textContent = country.iso;
                button.querySelector('.pc-dial').textContent = '+' + country.dial;
                button.setAttribute('aria-label', 'Country code, ' + country.name + ' plus ' + country.dial + '. Change');
                number.placeholder = country.sample;
                number.pattern = phonePattern(country);
                number.dataset.patternMessage = phoneMessage(country);

                if (!retidy) return;

                if (number.value) number.value = tidyNational(number.value.replace(/\D+/g, ''), country);
                // A message already showing may no longer be true for this country
                number.dispatchEvent(new Event('input', { bubbles: true }));
            }

            number.addEventListener('input', () => {
                const raw = number.value;
                const intl = splitInternational(raw);
                let country = countryByDial(hidden.value) || countryByDial('91');

                if (intl && intl.country) {
                    // "+971..." names its own country
                    if (intl.country.dial !== hidden.value) {
                        hidden.value = intl.country.dial;
                        button.querySelector('.pc-iso').textContent = intl.country.iso;
                        button.querySelector('.pc-dial').textContent = '+' + intl.country.dial;
                        number.placeholder = intl.country.sample;
                        number.pattern = phonePattern(intl.country);
                        number.dataset.patternMessage = phoneMessage(intl.country);
                    }
                    country = intl.country;
                    number.value = tidyNational(intl.national, country);
                    return;
                }

                // Still typing a "+" or "00" before the code is complete: leave it alone
                if (/^\s*(\+|00)/.test(raw)) {
                    const kept = raw.replace(/[^\d+\s-]/g, '');
                    if (kept !== raw) number.value = kept;
                    return;
                }

                const clean = tidyNational(raw.replace(/\D+/g, ''), country);
                if (clean !== raw) number.value = clean;
            });

            button.addEventListener('click', () => {
                phoneMenuOwner === owner ? closePhoneMenu() : openPhoneMenu(owner);
            });
            button.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown') { event.preventDefault(); openPhoneMenu(owner); }
            });

            // Start from what the server rendered, so the rule matches the chip
            setCountry(countryByDial(hidden.value) || countryByDial('91'), false);
        });
    }

    /* -----------------------------------------------------------------------
       Boot
       ----------------------------------------------------------------------- */

    function boot() {
        wireConditionalFields();
        wireValidation();
        wireCustomSelects();
        wirePhotoFields();
        wirePhoneFields();

        window.PMSForms = {
            validate,
            refresh(root) { wireConditionalFields(root); wireCustomSelects(root); wirePhotoFields(root); wirePhoneFields(root); },
            clear(field) { clearFieldError(field); },
            reveal,
            isReachable,
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
