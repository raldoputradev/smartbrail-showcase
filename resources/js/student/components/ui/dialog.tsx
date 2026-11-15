import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export const Dialog = DialogPrimitive.Root;
export const DialogTrigger = DialogPrimitive.Trigger;
export const DialogClose = DialogPrimitive.Close;

export function DialogContent({
    className,
    children,
    title,
    description,
    ...props
}: DialogPrimitive.DialogContentProps & { title?: ReactNode; description?: string }) {
    return (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-sb-navy-2/50 backdrop-blur-[2px] data-[state=open]:animate-in data-[state=open]:fade-in-0" />
            <DialogPrimitive.Content
                className={cn(
                    'fixed left-1/2 top-1/2 z-50 w-[min(560px,calc(100vw-24px))] -translate-x-1/2 -translate-y-1/2 rounded-[16px] border border-sb-border bg-sb-surface p-0 shadow-sb-pop outline-none data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 max-h-[85dvh] flex flex-col',
                    className,
                )}
                {...props}
            >
                {(title || description) && (
                    <div className="px-5 pt-5 pb-3 border-b border-sb-border">
                        {title && <DialogPrimitive.Title className="text-[16px] font-bold">{title}</DialogPrimitive.Title>}
                        {description && (
                            <DialogPrimitive.Description className="text-[13px] text-sb-muted mt-1">
                                {description}
                            </DialogPrimitive.Description>
                        )}
                    </div>
                )}
                <div className="overflow-y-auto sb-scroll flex-1">{children}</div>
                <DialogPrimitive.Close className="absolute right-4 top-4 rounded-lg p-1.5 text-sb-muted hover:bg-sb-hover hover:text-sb-text outline-none focus-visible:ring-2 focus-visible:ring-sb-primary/40" aria-label="Tutup">
                    <X size={17} />
                </DialogPrimitive.Close>
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
}
