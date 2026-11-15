import type { PropsWithChildren, ReactNode } from 'react';
import { Link } from '@inertiajs/react';

export function PageHead({
    crumb,
    title,
    sub,
    children,
}: PropsWithChildren<{ crumb?: ReactNode; title: ReactNode; sub?: ReactNode }>) {
    return (
        <header className="sb-pagehead" aria-label="Kepala halaman">
            {crumb && <nav className="mb-1.5 text-[12px] font-semibold tracking-wide text-sb-muted">{crumb}</nav>}
            <div className="flex flex-wrap items-center gap-3.5">
                {typeof title === 'string' ? (
                    <h2 className="text-[22px] font-extrabold tracking-tight text-sb-text">{title}</h2>
                ) : title}
                {children}
            </div>
            {sub && <p className="mt-1.5 max-w-2xl text-[13.5px] text-sb-text2">{sub}</p>}
        </header>
    );
}

export function Crumb({ items }: { items: Array<{ label: string; href?: string }> }) {
    return (
        <>
            {items.map((it, i) => (
                <span key={i}>
                    {i > 0 && <span className="mx-1.5 text-sb-muted/60">›</span>}
                    {it.href ? (
                        <Link href={it.href} className="hover:text-sb-primary">{it.label}</Link>
                    ) : (
                        <span>{it.label}</span>
                    )}
                </span>
            ))}
        </>
    );
}
