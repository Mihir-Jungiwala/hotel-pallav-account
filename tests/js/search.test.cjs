// Run with:  node --test tests/js/search.test.cjs
// The matcher has no dependency on the page, so it is tested on its own.
// The project is an ES module package and this is a browser script, so the
// source is evaluated directly rather than require()d.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/pms-search.js'), 'utf8');
const holder = { exports: {} };
new Function('module', 'exports', source)(holder, holder.exports);
const S = holder.exports;

/** A row, the way the page builds one from what a table row shows. */
const row = (text, extra = {}) => S.makeIndex({ text, ...extra });

const staff = [
    row('Asha Menon E-1 Front Office Operations 98765 43210 asha@pallav.com Bank Active', { numbers: [24000] }),
    row('Ravi Patel E-2 Cook Kitchen 91234 56789 ravi@pallav.com Cash Active', { numbers: [18000] }),
    row('Meena Shah E-3 Housekeeping Rooms 90000 11111 meena@pallav.com Cash Inactive', { numbers: [15500] }),
    row('Kiran Joshi E-4 Front Office Operations 98000 22222 kiran@pallav.com Bank Active', { numbers: [32000] }),
];
const vocab = new Set(staff.flatMap((r) => r.words));
const find = (query, ctx = {}) => {
    const q = S.compile(query, { vocab, ...ctx });
    return staff.map((r, i) => (q.empty || q.test(r) ? i : null)).filter((i) => i !== null);
};

test('an empty search matches everything', () => {
    assert.deepEqual(find(''), [0, 1, 2, 3]);
    assert.deepEqual(find('   '), [0, 1, 2, 3]);
});

test('a word matches the start of a word, in any case', () => {
    assert.deepEqual(find('asha'), [0]);
    assert.deepEqual(find('ASHA'), [0]);
    assert.deepEqual(find('front'), [0, 3]);
});

test('a fragment matches inside a word', () => {
    assert.deepEqual(find('sha'), [0, 2]);   // Asha, Shah
    assert.deepEqual(find('ouse'), [2]);     // Housekeeping
});

test('every word must match, in any order', () => {
    assert.deepEqual(find('front office'), [0, 3]);
    assert.deepEqual(find('office front'), [0, 3]);
    assert.deepEqual(find('front bank kiran'), [3]);
    assert.deepEqual(find('front cook'), []);
});

test('a whole word does not also match the middle of another word', () => {
    // "active" is a word in this table, so it must not pull in "Inactive"
    assert.deepEqual(find('active'), [0, 1, 3]);
    assert.deepEqual(find('inactive'), [2]);
});

test('a quoted phrase must appear as written', () => {
    assert.deepEqual(find('"front office"'), [0, 3]);
    assert.deepEqual(find('"office front"'), []);
});

test('a leading dash leaves rows out', () => {
    assert.deepEqual(find('office -kiran'), [0]);
    assert.deepEqual(find('-inactive'), [0, 1, 3]);
    assert.deepEqual(find('-"front office"'), [1, 2]);
});

test('amounts: above, below, and between', () => {
    assert.deepEqual(find('>20000'), [0, 3]);
    assert.deepEqual(find('<16000'), [2]);
    assert.deepEqual(find('16000-25000'), [0, 1]);
    assert.deepEqual(find('>=32000'), [3]);
    assert.deepEqual(find('<=15500'), [2]);
});

test('an amount can be typed as it is printed', () => {
    const paid = row('Asha ₹24,000.00 Paid', { numbers: [24000] });
    assert.ok(S.compile('24000', {}).test(paid));
    assert.ok(S.compile('24,000', {}).test(paid));
    assert.ok(S.compile('₹24,000', {}).test(paid));
    const lakh = row('Owner ₹1,50,000.00');
    assert.ok(S.compile('150000', {}).test(lakh));
});

test('a phone number matches however it is spaced', () => {
    assert.deepEqual(find('9876543210'), [0]);
    assert.deepEqual(find('98765 43210'), [0]);
    assert.deepEqual(find('987654'), [0]);
});

test('an email matches by any part of it', () => {
    assert.deepEqual(find('ravi@pallav.com'), [1]);
    assert.deepEqual(find('pallav'), [0, 1, 2, 3]);
    assert.deepEqual(find('meena@'), [2]);
});

test('typos are forgiven', () => {
    assert.deepEqual(find('menno'), [0]);        // Menon, one extra letter
    assert.deepEqual(find('asah'), [0]);         // Asha, two letters swapped
    assert.deepEqual(find('houskeeping'), [2]);  // one missing letter
    assert.deepEqual(find('kirn'), [3]);
});

