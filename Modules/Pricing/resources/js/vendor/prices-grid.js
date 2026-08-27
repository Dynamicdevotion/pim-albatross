/*! prices-grid.js — thin Alpine wrapper around jspreadsheet CE v4 for the
 *  bulk price editor. Loaded after jsuites.js and jspreadsheet.js. */
document.addEventListener('alpine:init', () => {
    Alpine.data('pricesGrid', (config) => ({
        instance: null,
        rows: config.rows ?? [],
        columns: config.columns ?? [],   // ordered read-only column keys, e.g. ['name','sku']
        headers: config.headers ?? {},   // { name:'Name', sku:'SKU', status:'Status', price:'Price' }
        saveTimer: null,
        pending: {},                     // product_id -> value (debounced batch)

        init() {
            this.build();

            this.$wire.on('prices-grid-data', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;
                this.rows = data.rows ?? [];
                this.columns = data.columns ?? this.columns;
                this.rebuild();
            });
        },

        priceColIndex() {
            return this.columns.length;  // price column is always appended last
        },

        columnDefs() {
            const defs = this.columns.map((key) => ({
                title: this.headers[key] ?? key,
                width: key === 'name' ? 320 : 140,
                readOnly: true,
                type: 'text',
            }));

            defs.push({
                title: this.headers.price ?? 'Price',
                width: 120,
                type: 'numeric',
                mask: '#,##0.00',
                decimal: '.',
            });

            return defs;
        },

        matrix() {
            return this.rows.map((row) => [
                ...this.columns.map((key) => row[key] ?? ''),
                row.price ?? '',
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
                    if (parseInt(x, 10) !== self.priceColIndex()) {
                        return;
                    }
                    const row = self.rows[parseInt(y, 10)];
                    if (row) {
                        self.queue(row.product_id, value);
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

        queue(productId, value) {
            this.pending[productId] = (value === '' || value === null) ? null : value;
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.flush(), 500);
        },

        flush() {
            const changes = Object.entries(this.pending).map(([productId, price]) => ({
                product_id: parseInt(productId, 10),
                price: price,
            }));
            this.pending = {};

            if (changes.length) {
                this.$wire.saveCells(changes);
            }
        },
    }));
});
