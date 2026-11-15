import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold whitespace-nowrap',
    {
        variants: {
            tone: {
                neutral: 'bg-sb-surface2 text-sb-text2 border border-sb-border',
                primary: 'bg-sb-primary-soft text-sb-primary border border-sb-primary/20',
                ok: 'bg-sb-ok-bg text-sb-ok border border-sb-ok/25',
                warn: 'bg-sb-warn-bg text-sb-warn border border-sb-warn/25',
                danger: 'bg-sb-danger-bg text-sb-danger border border-sb-danger/25',
                info: 'bg-sb-info-bg text-sb-info border border-sb-info/25',
                violet: 'bg-sb-violet-bg text-sb-violet border border-sb-violet/25',
            },
        },
        defaultVariants: { tone: 'neutral' },
    },
);

export interface BadgeProps
    extends React.HTMLAttributes<HTMLSpanElement>,
        VariantProps<typeof badgeVariants> {}

export function Badge({ className, tone, ...props }: BadgeProps) {
    return <span className={cn(badgeVariants({ tone }), className)} {...props} />;
}

/** Peta status → tone konsisten di seluruh aplikasi. */
export function toneFor(status: string): NonNullable<BadgeProps['tone']> {
    switch (status) {
        case 'disetujui':
        case 'lengkap':
        case 'aktif':
        case 'berlaku':
            return 'ok';
        case 'diajukan':
        case 'menunggu_penerimaan':
        case 'menunggu':
        case 'belum_mulai':
            return 'warn';
        case 'ditolak':
        case 'dicabut':
        case 'alfa':
            return 'danger';
        case 'selesai':
        case 'berakhir':
            return 'neutral';
        case 'kedaluwarsa':
        case 'dibatalkan':
        case 'tidak_lengkap':
        case 'masuk':
        case 'belum_lengkap':
            return 'info';
        default:
            return 'neutral';
    }
}
