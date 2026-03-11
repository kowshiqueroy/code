/**
 * ============================================================
 * POS System — Client-Side IndexedDB Manager  (assets/js/db.js)
 * ============================================================
 * Provides a clean Promise-based API for:
 *   • Offline sales draft storage
 *   • Product/settings cache reads
 *   • Sync queue management
 *   • Draft parking/recall
 */

'use strict';

const PosDB = (() => {

  const DB_NAME    = 'pos_offline';
  const DB_VERSION = 1;

  const STORES = {
    SYNC_QUEUE : 'sync_queue',
    PRODUCTS   : 'products_cache',
    SETTINGS   : 'settings_cache',
    DRAFTS     : 'drafts',
    CUSTOMERS  : 'customers_cache',
  };

  let _db = null;

  // ── Open (or reuse) the database ─────────────────────────
  function open() {
    if (_db) return Promise.resolve(_db);

    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);

      req.onupgradeneeded = (e) => {
        const db = e.target.result;

        // Sync Queue
        if (!db.objectStoreNames.contains(STORES.SYNC_QUEUE)) {
          const sq = db.createObjectStore(STORES.SYNC_QUEUE, {
            keyPath: 'localId', autoIncrement: true,
          });
          sq.createIndex('uuid',      'uuid',      { unique: true });
          sq.createIndex('status',    'status',    { unique: false });
          sq.createIndex('createdAt', 'createdAt', { unique: false });
        }

        // Products cache (for offline POS)
        if (!db.objectStoreNames.contains(STORES.PRODUCTS)) {
          const pc = db.createObjectStore(STORES.PRODUCTS, { keyPath: 'id' });
          pc.createIndex('barcode', 'barcode', { unique: false });
          pc.createIndex('name',    'name',    { unique: false });
        }

        // Settings cache
        if (!db.objectStoreNames.contains(STORES.SETTINGS)) {
          db.createObjectStore(STORES.SETTINGS, { keyPath: 'key' });
        }

        // Draft/parked sales
        if (!db.objectStoreNames.contains(STORES.DRAFTS)) {
          const dr = db.createObjectStore(STORES.DRAFTS, {
            keyPath: 'localId', autoIncrement: true,
          });
          dr.createIndex('name',      'name',      { unique: false });
          dr.createIndex('createdAt', 'createdAt', { unique: false });
        }

        // Customers cache
        if (!db.objectStoreNames.contains(STORES.CUSTOMERS)) {
          const cu = db.createObjectStore(STORES.CUSTOMERS, { keyPath: 'id' });
          cu.createIndex('phone', 'phone', { unique: false });
          cu.createIndex('name',  'name',  { unique: false });
        }
      };

      req.onsuccess = () => { _db = req.result; resolve(_db); };
      req.onerror   = () => reject(req.error);
    });
  }

  // ── Generic helpers ───────────────────────────────────────
  async function getAll(storeName) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readonly');
      const req = tx.objectStore(storeName).getAll();
      req.onsuccess = () => resolve(req.result ?? []);
      req.onerror   = () => reject(req.error);
    });
  }

  async function getByKey(storeName, key) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readonly');
      const req = tx.objectStore(storeName).get(key);
      req.onsuccess = () => resolve(req.result ?? null);
      req.onerror   = () => reject(req.error);
    });
  }

  async function put(storeName, record) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readwrite');
      const req = tx.objectStore(storeName).put(record);
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    });
  }

  async function remove(storeName, key) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readwrite');
      const req = tx.objectStore(storeName).delete(key);
      req.onsuccess = () => resolve();
      req.onerror   = () => reject(req.error);
    });
  }

  async function clear(storeName) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readwrite');
      const req = tx.objectStore(storeName).clear();
      req.onsuccess = () => resolve();
      req.onerror   = () => reject(req.error);
    });
  }

  async function getByIndex(storeName, indexName, value) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx    = db.transaction(storeName, 'readonly');
      const idx   = tx.objectStore(storeName).index(indexName);
      const req   = idx.getAll(value);
      req.onsuccess = () => resolve(req.result ?? []);
      req.onerror   = () => reject(req.error);
    });
  }

  // ── Products ──────────────────────────────────────────────
  const products = {
    /** Replace entire products cache */
    async replaceAll(list) {
      await clear(STORES.PRODUCTS);
      const db = await open();
      return new Promise((resolve, reject) => {
        const tx = db.transaction(STORES.PRODUCTS, 'readwrite');
        let count = 0;
        for (const p of list) {
          tx.objectStore(STORES.PRODUCTS).put(p);
          count++;
        }
        tx.oncomplete = () => resolve(count);
        tx.onerror    = () => reject(tx.error);
      });
    },

    getAll: () => getAll(STORES.PRODUCTS),

    /** Search by barcode (exact) */
    async findByBarcode(barcode) {
      const results = await getByIndex(STORES.PRODUCTS, 'barcode', barcode);
      return results[0] ?? null;
    },

    /** Search by partial name */
    async search(query) {
      const all = await getAll(STORES.PRODUCTS);
      const q   = query.toLowerCase();
      return all.filter(p =>
        p.name?.toLowerCase().includes(q) ||
        p.barcode?.includes(q)
      );
    },
  };

  // ── Settings ──────────────────────────────────────────────
  const settings = {
    async get(key) {
      const row = await getByKey(STORES.SETTINGS, key);
      return row?.value ?? null;
    },
    async set(key, value) {
      return put(STORES.SETTINGS, { key, value, updatedAt: Date.now() });
    },
    async getAll() {
      const rows = await getAll(STORES.SETTINGS);
      return Object.fromEntries(rows.map(r => [r.key, r.value]));
    },
  };

  // ── Sync Queue ────────────────────────────────────────────
  const syncQueue = {
    /**
     * Add a failed POST sale to the queue.
     * @param {object} salePayload - The full sale object as POSTed
     * @param {string} uuid        - Client-generated UUID
     */
    async enqueue(salePayload, uuid) {
      return put(STORES.SYNC_QUEUE, {
        uuid,
        payload:   JSON.stringify(salePayload),
        status:    'pending',
        retries:   0,
        createdAt: Date.now(),
        updatedAt: Date.now(),
      });
    },

    /** All pending items */
    async getPending() {
      const all = await getAll(STORES.SYNC_QUEUE);
      return all.filter(i => i.status === 'pending');
    },

    /** Total count */
    async count() {
      return (await getAll(STORES.SYNC_QUEUE)).length;
    },

    async markSynced(localId) {
      const item = await getByKey(STORES.SYNC_QUEUE, localId);
      if (!item) return;
      return put(STORES.SYNC_QUEUE, { ...item, status: 'synced', updatedAt: Date.now() });
    },

    async markFailed(localId) {
      const item = await getByKey(STORES.SYNC_QUEUE, localId);
      if (!item) return;
      return put(STORES.SYNC_QUEUE, {
        ...item,
        status:    'failed',
        retries:   (item.retries ?? 0) + 1,
        updatedAt: Date.now(),
      });
    },

    async remove(localId) {
      return remove(STORES.SYNC_QUEUE, localId);
    },

    getAll: () => getAll(STORES.SYNC_QUEUE),
  };

  // ── Drafts ────────────────────────────────────────────────
  const drafts = {
    /**
     * Park/save a sale as a draft.
     * @param {string} name    - Human label for recall
     * @param {object} cart    - Cart contents
     * @param {object} meta    - Customer, payment info, etc.
     */
    async save(name, cart, meta = {}) {
      return put(STORES.DRAFTS, {
        name,
        cart,
        meta,
        createdAt: Date.now(),
        updatedAt: Date.now(),
      });
    },

    /** Update existing draft */
    async update(localId, name, cart, meta = {}) {
      const existing = await getByKey(STORES.DRAFTS, localId);
      return put(STORES.DRAFTS, {
        ...(existing ?? {}),
        localId,
        name,
        cart,
        meta,
        updatedAt: Date.now(),
      });
    },

    getAll:        () => getAll(STORES.DRAFTS),
    getById:  (id) => getByKey(STORES.DRAFTS, id),
    remove:   (id) => remove(STORES.DRAFTS, id),
  };

  // ── Customers ─────────────────────────────────────────────
  const customers = {
    async replaceAll(list) {
      await clear(STORES.CUSTOMERS);
      const db = await open();
      return new Promise((resolve, reject) => {
        const tx = db.transaction(STORES.CUSTOMERS, 'readwrite');
        for (const c of list) tx.objectStore(STORES.CUSTOMERS).put(c);
        tx.oncomplete = () => resolve(list.length);
        tx.onerror    = () => reject(tx.error);
      });
    },

    async findByPhone(phone) {
      const results = await getByIndex(STORES.CUSTOMERS, 'phone', phone);
      return results[0] ?? null;
    },

    async search(query) {
      const all = await getAll(STORES.CUSTOMERS);
      const q   = query.toLowerCase();
      return all.filter(c =>
        c.name?.toLowerCase().includes(q) ||
        c.phone?.includes(q)
      ).slice(0, 10);
    },

    getAll: () => getAll(STORES.CUSTOMERS),
    getById: (id) => getByKey(STORES.CUSTOMERS, id),
    put: (record) => put(STORES.CUSTOMERS, record),
  };

  // ── Expose public API ─────────────────────────────────────
  return {
    open,
    products,
    settings,
    syncQueue,
    drafts,
    customers,
    STORES,
  };

})();

// Make globally available
window.PosDB = PosDB;
