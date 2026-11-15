import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';

function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function helpdeskUrl() {
  const base = document.querySelector('meta[name="app-url"]')?.content;
  if (base) return `${base.replace(/\/$/, '')}/helpdesk/chat`;
  const path = window.location.pathname;
  const root = path.includes('/public/')
    ? path.slice(0, path.indexOf('/public/') + '/public'.length)
    : '';
  return `${window.location.origin}${root}/helpdesk/chat`;
}

const SUGGESTIONS = [
  'Apa itu AIoT SIMALAS?',
  'Cara unduh & instal APK?',
  'Mahasiswa login di mana?',
  'Bagaimana enroll sidik jari?',
];

function Avatar({ role }) {
  const isUser = role === 'user';
  return (
    <div
      className={`flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border ${
        isUser
          ? 'border-orange-200 bg-gradient-to-br from-rose-100 to-orange-100 text-rose-600'
          : 'border-rose-200 bg-white text-orange-500 shadow-sm'
      }`}
      aria-hidden
    >
      {isUser ? (
        <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8">
          <circle cx="12" cy="8" r="3.2" />
          <path d="M5.5 19.2c1.6-3.2 4-4.8 6.5-4.8s4.9 1.6 6.5 4.8" strokeLinecap="round" />
        </svg>
      ) : (
        <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8">
          <rect x="4" y="6" width="16" height="12" rx="3" />
          <circle cx="9" cy="12" r="1.2" fill="currentColor" stroke="none" />
          <circle cx="15" cy="12" r="1.2" fill="currentColor" stroke="none" />
          <path d="M9 15.5h6" strokeLinecap="round" />
        </svg>
      )}
    </div>
  );
}

