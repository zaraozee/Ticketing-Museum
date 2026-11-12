import { onMounted, onUnmounted, ref, type Ref } from 'vue';

/**
 * Status koneksi perangkat (FR-PWA-03, SYSTEM_DESIGN §15.3).
 *
 * Dipakai untuk menonaktifkan aksi yang membutuhkan jaringan (bayar, batalkan,
 * scan) dan menampilkan label "Tidak terverifikasi" pada e-ticket offline.
 */
export function useOnlineStatus(): {
    online: Ref<boolean>;
    lastOnlineAt: Ref<Date | null>;
} {
    const online = ref<boolean>(typeof navigator === 'undefined' ? true : navigator.onLine);
    const lastOnlineAt = ref<Date | null>(online.value ? new Date() : null);

    const handleOnline = (): void => {
        online.value = true;
        lastOnlineAt.value = new Date();
    };

    const handleOffline = (): void => {
        online.value = false;
    };

    onMounted(() => {
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    });

    onUnmounted(() => {
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });

    return { online, lastOnlineAt };
}
