import { Link } from '@inertiajs/react';
import { BellRing, Home, Rocket } from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHead, Crumb } from '@/components/PageHead';
import type { SharedProps } from '@/types';

export default function ComingSoon({}: SharedProps) {
    return (
        <>
            <PageHead
                crumb={<Crumb items={[{ label: 'Beranda', href: route('mahasiswa.beranda') }, { label: 'Coming Soon' }]} />}
                title="Coming Soon"
                sub="Fitur baru yang sedang disiapkan untuk Anda"
            />
            <div className="flex min-h-[55vh] items-center justify-center px-4 py-8">
                <div className="relative w-full max-w-lg overflow-hidden rounded-2xl border border-sb-border bg-sb-card p-10 text-center shadow-sm">
                    <div aria-hidden="true" className="pointer-events-none absolute -top-16 left-1/2 h-40 w-40 -translate-x-1/2 rounded-full bg-sb-primary/15 blur-2xl" />
                    <div className="relative mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-sb-primary to-sb-accent text-white shadow-lg shadow-sb-primary/30">
                        <Rocket size={30} strokeWidth={1.8} />
                    </div>
                    <span className="relative mt-5 inline-flex items-center gap-1.5 rounded-full bg-sb-primary-soft px-3 py-1 text-[11px] font-bold uppercase tracking-widest text-sb-primary">
                        <BellRing size={12} /> Segera Hadir
                    </span>
                    <h1 className="relative mt-3 text-[22px] font-extrabold tracking-tight text-sb-text">
                        Fitur ini sedang dalam pengembangan
                    </h1>
                    <p className="relative mx-auto mt-2 max-w-sm text-[13.5px] leading-relaxed text-sb-muted">
                        Tim AIOT SmartBrail sedang menyiapkan fitur baru untuk Anda.
                        Pantau terus — kami akan mengumumkannya lewat notifikasi saat sudah siap dipakai.
                    </p>
                    <Link
                        href={route('mahasiswa.beranda')}
                        className="relative mt-6 inline-flex items-center gap-2 rounded-xl bg-sb-primary px-5 py-2.5 text-[13px] font-bold text-white shadow-sm transition hover:brightness-110"
                    >
                        <Home size={15} /> Kembali ke Beranda
                    </Link>
                </div>
            </div>
        </>
    );
}

ComingSoon.layout = (page: React.ReactNode) => <AppShell title="Coming Soon">{page}</AppShell>;
