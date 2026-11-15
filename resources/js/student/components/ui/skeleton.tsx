import { cn } from '@/lib/utils';

export function Skeleton({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div aria-hidden="true" className={cn('sb-skeleton', className)} {...props} />;
}

/** Pembungkus area loading yang aksesibel. */
export function LoadingRegion({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div role="status" aria-busy="true" aria-label={label} className="contents">
            <span className="sr-only">{label}</span>
            {children}
        </div>
    );
}
