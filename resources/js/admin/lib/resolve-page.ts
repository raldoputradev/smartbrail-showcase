import type { ComponentType } from 'react';

type PageModule = { default: ComponentType } | ComponentType;

function unwrap(module: PageModule): ComponentType {
    return (module as { default?: ComponentType }).default ?? (module as ComponentType);
}

/**
 * Resolver halaman Inertia → React.
 * import.meta.glob lazy menghasilkan `() => import()` — WAJIB dipanggil
 * agar Inertia menerima Promise<component>, bukan fungsi async sebagai komponen.
 */
export async function resolvePageComponent(
    path: string,
    pages: Record<string, unknown>,
): Promise<ComponentType> {
    const page = pages[path] as PageModule | (() => Promise<PageModule>) | undefined;
    if (typeof page === 'undefined') {
        throw new Error(`Page not found: ${path}`);
    }
    const module = typeof page === 'function' ? await (page as () => Promise<PageModule>)() : page;
    return unwrap(module);
}
