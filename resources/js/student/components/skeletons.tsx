import { Skeleton } from '@/components/ui/skeleton';

function Bar({ className }: { className?: string }) {
    return <Skeleton className={className} />;
}

/** Skeleton per halaman — bentuk mengikuti susunan konten. */

export function DashboardSkeleton() {
    return (
        <div className="space-y-4" aria-hidden>
            <Bar className="h-24 w-full rounded-[14px]" />
            <div className="grid gap-4 lg:grid-cols-2">
                <Bar className="h-40 rounded-[14px]" />
                <Bar className="h-40 rounded-[14px]" />
            </div>
            <div className="grid gap-4 xl:grid-cols-[7fr_5fr]">
                <Bar className="h-[340px] rounded-[14px]" />
                <Bar className="h-[200px] rounded-[14px]" />
            </div>
            <div className="grid gap-4 xl:grid-cols-[7.5fr_4.5fr]">
                <Bar className="h-52 rounded-[14px]" />
                <Bar className="h-52 rounded-[14px]" />
            </div>
        </div>
    );
}

export function AttendanceSkeleton() {
    return (
        <div className="space-y-4" aria-hidden>
            <Bar className="h-28 w-full rounded-[14px]" />
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[0, 1, 2, 3].map((i) => <Bar key={i} className="h-20 rounded-[14px]" />)}
            </div>
            <div className="rounded-[14px] border border-sb-border bg-sb-surface p-5">
                {[...Array(6)].map((_, i) => (
                    <div key={i} className="flex items-center gap-4 border-b border-sb-border py-3 last:border-0">
                        <Bar className="h-4 w-28" />
                        <Bar className="h-4 w-16" />
                        <Bar className="h-4 w-16" />
                        <Bar className="ml-auto h-5 w-20 rounded-full" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export function CalendarSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]" aria-hidden>
            <div className="rounded-[14px] border border-sb-border bg-sb-surface p-5">
                <Bar className="mb-4 h-8 w-44" />
                <div className="grid grid-cols-7 gap-2">
                    {[...Array(35)].map((_, i) => <Bar key={i} className="h-14 rounded-lg" />)}
                </div>
            </div>
            <Bar className="h-[380px] rounded-[14px]" />
        </div>
    );
}

export function TeamSkeleton() {
    return (
        <div className="space-y-4" aria-hidden>
            <Bar className="h-28 w-full rounded-[14px]" />
            <Bar className="h-36 w-full rounded-[14px]" />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {[...Array(8)].map((_, i) => <Bar key={i} className="h-24 rounded-[14px]" />)}
            </div>
        </div>
    );
}

export function ProfileSkeleton() {
    return (
        <div className="space-y-4" aria-hidden>
            <Bar className="h-36 w-full rounded-[14px]" />
            <div className="grid gap-4 lg:grid-cols-2">
                <Bar className="h-64 rounded-[14px]" />
                <div className="space-y-4">
                    <Bar className="h-40 rounded-[14px]" />
                    <Bar className="h-32 rounded-[14px]" />
                </div>
            </div>
        </div>
    );
}

export function PasswordSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-[5fr_7fr]" aria-hidden>
            <Bar className="h-[420px] rounded-[14px]" />
            <Bar className="h-[420px] rounded-[14px]" />
        </div>
    );
}

export function RoomRequestSkeleton() {
    return (
        <div className="space-y-4" aria-hidden>
            <Bar className="h-24 w-full rounded-[14px]" />
            <div className="grid gap-4 xl:grid-cols-[7fr_5fr]">
                <div className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Bar className="h-36 rounded-[14px]" />
                        <Bar className="h-36 rounded-[14px]" />
                    </div>
                    <Bar className="h-44 rounded-[14px]" />
                </div>
                <Bar className="h-[480px] rounded-[14px]" />
            </div>
        </div>
    );
}

export function ReportHistorySkeleton() {
    return (
        <div className="space-y-4" aria-hidden>
            <Bar className="h-32 w-full rounded-[16px]" />
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {[0, 1, 2, 3].map((i) => <Bar key={i} className="h-24 rounded-[14px]" />)}
            </div>
            <div className="grid items-start gap-4 lg:grid-cols-[7fr_5fr]">
                <div className="rounded-[14px] border border-sb-border bg-sb-surface p-5">
                    <Bar className="mb-4 h-10 w-full rounded-[10px]" />
                    <div className="mb-4 flex gap-2">
                        <Bar className="h-9 flex-1 rounded-[10px]" />
                        <Bar className="h-9 flex-1 rounded-[10px]" />
                        <Bar className="h-9 flex-1 rounded-[10px]" />
                    </div>
                    {[...Array(5)].map((_, i) => (
                        <div key={i} className="mb-3 flex items-center gap-3">
                            <Bar className="h-10 w-10 rounded-[10px]" />
                            <div className="flex-1 space-y-2">
                                <Bar className="h-3 w-20" />
                                <Bar className="h-4 w-2/3" />
                                <Bar className="h-3 w-32" />
                            </div>
                            <Bar className="h-6 w-20 rounded-full" />
                        </div>
                    ))}
                </div>
                <Bar className="h-[440px] rounded-[14px]" />
            </div>
        </div>
    );
}

export function GenericSkeleton() {
    return (
        <div className="space-y-4" aria-hidden>
            <Bar className="h-24 w-full rounded-[14px]" />
            <Bar className="h-64 w-full rounded-[14px]" />
            <Bar className="h-40 w-full rounded-[14px]" />
        </div>
    );
}

export function skeletonFor(url: string) {
    if (url.includes('/absensi')) return <AttendanceSkeleton />;
    if (url.includes('/kalender')) return <CalendarSkeleton />;
    if (url.includes('/tim')) return <TeamSkeleton />;
    if (url.includes('/profil')) return <ProfileSkeleton />;
    if (url.includes('/ganti-password')) return <PasswordSkeleton />;
    if (url.includes('/ruangan')) return <RoomRequestSkeleton />;
    if (url.includes('/saran')) return <ReportHistorySkeleton />;
    if (url.includes('/beranda')) return <DashboardSkeleton />;
    return <GenericSkeleton />;
}