export default function ChatWidget({ urls }) {
  const [open, setOpen] = useState(false);
  const [hintOpen, setHintOpen] = useState(false);
  const [input, setInput] = useState('');
  const [busy, setBusy] = useState(false);
  const [aiEnabled, setAiEnabled] = useState(null);
  const [messages, setMessages] = useState([
    {
      role: 'assistant',
      text: 'Selamat datang di AI Helpdesk SIMALAS. Saya siap membantu seputar absensi, akses ruangan, unduh APK, OTP, dan cara menggunakan aplikasi.',
    },
  ]);
  const endRef = useRef(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, open]);

  useEffect(() => {
    const timer = window.setTimeout(() => setHintOpen(true), 1400);
    return () => window.clearTimeout(timer);
  }, []);

  useEffect(() => {
    const onOpen = () => {
      setHintOpen(false);
      setOpen(true);
    };
    window.addEventListener('simalas:open-helpdesk', onOpen);
    return () => window.removeEventListener('simalas:open-helpdesk', onOpen);
  }, []);

  function dismissHint() {
    setHintOpen(false);
  }

  function toggleChat() {
    setOpen((v) => {
      const next = !v;
      if (next) dismissHint();
      return next;
    });
  }

  async function send(text) {
    const q = (text || input).trim();
    if (!q || busy) return;

    setInput('');
    setMessages((m) => [...m, { role: 'user', text: q }]);
    setBusy(true);

    try {
      const history = messages
        .slice(-8)
        .map((m) => ({ role: m.role, content: m.text }));

      const res = await fetch(urls?.helpdeskChat || helpdeskUrl(), {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrf(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ message: q, history }),
      });

      const data = await res.json().catch(() => ({}));
      if (typeof data.ai_enabled === 'boolean') {
        setAiEnabled(data.ai_enabled);
      }

      const reply =
        data.reply ||
        data.message ||
        (res.ok
          ? 'Maaf, saya belum bisa menjawab. Coba tanya tentang absensi, akses ruangan, atau unduh APK.'
          : 'Layanan chat sedang sibuk. Coba lagi sebentar.');

      setMessages((m) => [
        ...m,
        {
          role: 'assistant',
          text: reply,
          source: data.source || null,
        },
      ]);
    } catch {
      setMessages((m) => [
        ...m,
        {
          role: 'assistant',
          text: 'Koneksi gagal. Periksa jaringan lalu coba lagi, atau buka FAQ di halaman ini.',
        },
      ]);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="fixed bottom-5 right-5 z-[150] flex flex-col items-end gap-3">
      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0, y: 16, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 16, scale: 0.96 }}
            transition={{ duration: 0.22 }}
            className="flex h-[min(78vh,620px)] w-[min(94vw,440px)] flex-col overflow-hidden rounded-2xl border border-rose-200/70 bg-white shadow-2xl"
          >
            <div className="bg-gradient-to-r from-rose-600 to-orange-500 px-4 py-4 text-white">
              <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                  <div className="flex h-12 w-12 items-center justify-center rounded-full border border-white/30 bg-white/15">
                    <svg viewBox="0 0 24 24" className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
                      <rect x="4" y="6" width="16" height="12" rx="3" />
                      <circle cx="9" cy="12" r="1.2" fill="currentColor" stroke="none" />
                      <circle cx="15" cy="12" r="1.2" fill="currentColor" stroke="none" />
                      <path d="M9 15.5h6" strokeLinecap="round" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-base font-extrabold leading-tight">AI Helpdesk SIMALAS</p>
                    <p className="mt-1 text-sm text-rose-100">
                      {aiEnabled === false
                        ? 'Mode FAQ (isi GEMINI_API_KEY untuk AI penuh)'
                        : 'Online · Tanya seputar AIoT SIMALAS'}
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setOpen(false)}
                  className="rounded-lg p-1.5 text-lg leading-none text-rose-100 transition hover:bg-white/15 hover:text-white"
                  aria-label="Tutup chat"
                >
                  ✕
                </button>
              </div>
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto bg-gradient-to-b from-orange-50/40 to-white p-4 sm:p-5">
              {messages.map((m, i) => {
                const isUser = m.role === 'user';
                return (
                  <div
                    key={`${m.role}-${i}`}
                    className={`flex items-end gap-2.5 ${isUser ? 'flex-row-reverse' : 'flex-row'}`}
                  >
                    <Avatar role={m.role} />
                    <div className={`max-w-[80%] ${isUser ? 'items-end' : 'items-start'} flex flex-col`}>
                      <span className={`mb-1 text-xs font-semibold uppercase tracking-wide ${isUser ? 'text-right text-rose-500' : 'text-orange-600'}`}>
                        {isUser ? 'Anda' : 'Asisten AI'}
                      </span>
                      <div
                        className={`rounded-2xl px-4 py-3 text-[15px] leading-relaxed ${
                          isUser
                            ? 'rounded-br-md bg-gradient-to-r from-rose-600 to-orange-500 text-white'
                            : 'rounded-bl-md border border-rose-100 bg-white text-slate-700 shadow-sm'
                        }`}
                      >
                        {m.text}
                        {m.role === 'assistant' && m.source === 'gemini' && (
                          <span className="mt-2 block text-[11px] font-semibold uppercase tracking-wide text-orange-600">
                            Gemini AI
                          </span>
                        )}
                        {m.role === 'assistant' && m.source === 'faq' && (
                          <span className="mt-2 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                            FAQ lokal
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
              {busy && (
                <div className="flex items-end gap-2.5">
                  <Avatar role="assistant" />
                  <p className="rounded-2xl rounded-bl-md border border-rose-100 bg-white px-4 py-3 text-sm font-medium text-orange-600 shadow-sm">
                    Asisten mengetik…
                  </p>
                </div>
              )}
              <div ref={endRef} />
            </div>

            <div className="border-t border-rose-100 bg-white p-3.5 sm:p-4">
              <div className="mb-2.5 flex flex-wrap gap-2">
                {SUGGESTIONS.map((s) => (
                  <button
                    key={s}
                    type="button"
                    disabled={busy}
                    onClick={() => send(s)}
                    className="rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-orange-50 hover:text-orange-700 disabled:opacity-50"
                  >
                    {s}
                  </button>
                ))}
              </div>
              <form
                className="flex gap-2"
                onSubmit={(e) => {
                  e.preventDefault();
                  send();
                }}
              >
                <input
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  disabled={busy}
                  maxLength={500}
                  placeholder="Tulis pertanyaan…"
                  className="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-[15px] outline-none ring-rose-400 focus:bg-white focus:ring-2"
                />
                <button
                  type="submit"
                  disabled={busy || !input.trim()}
                  className="rounded-xl bg-gradient-to-r from-rose-600 to-orange-500 px-5 py-3 text-[15px] font-bold text-white disabled:opacity-50"
                >
                  Kirim
                </button>
              </form>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <div className="relative flex items-end justify-end gap-3">
        <AnimatePresence>
          {hintOpen && !open && (
            <motion.div
              initial={{ opacity: 0, x: 14, scale: 0.94 }}
              animate={{ opacity: 1, x: 0, scale: 1 }}
              exit={{ opacity: 0, x: 10, scale: 0.96 }}
              transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
              className="relative mb-2 mr-1 max-w-[min(78vw,280px)]"
            >
              <button
                type="button"
                onClick={dismissHint}
                className="absolute -right-2 -top-2 z-20 flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-bold text-slate-500 shadow-md transition hover:border-rose-200 hover:text-rose-600"
                aria-label="Tutup petunjuk bantuan"
              >
                ✕
              </button>

              <button
                type="button"
                onClick={toggleChat}
                className="relative w-full rounded-2xl border border-rose-200/80 bg-white px-5 py-4 text-left shadow-xl shadow-slate-900/15 transition hover:border-rose-300 hover:shadow-2xl"
              >
                <p className="bg-gradient-to-r from-rose-600 to-orange-500 bg-clip-text text-lg font-semibold italic leading-snug text-transparent sm:text-xl">
                  Butuh bantuan?
                </p>
                <p className="mt-1 text-sm font-medium text-slate-500">
                  Ada pertanyaan seputar SIMALAS
                </p>
                <span
                  className="absolute -right-1.5 bottom-5 h-3.5 w-3.5 rotate-45 rounded-sm border-b border-r border-rose-200/80 bg-white"
                  aria-hidden
                />
              </button>

              <span
                className="absolute -right-4 bottom-6 h-2 w-2 rounded-full bg-rose-500"
                aria-hidden
              />
            </motion.div>
          )}
        </AnimatePresence>

        <motion.button
          type="button"
          onClick={toggleChat}
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.96 }}
          className="relative flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-rose-600 to-orange-500 text-white shadow-lg shadow-rose-600/35 transition hover:from-rose-500 hover:to-orange-400"
          aria-label={open ? 'Tutup bantuan' : 'Buka AI helpdesk'}
          aria-expanded={open}
        >
          {!open && hintOpen && (
            <span className="absolute inset-0 animate-ping rounded-full bg-rose-400/35" aria-hidden />
          )}
          {open ? (
            <svg viewBox="0 0 24 24" className="relative h-7 w-7" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden>
              <path d="M18 6L6 18M6 6l12 12" strokeLinecap="round" />
            </svg>
          ) : (
            <svg viewBox="0 0 24 24" className="relative h-7 w-7" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
              <path
                d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          )}
        </motion.button>
      </div>
    </div>
  );
}
