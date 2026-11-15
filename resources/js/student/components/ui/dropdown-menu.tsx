import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { cn } from '@/lib/utils';

export const DropdownMenu = DropdownMenuPrimitive.Root;
export const DropdownMenuTrigger = DropdownMenuPrimitive.Trigger;

export function DropdownMenuContent({
    className,
    sideOffset = 6,
    ...props
}: DropdownMenuPrimitive.DropdownMenuContentProps) {
    return (
        <DropdownMenuPrimitive.Portal>
            <DropdownMenuPrimitive.Content
                sideOffset={sideOffset}
                collisionPadding={10}
                className={cn(
                    'z-50 min-w-[180px] overflow-hidden rounded-[12px] border border-sb-border bg-sb-surface p-1.5 shadow-sb-pop data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    className,
                )}
                {...props}
            />
        </DropdownMenuPrimitive.Portal>
    );
}

export function DropdownMenuItem({ className, ...props }: DropdownMenuPrimitive.DropdownMenuItemProps) {
    return (
        <DropdownMenuPrimitive.Item
            className={cn(
                'flex cursor-pointer select-none items-center gap-2.5 rounded-[8px] px-3 py-2 text-[13.5px] font-medium text-sb-text2 outline-none transition-colors focus:bg-sb-hover focus:text-sb-text data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:size-[15px] [&_svg]:text-sb-muted',
                className,
            )}
            {...props}
        />
    );
}

export function DropdownMenuSeparator({ className, ...props }: DropdownMenuPrimitive.DropdownMenuSeparatorProps) {
    return <DropdownMenuPrimitive.Separator className={cn('my-1.5 h-px bg-sb-border', className)} {...props} />;
}
