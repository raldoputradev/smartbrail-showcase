/**
 * Base URL aset publik — diturunkan dari konfigurasi Ziggy (@routes),
 * sehingga benar di deployment root maupun subfolder Apache (mis. /Simalas/public).
 */
declare const Ziggy: { url?: string } | undefined;

function basePath(): string {
    try {
        const url = typeof Ziggy !== 'undefined' ? Ziggy?.url
            : (window as unknown as { Ziggy?: { url?: string } }).Ziggy?.url;
        if (url) return new URL(url).pathname.replace(/\/$/, '');
    } catch {
        /* abaikan — fallback root */
    }
    return '';
}

/** asset('branding/logo.jpg') → '<base>/branding/logo.jpg' */
export function asset(path: string): string {
    return `${basePath()}/${path.replace(/^\/+/, '')}`;
}
