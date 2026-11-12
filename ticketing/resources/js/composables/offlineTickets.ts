/**
 * Penyimpanan e-ticket offline (FR-TIX-05, SYSTEM_DESIGN §15.3).
 *
 * Tiket yang pernah dibuka disimpan di IndexedDB beserta payload QR. QR
 * dirender ulang di sisi klien dari payload, sehingga tetap tampil tanpa
 * jaringan. Status tiket saat offline **selalu** diberi label
 * "Tidak terverifikasi" — validitas akhir ditentukan server saat scan.
 */

export interface OfflineTicket {
    uuid: string;
    ticket_code: string;
    qr_token: string;
    qr_data_url: string;
    status: string;
    status_label: string;
    museum_name: string;
    museum_address: string;
    museum_city: string;
    visit_date: string;
    slot_label: string;
    ticket_type_name: string;
    booking_code: string;
    contact_name: string;
    /** Waktu tiket terakhir diperbarui dari server (ISO 8601). */
    cached_at: string;
}

const DB_NAME = 'jejak-tickets';
const DB_VERSION = 1;
const STORE = 'tickets';

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'uuid' });
            }
        };
    });
}

export function indexedDbAvailable(): boolean {
    return typeof indexedDB !== 'undefined';
}

export async function saveOfflineTicket(ticket: OfflineTicket): Promise<void> {
    if (!indexedDbAvailable()) {
        return;
    }

    const db = await openDatabase();

    try {
        await new Promise<void>((resolve, reject) => {
            const transaction = db.transaction(STORE, 'readwrite');
            transaction.objectStore(STORE).put(ticket);
            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
        });
    } finally {
        db.close();
    }
}

export async function getOfflineTicket(uuid: string): Promise<OfflineTicket | null> {
    if (!indexedDbAvailable()) {
        return null;
    }

    const db = await openDatabase();

    try {
        return await new Promise<OfflineTicket | null>((resolve, reject) => {
            const transaction = db.transaction(STORE, 'readonly');
            const request = transaction.objectStore(STORE).get(uuid);
            request.onsuccess = () => resolve((request.result as OfflineTicket | undefined) ?? null);
            request.onerror = () => reject(request.error);
        });
    } finally {
        db.close();
    }
}

export async function listOfflineTickets(): Promise<OfflineTicket[]> {
    if (!indexedDbAvailable()) {
        return [];
    }

    const db = await openDatabase();

    try {
        return await new Promise<OfflineTicket[]>((resolve, reject) => {
            const transaction = db.transaction(STORE, 'readonly');
            const request = transaction.objectStore(STORE).getAll();
            request.onsuccess = () => resolve((request.result as OfflineTicket[]) ?? []);
            request.onerror = () => reject(request.error);
        });
    } finally {
        db.close();
    }
}

/**
 * Hapus seluruh tiket offline (dipanggil saat logout, §15.3).
 */
export async function clearOfflineTickets(): Promise<void> {
    if (!indexedDbAvailable()) {
        return;
    }

    const db = await openDatabase();

    try {
        await new Promise<void>((resolve, reject) => {
            const transaction = db.transaction(STORE, 'readwrite');
            transaction.objectStore(STORE).clear();
            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
        });
    } finally {
        db.close();
    }
}

/**
 * Label status tiket saat offline (SYSTEM_DESIGN §15.3).
 */
export function offlineStatusLabel(cachedAt: string): string {
    const cached = new Date(cachedAt);

    const formatted = Number.isNaN(cached.getTime())
        ? 'waktu tidak diketahui'
        : cached.toLocaleString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          });

    return `Tidak terverifikasi — terakhir diperbarui ${formatted}`;
}
