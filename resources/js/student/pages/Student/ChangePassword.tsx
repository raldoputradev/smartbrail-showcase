import { useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Check, Eye, EyeOff, KeyRound, MailCheck, Send, X } from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHead, Crumb } from '@/components/PageHead';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { AuthUser, SharedProps } from '@/types';
import { asset } from '@/lib/asset';
import { cn } from '@/lib/utils';

interface Props extends SharedProps {
    user: AuthUser;
    isMandatory: boolean;
}

const RULES: Array<{ key: string; label: string; test: (p: string) => boolean }> = [
    { key: 'len', label: 'Minimal 8 karakter', test: (p) => p.length >= 8 },
    { key: 'lower', label: 'Huruf kecil (a-z)', test: (p) => /[a-z]/.test(p) },
    { key: 'upper', label: 'Huruf besar (A-Z)', test: (p) => /[A-Z]/.test(p) },
    { key: 'num', label: 'Angka', test: (p) => /[0-9]/.test(p) },
    { key: 'sym', label: 'Simbol (@ # ! , . _)', test: (p) => /[^A-Za-z0-9]/.test(p) },
];

function PwField({ id, label, value, onChange, error, autoComplete, placeholder }: {
    id: string; label: string; value: string; onChange: (v: string) => void;
    error?: string; autoComplete: string; placeholder: string;
}) {
    const [show, setShow] = useState(false);
    return (
        <div>
            <label htmlFor={id} className="mb-1.5 block text-[12.5px] font-bold">{label}</label>
            <div className="relative">
                <Input id={id} type={show ? 'text' : 'password'} value={value}
                    onChange={(e) => onChange(e.target.value)} autoComplete={autoComplete}
                    placeholder={placeholder} aria-invalid={!!error} className="pr-10" />
                <button type="button" onClick={() => setShow(!show)} aria-label={show ? 'Sembunyikan' : 'Tampilkan'}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-sb-muted hover:text-sb-text">
                    {show ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
            </div>
            {error && <p className="mt-1.5 text-[12px] font-medium text-sb-danger">{error}</p>}
        </div>
    );
}

export default function ChangePassword({ user, isMandatory }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [method, setMethod] = useState<'current' | 'email'>('current');

    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    const emailForm = useForm({});

    const passed = RULES.filter((r) => r.test(form.data.password)).length;
    const strength = form.data.password ? Math.round((passed / RULES.length) * 100) : 0;
    const matchState = useMemo(() => {
        if (!form.data.password_confirmation) return 'idle';
        return form.data.password === form.data.password_confirmation ? 'ok' : 'bad';
    }, [form.data.password, form.data.password_confirmation]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('mahasiswa.password.update'), { preserveScroll: true });
    };

    return (
        <div className="space-y-4 sb-fade-in">
            <PageHead
                crumb={<Crumb items={[{ label: 'Akun' }, { label: 'Keamanan' }]} />}
                title={isMandatory ? 'Buat Password Baru' : 'Ganti Password'}
                sub={isMandatory
                    ? 'Akun Anda masih memakai password sementara — buat password pribadi untuk mulai menggunakan portal.'
                    : 'Perbarui password akun atau atur ulang lewat email terdaftar.'}
            />

            <div className="grid items-start gap-4 lg:grid-cols-[5fr_7fr]">
                {/* Panel ilustrasi — disembunyikan di HP */}
                <Card className="overflow-hidden max-lg:hidden">
                    <div className="sb-pw-hero p-0">
                        <img
                            src={asset('images/password-security.jpg')}
                            alt="Ilustrasi pemulihan password SmartBrail"
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    </div>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        {!isMandatory && (
                            <div className="mb-5 flex gap-1 rounded-[10px] bg-sb-surface2 p-1" role="tablist" aria-label="Metode ganti password">
                                {([
                                    ['current', KeyRound, 'Password saat ini'],
                                    ['email', MailCheck, 'Pemulihan via email'],
                                ] as const).map(([v, Icon, label]) => (
                                    <button key={v} role="tab" aria-selected={method === v} type="button"
                                        onClick={() => setMethod(v)}
                                        className={cn('flex flex-1 items-center justify-center gap-2 rounded-[8px] px-3 py-2 text-[12.5px] font-semibold transition',
                                            method === v ? 'bg-sb-surface text-sb-primary shadow-sm' : 'text-sb-muted hover:text-sb-text')}>
                                        <Icon size={14} /> {label}
                                    </button>
                                ))}
                            </div>
                        )}

                        {method === 'current' || isMandatory ? (
                            <form onSubmit={submit} className="space-y-4" noValidate>
                                {isMandatory && (
                                    <p className="rounded-[10px] border border-sb-warn/30 bg-sb-warn-bg/60 px-3.5 py-3 text-[12.5px] text-sb-text2">
                                        Akun <strong>{user.nama_lengkap}</strong> masih memakai password sementara. Buat password pribadi baru untuk mulai menggunakan portal.
                                    </p>
                                )}
                                {flash.success && (
                                    <p role="status" className="rounded-[10px] border border-sb-ok/30 bg-sb-ok-bg/60 px-3.5 py-3 text-[12.5px] font-medium text-sb-ok">{flash.success}</p>
                                )}
                                {!isMandatory && (
                                    <PwField id="current_password" label="Password Saat Ini" value={form.data.current_password}
                                        onChange={(v) => form.setData('current_password', v)}
                                        error={form.errors.current_password} autoComplete="current-password"
                                        placeholder="Masukkan password saat ini" />
                                )}
                                <PwField id="password" label="Password Baru" value={form.data.password}
                                    onChange={(v) => form.setData('password', v)}
                                    error={form.errors.password} autoComplete="new-password"
                                    placeholder="Buat password baru (minimal 8 karakter)" />

                                {/* Meter + checklist */}
                                <div aria-live="polite">
                                    <div className="flex items-center justify-between text-[11.5px]">
                                        <span className="font-semibold text-sb-muted">Kekuatan password</span>
                                        {form.data.password && (
                                            <span className={cn('font-bold', strength === 100 ? 'text-sb-ok' : strength >= 60 ? 'text-sb-warn' : 'text-sb-danger')}>
                                                {strength === 100 ? 'Kuat' : strength >= 60 ? 'Sedang' : 'Lemah'}
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-sb-surface2">
                                        <div className={cn('h-full rounded-full transition-all',
                                            strength === 100 ? 'bg-sb-ok' : strength >= 60 ? 'bg-sb-warn' : 'bg-sb-danger')}
                                            style={{ width: `${Math.max(strength, form.data.password ? 8 : 0)}%` }} />
                                    </div>
                                    <ul className="mt-3 grid grid-cols-2 gap-1.5 max-sm:grid-cols-1">
                                        {RULES.map((r) => {
                                            const ok = r.test(form.data.password);
                                            return (
                                                <li key={r.key} className={cn('flex items-center gap-1.5 text-[11.5px]',
                                                    ok ? 'text-sb-ok' : 'text-sb-muted')}>
                                                    {ok ? <Check size={12} /> : <X size={12} />} {r.label}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>

                                <div>
                                    <PwField id="password_confirmation" label="Ulangi Password Baru" value={form.data.password_confirmation}
                                        onChange={(v) => form.setData('password_confirmation', v)}
                                        error={form.errors.password_confirmation} autoComplete="new-password"
                                        placeholder="Ketik ulang password baru" />
                                    {matchState !== 'idle' && (
                                        <p className={cn('mt-1.5 flex items-center gap-1 text-[12px] font-medium', matchState === 'ok' ? 'text-sb-ok' : 'text-sb-danger')}>
                                            {matchState === 'ok' ? <Check size={12} /> : <X size={12} />}
                                            {matchState === 'ok' ? 'Password cocok' : 'Password belum cocok'}
                                        </p>
                                    )}
                                </div>

                                <Button type="submit" loading={form.processing} className="w-full">
                                    <Send size={15} />
                                    {isMandatory ? 'Simpan & Mulai Menggunakan Portal' : 'Simpan Password Baru'}
                                </Button>
                            </form>
                        ) : (
                            <div className="space-y-4">
                                <div className="rounded-[10px] border border-sb-border bg-sb-surface2 px-4 py-3.5 text-[13px] text-sb-text2">
                                    <h4 className="font-bold text-sb-text">Atur ulang lewat email terdaftar</h4>
                                    <p className="mt-1">
                                        {user.email
                                            ? <>Kami akan mengirim tautan <strong>&quot;Atur Ulang Password&quot;</strong> ke <strong>{user.email}</strong>. Tautan berlaku terbatas dan hanya sekali pakai.</>
                                            : 'Akun Anda belum memiliki email terikat. Minta password sementara baru kepada admin/laboran untuk masuk kembali.'}
                                    </p>
                                </div>
                                {user.email && (
                                    <>
                                        {flash.success && (
                                            <p role="status" className="rounded-[10px] border border-sb-ok/30 bg-sb-ok-bg/60 px-3.5 py-3 text-[12.5px] font-medium text-sb-ok">{flash.success}</p>
                                        )}
                                        <Button loading={emailForm.processing} className="w-full"
                                            onClick={() => emailForm.post(route('mahasiswa.password.reset-link'))}>
                                            <MailCheck size={15} /> Kirim Tautan Atur Ulang
                                        </Button>
                                    </>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

ChangePassword.layout = (page: React.ReactNode) => <AppShell title="Ganti Password">{page}</AppShell>;
