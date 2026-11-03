import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

globalThis.window = { location: { assign() {} } };
await import('../resources/js/app.js');

function editor() {
    const state = window.documentEditor({
        draftKey: 'draft-key', draftUrl: '/invoices/draft',
        items: [{ description: 'Service', quantity: 2, unit_price: '12.50', product_id: '' }],
    });
    state.$root = { querySelector: () => ({ value: 'csrf-token' }) };
    return state;
}

beforeEach(() => {
    globalThis.FormData = class {
        *[Symbol.iterator]() {
            yield ['_token', 'csrf-token'];
            yield ['draft_key', 'draft-key'];
            yield ['customer_name', 'Draft client'];
        }
    };
});

test('draft saving submits current items with CSRF and excludes control fields', async () => {
    const state = editor();
    globalThis.fetch = async (url, options) => {
        assert.equal(url, '/invoices/draft');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
        const data = JSON.parse(options.body);
        assert.equal(data.payload.customer_name, 'Draft client');
        assert.equal(data.payload.items[0].quantity, '2');
        assert.equal(data.payload.items[0].product_id, null);
        assert.equal(data.payload._token, undefined);
        assert.equal(data.payload.draft_key, undefined);
        return { ok: true };
    };
    await state.saveDraft();
    assert.equal(state.draftStatus, 'Draft saved');
    assert.equal(state.dirty, false);
    assert.equal(state.saving, false);
});

test('failed autosave keeps changes dirty and warns rather than claiming success', async () => {
    const state = editor();
    globalThis.fetch = async () => ({ ok: false });
    await state.saveDraft();
    assert.equal(state.dirty, true);
    assert.match(state.draftStatus, /not saved/);
});

test('review waits for an in-flight save so it cannot recreate a finalized draft', async () => {
    const state = editor();
    let release;
    let submitted = false;
    globalThis.fetch = () => new Promise(resolve => { release = resolve; });
    const saving = state.saveDraft();
    const reviewing = state.submitReview({ target: { submit() { submitted = true; } } });
    assert.equal(submitted, false);
    release({ ok: true });
    await saving;
    await reviewing;
    assert.equal(submitted, true);
    assert.equal(state.submitting, true);
});

test('navigation saves dirty changes before leaving the editor', async () => {
    const state = editor();
    state.dirty = true;
    let destination;
    window.location.assign = url => { destination = url; };
    globalThis.fetch = async () => ({ ok: true });
    const link = { href: '/invoices', target: '', hasAttribute: () => false };
    let prevented = false;
    await state.guardLink({
        button: 0, target: { closest: () => link },
        preventDefault() { prevented = true; }, stopImmediatePropagation() {},
    });
    assert.equal(prevented, true);
    assert.equal(destination, '/invoices');
});


test('returning through browser history allows the document to be reviewed again', async () => {
    const state = editor();
    let submissions = 0;
    const event = { target: { submit() { submissions++; } } };
    await state.submitReview(event);
    state.restoreFromHistory({ persisted: true });
    await state.submitReview(event);
    assert.equal(submissions, 2);
});