test('short words are not fuzzy, so they do not match at random', () => {
    assert.deepEqual(find('xyz'), []);
    assert.deepEqual(find('ra'), [1]);           // prefix only: Ravi
});

test('a typo does not turn into a wrong match', () => {
    assert.deepEqual(find('zzzzzz'), []);
    assert.deepEqual(find('patel zzzzzz'), []);
});

test('accents do not get in the way', () => {
    const r = row('José Núñez Cook');
    assert.ok(S.compile('jose nunez', {}).test(r));
    assert.ok(S.compile('José', {}).test(row('Jose Nunez')));
});

test('names in other scripts search too', () => {
    const r = row('આશા મેનન Cook');
    assert.ok(S.compile('આશા', {}).test(r));
    assert.ok(S.compile('मेनन', {}).test(row('आशा मेनन')));
});

test('date words match rows dated in that window', () => {
    const now = new Date(2026, 8, 19, 12, 0);            // Sat 19 Sep 2026
    const mk = (d) => row('entry', { dates: [d] });
    const ctx = { hasDates: true, now };

    assert.ok(S.compile('today', ctx).test(mk(20260919)));
    assert.ok(!S.compile('today', ctx).test(mk(20260918)));
    assert.ok(S.compile('yesterday', ctx).test(mk(20260918)));
    assert.ok(S.compile('this week', ctx).test(mk(20260914)));    // Monday of that week
    assert.ok(!S.compile('this week', ctx).test(mk(20260913)));   // the Sunday before
    assert.ok(S.compile('this month', ctx).test(mk(20260901)));
    assert.ok(!S.compile('this month', ctx).test(mk(20260831)));
    assert.ok(S.compile('last month', ctx).test(mk(20260831)));
    assert.ok(!S.compile('last month', ctx).test(mk(20260901)));
});

test('a date time (with hours) still counts as its date', () => {
    const ctx = { hasDates: true, now: new Date(2026, 8, 19) };
    assert.ok(S.compile('today', ctx).test(row('x', { dates: [20260919] })));
});

test('on a table with no dates, "today" is just a word', () => {
    const q = S.compile('today', { hasDates: false });
    assert.ok(q.test(row('Today special')));
    assert.ok(!q.test(row('Nothing here')));
});

test('date words combine with other words', () => {
    const ctx = { hasDates: true, now: new Date(2026, 8, 19) };
    const q = S.compile('asha today', ctx);
    assert.ok(q.test(row('Asha advance', { dates: [20260919] })));
    assert.ok(!q.test(row('Ravi advance', { dates: [20260919] })));
    assert.ok(!q.test(row('Asha advance', { dates: [20260101] })));
});

test('highlight terms are the words and phrases actually typed', () => {
    const q = S.compile('asha "front office" -kiran >20000', { vocab });
    assert.deepEqual(q.terms, ['asha', 'front office']);
});

test('suggests the nearest real word when nothing matches', () => {
    const v = new Set(['menon', 'patel', 'shah', 'joshi']);
    const hint = S.suggest('menen', v);
    assert.equal(hint.to, 'menon');
    assert.equal(hint.query, 'menon');
    assert.equal(S.suggest('qqqqqq', v), null);
});

test('a suggestion keeps the rest of the query', () => {
    const v = new Set(['menon', 'front', 'office']);
    assert.equal(S.suggest('front menen', v).query, 'front menon');
});

test('no suggestion when the word is already known', () => {
    assert.equal(S.suggest('menon', new Set(['menon'])), null);
    assert.equal(S.suggest('sha', new Set(['asha'])), null);   // a fragment of a real word
});

test('textMatches works on a plain line, for menus', () => {
    assert.ok(S.textMatches('pallav', 'Hotel Pallav'));
    assert.ok(S.textMatches('hotel pal', 'Hotel Pallav'));
    assert.ok(S.textMatches('pallva', 'Hotel Pallav'));     // typo
    assert.ok(!S.textMatches('food', 'Hotel Pallav'));
    assert.ok(S.textMatches('', 'Anything'));
});

test('distance counts a swap as one edit', () => {
    assert.equal(S.distance('asha', 'asah', 2), 1);
    assert.equal(S.distance('menon', 'menen', 2), 1);
    assert.equal(S.distance('abc', 'xyz', 1), 2);           // gave up early
});

test('a lone symbol does not break the query', () => {
    assert.deepEqual(find('>'), [0, 1, 2, 3]);
    assert.deepEqual(find('-'), [0, 1, 2, 3]);
    assert.deepEqual(find('"'), [0, 1, 2, 3]);
});
