/// <reference types="vite/client" />
/// <reference types="vite-plugin-pwa/client" />

/**
 * Tipe props bersama dari `HandleInertiaRequests`.
 *
 * `index signature` diperlukan agar tipe ini memenuhi constraint `PageProps`
 * pada `usePage<T>()` di Inertia v3.
 */
export interface SharedProps {
    [key: string]: unknown;
    auth: {
        user?: {
            id: number;
            name: string;
            email: string;
            phone?: string | null;
            is_organizer?: boolean;
            is_admin?: boolean;
            email_verified?: boolean;
            organizer_profile?: {
                id: number;
                organization_name: string;
                status: string;
            } | null;
        } | null;
    };
    appName?: string;
    flash?: {
        success?: string | null;
        error?: string | null;
        status?: string | null;
    };
}
