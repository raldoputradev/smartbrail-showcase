import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] text-sm font-semibold transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-sb-primary/25 disabled:pointer-events-none disabled:opacity-50 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                primary: 'bg-sb-primary text-white hover:bg-sb-primary-hover shadow-sm',
                secondary: 'bg-sb-surface2 text-sb-text border border-sb-border hover:bg-sb-hover',
                outline: 'border border-sb-border-strong bg-sb-surface text-sb-text hover:bg-sb-hover',
                ghost: 'text-sb-text2 hover:bg-sb-hover hover:text-sb-text',
                danger: 'bg-sb-danger text-white hover:bg-red-700 shadow-sm',
                link: 'text-sb-primary underline-offset-4 hover:underline px-0 h-auto',
            },
            size: {
                sm: 'h-8 px-3 text-[13px]',
                md: 'h-10 px-4',
                lg: 'h-11 px-5',
                icon: 'h-9 w-9',
            },
        },
        defaultVariants: { variant: 'primary', size: 'md' },
    },
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    loading?: boolean;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, type = 'button', loading, disabled, children, ...props }, ref) => (
        <button
            ref={ref} type={type}
            className={cn(buttonVariants({ variant, size }), className)}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading && <Loader2 size={15} className="animate-spin" aria-hidden />}
            {children}
        </button>
    ),
);
Button.displayName = 'Button';
