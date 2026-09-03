/*! prices-grid.js — thin Alpine wrapper around jspreadsheet CE v4 for the
 *  bulk price editor. Loaded after jsuites.js and jspreadsheet.js. */
document.addEventListener('alpine:init', () => {
    Alpine.data('pricesGrid', (config) => ({
        instance: null,
        rows: config.rows ?? [],
        columns: config.columns ?? [],   // ordered read-only column keys, e.g. ['name','sku']
        headers: config.headers ?? {},   // { name:'Name', sku:'SKU', status:'Status', price:'Price', sale_price:'Sale price' }
        saveTimer: null,
        pending: {},                     // product_id -> { price?, sale_price? } (debounced batch, per-field)

        init() {
            this.build();

            this.$wire.on('prices-grid-data', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;
                this.rows = data.rows ?? [];
                this.columns = data.columns ?? this.columns;
                this.rebuild();
            });
        },

        // The two editable columns are always appended last, in this order.
        priceColIndex() {
            return this.columns.length;
        },

        salePriceColIndex() {
            return this.columns.length + 1;
        },

        editableFieldForColumn(x) {
            if (x === this.priceColIndex()) {
                return 'price';
            }
            if (x === this.salePriceColIndex()) {
                return 'sale_price';
            }
            return null;
        },

        columnDefs() {
            const defs = this.columns.map((key) => ({
                title: this.headers[key] ?? key,
                width: key === 'name' ? 320 : 140,
                readOnly: true,
                type: 'text',
            }));

            ['price', 'sale_price'].forEach((field) => {
                defs.push({
                    title: this.headers[field] ?? field,
                    width: 120,
                    type: 'numeric',
                    mask: '#,##0.00',
                    decimal: '.',
                });
            });

            return defs;
        },

        matrix() {
            return this.rows.map((row) => [
                ...this.columns.map((key) => row[key] ?? ''),
                row.price ?? '',
                row.sale_price ?? '',
            ]);
        },

        build() {
            const self = this;

            this.instance = jspreadsheet(this.$refs.grid, {
                data: this.matrix(),
                columns: this.columnDefs(),
                allowInsertRow: false,
                allowInsertColumn: false,
                allowDeleteRow: false,
                allowDeleteColumn: false,
                allowRenameColumn: false,
                columnSorting: true,
                search: false,
                pagination: false,
                tableOverflow: true,
                tableHeight: '60vh',
                onchange: (el, cell, x, y, value) => {
                    const field = self.editableFieldForColumn(parseInt(x, 10));
                    if (field === null) {
                        return;
                    }
                    const row = self.rows[parseInt(y, 10)];
                    if (row) {
                        self.queue(row.product_id, field, value);
                    }
                },
                onselection: (el, x1, y1, x2, y2) => {
                    const from = Math.min(parseInt(y1, 10), parseInt(y2, 10));
                    const to = Math.max(parseInt(y1, 10), parseInt(y2, 10));
                    const ids = [];
                    for (let i = from; i <= to; i++) {
                        if (self.rows[i]) {
                            ids.push(self.rows[i].product_id);
                        }
                    }
                    self.$wire.set('selectedProductIds', ids, false);
                },
            });
        },

        rebuild() {
            if (this.instance) {
                this.instance.destroy();
                this.instance = null;
            }
            this.$refs.grid.innerHTML = '';
            this.build();
        },

        // Merges into whatever is already pending for this product, so
        // editing price then sale_price within the same debounce window
        // flushes both — and editing just one never touches the other.
        queue(productId, field, value) {
            const current = this.pending[productId] ?? {};
            current[field] = (value === '' || value === null) ? null : value;
            this.pending[productId] = current;

            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.flush(), 500);
        },

        flush() {
            const changes = Object.entries(this.pending).map(([productId, fields]) => ({
                product_id: parseInt(productId, 10),
                ...fields,
            }));
            this.pending = {};

            if (changes.length) {
                this.$wire.saveCells(changes);
            }
        },
    }));
});
