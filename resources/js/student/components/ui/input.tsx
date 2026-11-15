import * as React from 'react';
import { cn } from '@/lib/utils';

const fieldCls =
    'w-full rounded-[10px] border border-sb-border bg-sb-surface px-3.5 py-2.5 text-sm text-sb-text placeholder:text-sb-muted/70 outline-none transition focus:border-sb-primary focus:ring-[3px] focus:ring-sb-primary/20 disabled:cursor-not-allowed disabled:bg-sb-surface2 disabled:text-sb-muted';

export const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
    ({ className, ...props }, ref) => (
        <input ref={ref} className={cn(fieldCls, className)} {...props} />
    ),
);
Input.displayName = 'Input';

export const Textarea = React.forwardRef<HTMLTextAreaElement, React.TextareaHTMLAttributes<HTMLTextAreaElement>>(
    ({ className, ...props }, ref) => (
        <textarea ref={ref} className={cn(fieldCls, 'min-h-[84px] resize-y', className)} {...props} />
    ),
);
Textarea.displayName = 'Textarea';

export const Select = React.forwardRef<HTMLSelectElement, React.SelectHTMLAttributes<HTMLSelectElement>>(
    ({ className, ...props }, ref) => (
        <select ref={ref} className={cn(fieldCls, 'appearance-none bg-no-repeat pr-9', className)}
            style={{ backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%235f7195' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E")`, backgroundPosition: 'right 12px center' }}
            {...props}
        />
    ),
);
Select.displayName = 'Select';

export function Label({ className, ...props }: React.LabelHTMLAttributes<HTMLLabelElement>) {
    return <label className={cn('block text-[13px] font-semibold text-sb-text2 mb-1.5', className)} {...props} />;
}

export function FieldError({ children }: { children?: React.ReactNode }) {
    if (!children) return null;
    return <p className="mt-1.5 text-[12.5px] font-medium text-sb-danger" role="alert">{children}</p>;
}
