import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export const Checkbox = ({
    className,
    ...props
}: CheckboxPrimitive.CheckboxProps) => (
    <CheckboxPrimitive.Root
        className={cn(
            'peer h-[18px] w-[18px] shrink-0 rounded-[5px] border border-sb-border-strong bg-sb-surface transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-sb-primary/25 data-[state=checked]:border-sb-primary data-[state=checked]:bg-sb-primary data-[state=checked]:text-white disabled:cursor-not-allowed disabled:opacity-50',
            className,
        )}
        {...props}
    >
        <CheckboxPrimitive.Indicator className="flex items-center justify-center">
            <Check size={13} strokeWidth={3} />
        </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
);
