/* ===========================================================================
   Hotel Pallav - smart search

   Every payroll search box shares this one engine. It replaces a plain
   "does this text contain what was typed" check with something closer to how
   people actually look for things:

     asha cook          every word must match, in any order
     "front office"     an exact phrase
     -inactive          leave out anything containing that word
     >20000  <5000      amounts above / below
     10000-25000        amounts between
     today  yesterday   dates (only where the table has dates)
     this week  this month  last month
     menno              typos are forgiven: finds Menon

   Everything visible in a row is searchable, not just the columns someone
   remembered to list: names, codes, amounts as printed (24,000 or 24000),
   dates, statuses, phone numbers and emails.

   The matching part has no dependency on the page, so it is tested on its own
   (tests/js/search.test.cjs). The rest of the file is the search box itself:
   highlight, clear button, "did you mean", tips, and the "/" shortcut.
   =========================================================================== */
(function (root) {
    'use strict';

    const DATE_RE = /^(20\d{2})(\d{2})(\d{2})(\d{4})?$/;
    const NUM_RE = /^-?\d+(\.\d+)?$/;

    /* -----------------------------------------------------------------------
       Text
       ----------------------------------------------------------------------- */

    /**
     * Lower-case, strip accents, drop the commas inside numbers (24,000 and
     * 1,50,000 both become plain digits) and turn punctuation into spaces.
     * Letters and marks from any script are kept, so names written in Gujarati
     * or Hindi search as well as English ones.
     */
    function normalise(value) {
        return String(value == null ? '' : value)
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/(\d),(?=\d)/g, '$1')
            .replace(/[^\p{L}\p{M}\p{N}@._+\-\s]/gu, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /** The words of a normalised string, with email/number parts split out too. */
    function wordsOf(text) {
        const out = new Set();
        for (const base of text.split(' ')) {
            if (!base) continue;
            out.add(base);
            for (const part of base.split(/[@._+\-]+/)) if (part) out.add(part);
        }
        return [...out];
    }

    /**
     * What is known about one row.
     * @param {{text: string, numbers?: number[], dates?: number[]}} source
     */
    function makeIndex(source) {
        const text = normalise(source.text);
        return {
            text,
            words: wordsOf(text),
            compact: text.replace(/\s+/g, ''),
            numbers: source.numbers || [],
            dates: source.dates || [],
        };
    }

    /** Optimal-string-alignment distance (edits, and swapped neighbours), with an early exit. */
    function distance(a, b, max) {
        if (Math.abs(a.length - b.length) > max) return max + 1;
        let prev2 = null;
        let prev = Array.from({ length: b.length + 1 }, (_, j) => j);

        for (let i = 1; i <= a.length; i++) {
            const cur = [i];
            let rowMin = i;

            for (let j = 1; j <= b.length; j++) {
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                let v = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + cost);
                if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                    v = Math.min(v, prev2[j - 2] + 1);
                }
                cur[j] = v;
                if (v < rowMin) rowMin = v;
            }

            if (rowMin > max) return max + 1;
            prev2 = prev;
            prev = cur;
        }

        return prev[b.length];
    }

    /** How many typos a word of this length may carry and still match. */
    function allowance(length) {
        if (length < 4) return 0;
        return length >= 8 ? 2 : 1;
    }

    /* -----------------------------------------------------------------------
       Dates
       ----------------------------------------------------------------------- */

    const ymd = (d) => d.getFullYear() * 10000 + (d.getMonth() + 1) * 100 + d.getDate();

    function dateWindow(keyword, now) {
        const day = new Date(now.getFullYear(), now.getMonth(), now.getDate());

        if (keyword === 'today') return [ymd(day), ymd(day)];
        if (keyword === 'yesterday') {
            const y = new Date(day); y.setDate(y.getDate() - 1);
            return [ymd(y), ymd(y)];
        }
        if (keyword === 'week') {
            const start = new Date(day);
            start.setDate(start.getDate() - ((start.getDay() + 6) % 7)); // Monday
            const end = new Date(start); end.setDate(end.getDate() + 6);
            return [ymd(start), ymd(end)];
        }
        if (keyword === 'month') {
            return [ymd(new Date(day.getFullYear(), day.getMonth(), 1)), ymd(new Date(day.getFullYear(), day.getMonth() + 1, 0))];
        }
        if (keyword === 'lastmonth') {
            return [ymd(new Date(day.getFullYear(), day.getMonth() - 1, 1)), ymd(new Date(day.getFullYear(), day.getMonth(), 0))];
        }
        return null;
    }

    /* -----------------------------------------------------------------------
       Query
       ----------------------------------------------------------------------- */

    /**
     * Splits what was typed into tokens.
     * @param {string} query
     * @param {{hasDates?: boolean}} ctx
     */
    function parse(query, ctx) {
        let s = String(query == null ? '' : query).trim();

        // Phrases that mean a date window, so they read as one thing
        if (ctx && ctx.hasDates) {
            s = s.replace(/\bthis\s+month\b/gi, '@month').replace(/\blast\s+month\b/gi, '@lastmonth')
                .replace(/\bthis\s+week\b/gi, '@week').replace(/\btoday\b/gi, '@today').replace(/\byesterday\b/gi, '@yesterday');
        }

        const tokens = [];
        const pattern = /(-?)"([^"]*)"|(\S+)/g;
        let m;

        while ((m = pattern.exec(s)) !== null) {
            if (m[2] !== undefined) {
                const text = normalise(m[2]);
                if (text) tokens.push({ type: 'phrase', text, neg: m[1] === '-', raw: m[2] });
                continue;
            }

            let word = m[3];
            let neg = false;
            if (word.length > 1 && word[0] === '-' && !NUM_RE.test(word)) { neg = true; word = word.slice(1); }

            let t;
            if ((t = word.match(/^(>=|<=|>|<)(\d+(?:\.\d+)?)$/))) {
                tokens.push({ type: 'cmp', op: t[1], n: parseFloat(t[2]), neg, raw: word });
            } else if ((t = word.match(/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/)) && parseFloat(t[1]) < parseFloat(t[2])) {
                tokens.push({ type: 'range', lo: parseFloat(t[1]), hi: parseFloat(t[2]), neg, raw: word });
            } else if (/^@(today|yesterday|week|month|lastmonth)$/.test(word)) {
                tokens.push({ type: 'date', keyword: word.slice(1), neg, raw: word });
            } else {
                const text = normalise(word);
                // A lone dash or dot is punctuation, not something to look for
                if (text && /[\p{L}\p{N}]/u.test(text)) tokens.push({ type: 'word', text, neg, raw: word });
            }
        }

        return tokens;
    }

    /**
     * Turns a query into a tester.
     *
     * @param {string} query
     * @param {{hasDates?: boolean, vocab?: Set<string>, now?: Date}} ctx
     *        vocab - every whole word in the table. A word someone typed in
     *        full ("active") must not also match the middle of another word
     *        ("inactive"), but a fragment ("sha" in "Asha") should.
     */
    function compile(query, ctx) {
        ctx = ctx || {};
        const now = ctx.now || new Date();
        const tokens = parse(query, ctx);
        const vocab = ctx.vocab || null;

        const matchWord = (t, idx) => {
            // Start of any word, which covers the whole word too
            if (idx.words.some((w) => w.startsWith(t))) return true;

            // Inside a word, unless the token is itself a whole word somewhere
            // in this table - then it was meant as that word
            const wholeWord = vocab ? vocab.has(t) : false;
            if (!wholeWord && t.length >= 3 && idx.text.includes(t)) return true;

            // A number typed with spaces or dashes against one stored without
            if (/^\d{4,}$/.test(t) && idx.compact.includes(t)) return true;

            // Typos - but never for a word this table knows exactly: "inactive"
            // is two edits from "active", and they are different things
            const room = wholeWord ? 0 : allowance(t.length);
            if (room > 0) {
                return idx.words.some((w) => w.length >= 3
                    && (distance(t, w, room) <= room || (w.length > t.length && distance(t, w.slice(0, t.length), room) <= room)));
            }

            return false;
        };

        const matchToken = (tok, idx) => {
            switch (tok.type) {
                case 'phrase': return idx.text.includes(tok.text);
                case 'cmp':
                    return idx.numbers.some((n) => {
                        if (tok.op === '>') return n > tok.n;
                        if (tok.op === '<') return n < tok.n;
                        if (tok.op === '>=') return n >= tok.n;
                        return n <= tok.n;
                    });
                case 'range': return idx.numbers.some((n) => n >= tok.lo && n <= tok.hi);
                case 'date': {
                    const win = dateWindow(tok.keyword, now);
                    return !!win && idx.dates.some((d) => d >= win[0] && d <= win[1]);
                }
                default: return matchWord(tok.text, idx);
            }
        };

        return {
            tokens,
            empty: tokens.length === 0,
            /** Words worth highlighting where they appear as typed. */
            terms: tokens.filter((t) => !t.neg && (t.type === 'word' || t.type === 'phrase')).map((t) => t.raw),
            test(idx) {
                for (const tok of tokens) {
                    const hit = matchToken(tok, idx);
                    if (tok.neg ? hit : !hit) return false;
                }
                return true;
            },
        };
    }

    /** One line of text against a query - for lists that are not tables. */
    function textMatches(query, text) {
        const q = compile(query, {});
        return q.empty || q.test(makeIndex({ text }));
    }

    /**
     * "Did you mean...?" - the nearest real word for the first typed word
     * that matches nothing at all.
     * @returns {{from: string, to: string, query: string}|null}
     */
    function suggest(query, vocab) {
        const tokens = parse(query, {}).filter((t) => t.type === 'word' && !t.neg && t.text.length >= 3);

        for (const tok of tokens) {
            let known = false;
            for (const w of vocab) { if (w.startsWith(tok.text) || (tok.text.length >= 3 && w.includes(tok.text))) { known = true; break; } }
            if (known) continue;

            let best = null;
            let bestD = 3;
            for (const w of vocab) {
                if (w.length < 3 || Math.abs(w.length - tok.text.length) > 2) continue;
                const d = Math.min(distance(tok.text, w, 2), w.length > tok.text.length ? distance(tok.text, w.slice(0, tok.text.length), 2) : 3);
                if (d < bestD) { bestD = d; best = w; if (d === 1) break; }
            }

            if (best) {
                const escaped = tok.raw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return { from: tok.raw, to: best, query: String(query).replace(new RegExp(escaped, 'i'), best) };
            }
        }

        return null;
    }

    const api = { normalise, makeIndex, compile, parse, textMatches, suggest, distance, wordsOf };

    /* -----------------------------------------------------------------------
       Node (the tests) stops here. Below is the page.
       ----------------------------------------------------------------------- */

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
        return;
    }

    if (typeof document === 'undefined') { root.PMSSearch = api; return; }

    const rowCache = new WeakMap();
    const tableCache = new WeakMap();

    /** Everything a row shows, plus the hidden search text it was given. */
    api.index = function (row) {
        let idx = rowCache.get(row);
        if (idx) return idx;

        const numbers = [];
        const dates = [];
        row.querySelectorAll('[data-sort-value]').forEach((cell) => {
            const v = String(cell.dataset.sortValue).trim();
            const d = v.match(DATE_RE);
            if (d) dates.push(parseInt(v.slice(0, 8), 10));
            else if (NUM_RE.test(v)) numbers.push(parseFloat(v));
        });

        idx = makeIndex({ text: (row.dataset.row || '') + ' ' + row.textContent, numbers, dates });
        rowCache.set(row, idx);
        return idx;
    };

    /** What is true of the whole table: any dates, and every word in it. */
    api.tableInfo = function (table) {
        let info = tableCache.get(table);
        if (info) return info;

        const vocab = new Set();
        let hasDates = false;
        table.querySelectorAll('tbody tr[data-row]').forEach((row) => {
            const idx = api.index(row);
            idx.words.forEach((w) => vocab.add(w));
            if (idx.dates.length) hasDates = true;
        });

        info = { vocab, hasDates };
        tableCache.set(table, info);
        return info;
    };

    api.compileFor = function (query, table) {
        const info = api.tableInfo(table);
        return compile(query, { hasDates: info.hasDates, vocab: info.vocab });
    };

    /* ---- Highlighting ---- */

    const SKIP = 'form, button, a.btn-icon, .avatar, select, input, textarea, script, style, mark, [data-no-highlight]';

    function clearMarks(table) {
        table.querySelectorAll('mark.pms-hit').forEach((mark) => {
            const parent = mark.parentNode;
            parent.replaceChild(document.createTextNode(mark.textContent), mark);
            parent.normalize();
        });
    }

    function markRow(row, terms) {
        const escaped = terms.filter(Boolean).map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
        if (!escaped.length) return;
        const re = new RegExp('(' + escaped.join('|') + ')', 'gi');

        row.querySelectorAll('td').forEach((cell) => {
            const walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT, {
                acceptNode: (n) => (n.parentElement && n.parentElement.closest(SKIP) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
            });
            const nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);

            nodes.forEach((node) => {
                if (!re.test(node.nodeValue)) { re.lastIndex = 0; return; }
                re.lastIndex = 0;

                const frag = document.createDocumentFragment();
                node.nodeValue.split(re).forEach((part, i) => {
                    if (i % 2 === 1) {
                        const mark = document.createElement('mark');
                        mark.className = 'pms-hit';
                        mark.textContent = part;
                        frag.appendChild(mark);
                    } else if (part) {
                        frag.appendChild(document.createTextNode(part));
                    }
                });
                node.parentNode.replaceChild(frag, node);
            });
        });
    }

    /**
     * After a filter pass: highlight what matched, and explain an empty result.
     * @param {HTMLElement} table
     * @param {HTMLInputElement} input
     * @param {ReturnType<typeof compile>} query
     * @param {number} shown
     */
    api.decorate = function (table, input, query, shown) {
        clearMarks(table);

        if (query && !query.empty && shown > 0 && query.terms.length) {
            let count = 0;
            table.querySelectorAll('tbody tr[data-row]').forEach((row) => {
                if (row.dataset.filteredOut || count >= 250) return;
                markRow(row, query.terms);
                count++;
            });
        }

        const noMatch = table.querySelector('tbody tr[data-no-match]');
        const text = noMatch && noMatch.querySelector('.es-text');
        if (!text) return;

        if (text.dataset.original === undefined) text.dataset.original = text.innerHTML;

        if (shown === 0 && query && !query.empty) {
            const hint = suggest(input.value, api.tableInfo(table).vocab);
            const shownTerm = document.createElement('strong');
            shownTerm.textContent = '“' + input.value.trim() + '”';

            text.textContent = 'Nothing matches ';
            text.appendChild(shownTerm);
            text.appendChild(document.createTextNode('. '));

            if (hint) {
                text.appendChild(document.createTextNode('Did you mean '));
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'pms-suggest';
                button.textContent = hint.to;
                button.addEventListener('click', () => {
                    input.value = hint.query;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.focus();
                });
                text.appendChild(button);
                text.appendChild(document.createTextNode('? '));
            }

            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'pms-suggest quiet';
            clear.textContent = 'Clear search';
            clear.addEventListener('click', () => {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            });
            text.appendChild(clear);
        } else {
            text.innerHTML = text.dataset.original;
        }
    };

    /* ---- The search box itself ---- */

    api.enhanceInput = function (input, table) {
        const field = input.closest('.search-field');
        if (!field || field.dataset.pmsSmart === '1') return;
        field.dataset.pmsSmart = '1';
        field.classList.add('smart');

        input.setAttribute('autocomplete', 'off');
        input.setAttribute('spellcheck', 'false');

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'search-clear';
        clear.setAttribute('aria-label', 'Clear search');
        clear.hidden = true;
        clear.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';

        field.append(clear);

        const sync = () => { clear.hidden = input.value.length === 0; };

        input.addEventListener('input', sync);
        clear.addEventListener('click', () => {
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (input.value) {
                e.preventDefault();
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                input.blur();
            }
        });

        sync();
    };

    root.PMSSearch = api;
})(typeof window !== 'undefined' ? window : globalThis);
