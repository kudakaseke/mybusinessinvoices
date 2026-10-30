window.documentEditor = (initial) => ({
    ...initial,
    reviewing: false,
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
