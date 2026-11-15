import * as AvatarPrimitive from '@radix-ui/react-avatar';
import { cn } from '@/lib/utils';
import { initials } from '@/lib/format';

export function Avatar({
    nama,
    src,
    className,
}: {
    nama?: string | null;
    src?: string | null;
    className?: string;
}) {
    return (
        <AvatarPrimitive.Root
            className={cn(
                'relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full bg-sb-primary-soft text-sb-primary',
                className,
            )}
        >
            {src ? (
                <AvatarPrimitive.Image src={src} alt={nama ?? ''} className="h-full w-full object-cover" />
            ) : null}
            <AvatarPrimitive.Fallback className="flex h-full w-full items-center justify-center text-[13px] font-bold">
                {initials(nama)}
            </AvatarPrimitive.Fallback>
        </AvatarPrimitive.Root>
    );
}
