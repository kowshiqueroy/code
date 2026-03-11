// ============================================================
// db.js — IndexedDB Manager (Offline Storage)
// Provides async CRUD for offline sales, drafts, and product cache
// ============================================================
'use strict';

const PosDB = (() => {
    const DB_NAME    = 'pos_offline_db';
    const DB_VERSION = 2;
    let _db = null;

    // ── Schema Definition ─────────────────────────────────
    const STORES = {
        offline_sales: {
            keyPath: 'local_id',
            indexes: [
                { name: 'status',     keyPath: 'status' },
                { name: 'created_at', keyPath: 'created_at' },
            ]
        },
        products_cache: {
            keyPath: 'id',
            indexes: [
                { name: 'barcode', keyPath: 'barcode', unique: true },
                { name: 'name',    keyPath: 'name' },
            ]
        },
        drafts: {
            keyPath: 'id',
            autoIncrement: true,
            indexes: [
                { name: 'label',      keyPath: 'label' },
                { name: 'updated_at', keyPath: 'updated_at' },
            ]
        },
        sync_log: {
            keyPath: 'id',
            autoIncrement: true,
            indexes: [{ name: 'local_id', keyPath: 'local_id' }]
        }
    };

    // ── Open / Upgrade ────────────────────────────────────
    function open() {
        if (_db) return Promise.resolve(_db);

        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);

            req.onupgradeneeded = e => {
                const db = e.target.result;
                for (const [storeName, config] of Object.entries(STORES)) {
                    let store;
                    if (db.objectStoreNames.contains(storeName)) {
                        // v2 migration: can add new indexes
                        store = e.target.transaction.objectStore(storeName);
                    } else {
                        store = db.createObjectStore(storeName, {
                            keyPath: config.keyPath,
                            autoIncrement: config.autoIncrement || false
                        });
                    }
                    (config.indexes || []).forEach(idx => {
                        if (!store.indexNames.contains(idx.name)) {
                            store.createIndex(idx.name, idx.keyPath, {
                                unique: idx.unique || false
                            });
                        }
                    });
                }
            };

            req.onsuccess = e => {
                _db = e.target.result;
                _db.onversionchange = () => { _db.close(); _db = null; };
                resolve(_db);
            };

            req.onerror = e => reject(e.target.error);
        });
    }

    // ── Generic Transaction Helpers ───────────────────────
    async function tx(storeName, mode, callback) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, mode);
            const store = transaction.objectStore(storeName);
            let result;
            try { result = callback(store); }
            catch (err) { reject(err); return; }
            if (result && typeof result.onsuccess !== 'undefined') {
                result.onsuccess = e => resolve(e.target.result);
                result.onerror   = e => reject(e.target.error);
            } else {
                transaction.oncomplete = () => resolve(result);
            }
            transaction.onerror = e => reject(e.target.error);
        });
    }

    async function getAll(storeName, indexName, query) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const t = db.transaction(storeName, 'readonly');
            const store = t.objectStore(storeName);
            const source = indexName ? store.index(indexName) : store;
            const req = query ? source.getAll(query) : source.getAll();
            req.onsuccess = e => resolve(e.target.result);
            req.onerror   = e => reject(e.target.error);
        });
    }

    // ── UUID Generator ────────────────────────────────────
    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    // ── PUBLIC API ─────────────────────────────────────────

    // ── Offline Sales ─────────────────────────────────────
    const sales = {
        async save(saleData) {
            const record = {
                ...saleData,
                local_id:   saleData.local_id || uuid(),
                status:     'pending',
                created_at: saleData.created_at || new Date().toISOString(),
                device_info: navigator.userAgent,
            };
            await tx('offline_sales', 'readwrite', store => store.put(record));
            return record.local_id;
        },

        async getAll() {
            return getAll('offline_sales');
        },

        async getPending() {
            return getAll('offline_sales', 'status', IDBKeyRange.only('pending'));
        },

        async get(localId) {
            return tx('offline_sales', 'readonly', store => store.get(localId));
        },

        async markSynced(localId) {
            const record = await this.get(localId);
            if (record) {
                record.status = 'synced';
                record.synced_at = new Date().toISOString();
                await tx('offline_sales', 'readwrite', store => store.put(record));
            }
        },

        async delete(localId) {
            return tx('offline_sales', 'readwrite', store => store.delete(localId));
        },

        async count() {
            const db = await open();
            return new Promise((resolve, reject) => {
                const t = db.transaction('offline_sales', 'readonly');
                const req = t.objectStore('offline_sales').count();
                req.onsuccess = e => resolve(e.target.result);
                req.onerror = e => reject(e.target.error);
            });
        }
    };

    // ── Product Cache ─────────────────────────────────────
    const products = {
        async cacheAll(productArray) {
            const db = await open();
            return new Promise((resolve, reject) => {
                const t = db.transaction('products_cache', 'readwrite');
                const store = t.objectStore('products_cache');
                productArray.forEach(p => store.put(p));
                t.oncomplete = resolve;
                t.onerror = e => reject(e.target.error);
            });
        },

        async search(query) {
            const all = await getAll('products_cache');
            if (!query) return all;
            const q = query.toLowerCase();
            return all.filter(p =>
                p.name.toLowerCase().includes(q) ||
                (p.barcode && p.barcode.includes(q))
            );
        },

        async getByBarcode(barcode) {
            const db = await open();
            return new Promise((resolve, reject) => {
                const t = db.transaction('products_cache', 'readonly');
                const idx = t.objectStore('products_cache').index('barcode');
                const req = idx.get(barcode);
                req.onsuccess = e => resolve(e.target.result || null);
                req.onerror = e => reject(e.target.error);
            });
        },

        async get(id) {
            return tx('products_cache', 'readonly', store => store.get(id));
        },

        async clear() {
            return tx('products_cache', 'readwrite', store => store.clear());
        }
    };

    // ── Drafts ────────────────────────────────────────────
    const drafts = {
        async save(label, cartData) {
            const record = {
                label,
                cart_data: JSON.stringify(cartData),
                updated_at: new Date().toISOString(),
            };
            return tx('drafts', 'readwrite', store => store.put(record));
        },

        async update(id, label, cartData) {
            const record = {
                id,
                label,
                cart_data: JSON.stringify(cartData),
                updated_at: new Date().toISOString(),
            };
            return tx('drafts', 'readwrite', store => store.put(record));
        },

        async getAll() {
            const rows = await getAll('drafts');
            return rows.map(r => ({
                ...r,
                cart_data: JSON.parse(r.cart_data || '{}')
            }));
        },

        async get(id) {
            const r = await tx('drafts', 'readonly', store => store.get(id));
            if (r) r.cart_data = JSON.parse(r.cart_data || '{}');
            return r;
        },

        async delete(id) {
            return tx('drafts', 'readwrite', store => store.delete(id));
        }
    };

    // ── Sync Log ──────────────────────────────────────────
    const syncLog = {
        async add(entry) {
            return tx('sync_log', 'readwrite', store => store.add({
                ...entry,
                logged_at: new Date().toISOString()
            }));
        },
        async getAll() {
            return getAll('sync_log');
        }
    };

    return { open, sales, products, drafts, syncLog, uuid };
})();

// ── Export ─────────────────────────────────────────────────
window.PosDB = PosDB;
