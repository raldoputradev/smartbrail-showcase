import './app.css';
import { Component, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { route as ziggyRoute } from 'ziggy-js';
import { resolvePageComponent } from './lib/resolve-page';

// Helper route() global — memakai konfigurasi Ziggy dari @routes di app-admin.blade.php.
window.route = ((name?: string, params?: unknown, absolute?: boolean) =>
    ziggyRoute(name as string, params as never, absolute, Ziggy) as string) as typeof route;

// Error boundary — kegagalan render tidak boleh menghasilkan layar kosong.
class RootErrorBoundary extends Component<{ children: ReactNode }, { error: Error | null }> {
    state = { error: null as Error | null };
    static getDerivedStateFromError(error: Error) { return { error }; }
    render() {
        if (!this.state.error) return this.props.children;
        return (
            <div style={{ minHeight: '100dvh', display: 'grid', placeItems: 'center', padding: 24, fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' }}>
                <div style={{ maxWidth: 420, textAlign: 'center' }}>
                    <p style={{ fontWeight: 800, fontSize: 17, color: '#0e1e3c' }}>Halaman gagal dimuat</p>
                    <p style={{ marginTop: 8, fontSize: 13, color: '#5a6b8a' }}>
                        Terjadi kesalahan saat menampilkan halaman. Coba muat ulang; bila berulang, laporkan ke petugas.
                    </p>
                    <button
                        onClick={() => window.location.reload()}
                        style={{ marginTop: 16, padding: '10px 20px', borderRadius: 10, border: 0, background: '#1d6ff2', color: '#fff', fontWeight: 700, cursor: 'pointer' }}
                    >
                        Muat Ulang
                    </button>
                    <p style={{ marginTop: 12, fontSize: 11, color: '#8a97ad', wordBreak: 'break-word' }}>{this.state.error.message}</p>
                </div>
            </div>
        );
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} — SmartBrail` : 'SmartBrail — Portal Admin'),
    resolve: (name) =>
        resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(
            <RootErrorBoundary>
                <App {...props} />
            </RootErrorBoundary>,
        );
    },
    progress: { color: '#38bdf8', delay: 120, includeCSS: false, showSpinner: false },
});
