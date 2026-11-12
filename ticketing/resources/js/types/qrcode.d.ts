/**
 * Deklarasi tipe untuk paket `qrcode` (tidak menyediakan tipe sendiri).
 * Dipakai `Public/Tickets/Show.vue` untuk merender QR di sisi klien
 * (FR-TIX-05).
 */
declare module 'qrcode' {
    export interface QRCodeToDataURLOptions {
        width?: number;
        margin?: number;
        errorCorrectionLevel?: 'low' | 'medium' | 'quartile' | 'high' | 'L' | 'M' | 'Q' | 'H';
        color?: { dark?: string; light?: string };
        type?: string;
    }

    export function toDataURL(text: string, options?: QRCodeToDataURLOptions): Promise<string>;

    const QRCode: { toDataURL: typeof toDataURL };

    export default QRCode;
}
