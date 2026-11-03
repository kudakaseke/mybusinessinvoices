window.documentEditor = (initial) => ({
    ...initial,
    reviewing: false,
    saving: false,
    submitting: false,
    dirty: false,
    draftStatus: 'Unsaved draft',
    draftTimer: null,
    pendingSave: null,
    init() {
        this.$watch('items', () => this.queueDraft());
    },
    queueDraft() {
        if (this.submitting) return;
        this.dirty = true;
        this.draftStatus = 'Unsaved changes';
        clearTimeout(this.draftTimer);
        this.draftTimer = setTimeout(() => this.saveDraft(), 1000);
    },
    async saveDraft() {
        if (this.submitting || this.saving) return this.pendingSave;
        clearTimeout(this.draftTimer);
        this.saving = true;
        this.dirty = false;
        this.draftStatus = 'Saving draft…';
        const form = this.$root;
        const payload = {};
        for (const [name, value] of new FormData(form)) {
            if (!name.startsWith('_') && name !== 'draft_key' && !name.startsWith('items[')) payload[name] = value;
        }
        payload.items = this.items.map(item => ({
            product_id: item.product_id || null, description: String(item.description ?? ''),
            quantity: String(item.quantity ?? ''), unit_price: String(item.unit_price ?? ''),
        }));
        this.pendingSave = (async () => {
            try {
                const response = await fetch(this.draftUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                    body: JSON.stringify({ key: this.draftKey, payload }),
                });
                if (!response.ok) throw new Error('Draft save failed');
                this.draftStatus = 'Draft saved';
                if (this.dirty && !this.submitting) this.queueDraft();
            } catch {
                this.dirty = true;
                this.draftStatus = 'Draft not saved. Check your connection and try again.';
            } finally {
                this.saving = false;
            }
        })();
        return this.pendingSave;
    },
    async submitReview(event) {
        if (this.submitting) return;
        this.submitting = true;
        clearTimeout(this.draftTimer);
        if (this.pendingSave) await this.pendingSave;
        event.target.submit();
    },
    async guardLink(event) {
        if (this.submitting || (!this.dirty && !this.saving) || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        await this.saveDraft();
        if (!this.dirty && !this.saving) window.location.assign(link.href);
    },
    restoreFromHistory(event) {
        if (event.persisted) this.submitting = false;
    },
    destroy() {
        this.submitting = true;
        clearTimeout(this.draftTimer);
    },
    selectClient(id) {
        const client = this.clients.find(client => String(client.id) === String(id));
        if (!client) return;
        ['name', 'email', 'address', 'phone'].forEach(field => {
            this['customer_' + field] = client[field] ?? '';
        });
    },
    selectProduct(item) {
        const product = this.products.find(product => String(product.id) === String(item.product_id));
        if (!product) return;
        item.description = product.description || product.name;
        item.unit_price = product.unit_price;
    },
    scaled(value) {
        const [whole, fraction = ''] = String(value || 0).split('.');
        return Number(whole) * 100 + Number(fraction.slice(0, 2).padEnd(2, '0'));
    },
    lineAmount(item) {
        return Math.round(this.scaled(item.quantity) * this.scaled(item.unit_price) / 100);
    },
    get subtotal() { return this.items.reduce((sum, item) => sum + this.lineAmount(item), 0); },
    get discount() { return Math.round(this.subtotal * this.scaled(this.discount_rate) / 10000); },
    get vat() { return Math.round((this.subtotal - this.discount) * this.scaled(this.vat_rate) / 10000); },
    get total() { return this.subtotal - this.discount + this.vat; },
    money(cents) { return (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
});
