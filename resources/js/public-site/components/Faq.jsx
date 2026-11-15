import { AnimatePresence, motion } from 'framer-motion';
import { useState } from 'react';
import { Reveal } from './Reveal';

const FAQS = [
  {
    q: 'Bagaimana cara mahasiswa mendaftar?',
    a: 'Unduh aplikasi mobile, isi data diri & akademik, verifikasi OTP email, lalu tunggu persetujuan admin laboratorium.',
  },
  {
    q: 'Apakah mahasiswa punya portal web?',
    a: 'Ya. Mahasiswa bisa login portal web SmartBrail untuk absensi, jadwal, pengajuan ruangan tim PBL, dan bantuan — selain aplikasi mobile.',
  },
  {
    q: 'Apakah aplikasi mobile sudah bisa diunduh?',
    a: 'Belum — rilis mobile menyusul. Sementara gunakan portal web SmartBrail yang berfungsi penuh di browser HP maupun laptop.',
  },
  {
    q: 'Di mana admin, dosen, dan laboran login?',
    a: 'Mereka login di portal web SmartBrail yang sama — tampilan dashboard menyesuaikan peran masing-masing.',
  },
  {
    q: 'Berapa lama menunggu persetujuan akun setelah OTP?',
    a: 'Setelah OTP email berhasil diverifikasi, akun masuk antrian persetujuan laboran. Waktu persetujuan bergantung jadwal admin — biasanya 1–3 hari kerja.',
  },
  {
    q: 'OTP email tidak masuk, apa yang harus dilakukan?',
    a: 'Cek folder spam/promosi, pastikan email benar, tunggu 1–2 menit, lalu gunakan tombol kirim ulang OTP di aplikasi.',
  },
  {
    q: 'Apakah SmartBrail bisa dipakai di iPhone?',
    a: 'Portal web bisa dibuka di browser mana pun. Aplikasi mobile saat ini Android — versi iOS belum dirilis.',
  },
  {
    q: 'Bagaimana cara enroll sidik jari pertama kali?',
    a: 'Setelah akun disetujui, datang ke mesin ESP32 di laboratorium dan ikuti petunjuk enroll sidik jari.',
  },
  {
    q: 'Bisa absen tanpa aplikasi mobile?',
    a: 'Tap sidik jari dilakukan di mesin fisik, tetapi pendaftaran, profil, dan riwayat absensi memerlukan aplikasi mobile.',
  },
  {
    q: 'Bagaimana jika lupa password?',
    a: 'Buka aplikasi → Lupa Password → masukkan email → verifikasi OTP → buat password baru.',
  },
  {
    q: 'Bagaimana cara mahasiswa memakai ruangan lab?',
    a: 'Melalui tim PBL — ajukan ruangan di portal/app dengan jadwal & peserta. Laboran menyetujui, lalu akses berlaku sesuai jadwal.',
  },
  {
    q: 'Apakah data sidik jari aman?',
    a: 'Template sidik jari disimpan terenkripsi di server. Komunikasi mesin ESP32 dilindungi kunci autentikasi perangkat.',
  },
  {
    q: 'Bagaimana cara update aplikasi?',
    a: 'Unduh APK terbaru dari halaman unduh resmi SmartBrail. Aplikasi juga memberi notifikasi jika ada pembaruan penting.',
  },
];

function FaqItem({ item, isOpen, onToggle }) {
  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-[#1c2540]">
      <button
        type="button"
        onClick={onToggle}
        className="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left text-sm font-semibold text-slate-900 dark:text-white"
      >
        <span>{item.q}</span>
        <motion.span animate={{ rotate: isOpen ? 180 : 0 }} className="shrink-0 text-slate-400">
          ▾
        </motion.span>
      </button>
      <AnimatePresence initial={false}>
        {isOpen && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.26 }}
          >
            <p className="border-t border-slate-100 px-4 py-3 text-sm leading-relaxed text-slate-600 dark:border-white/10 dark:text-slate-300">
              {item.a}
            </p>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

export default function Faq() {
  const [open, setOpen] = useState(0);
  const leftFaqs = FAQS.filter((_, i) => i % 2 === 0);
  const rightFaqs = FAQS.filter((_, i) => i % 2 === 1);

  const renderColumn = (items, offset) =>
    items.map((item, i) => {
      const idx = offset + i * 2;
      return (
        <FaqItem
          key={item.q}
          item={item}
          isOpen={open === idx}
          onToggle={() => setOpen(open === idx ? -1 : idx)}
        />
      );
    });

  return (
    <section
      id="faq"
      className="scroll-mt-24 border-b border-slate-200/80 bg-[#f6f8fb] py-20 dark:border-white/10 dark:bg-[#0f172a]"
    >
      <div className="mx-auto max-w-6xl px-5 lg:px-8">
        <Reveal>
          <div className="mb-10 text-center lg:text-left">
            <span className="text-xs font-bold uppercase tracking-[0.2em] text-blue-600 dark:text-blue-400">
              FAQ
            </span>
            <h2 className="mt-3 text-3xl font-extrabold text-slate-900 dark:text-white">
              Pertanyaan yang sering ditanyakan
            </h2>
          </div>
        </Reveal>

        <div className="grid gap-8 lg:grid-cols-[minmax(0,280px)_1fr] lg:items-start">
          <Reveal>
            <div className="sticky top-24 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1c2540]">
              <h3 className="text-lg font-extrabold text-slate-900 dark:text-white">Masih ada pertanyaan?</h3>
              <p className="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                Tim kami siap membantu terkait registrasi, absensi, dan penggunaan aplikasi mobile
                SmartBrail.
              </p>
              <div className="mt-5 flex flex-col gap-2.5">
                <a
                  href="#hubungi"
                  className="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-cyan-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-blue-600/20 transition hover:from-blue-500 hover:to-cyan-400"
                >
                  Hubungi via Sosmed →
                </a>
                <button
                  type="button"
                  onClick={() => window.dispatchEvent(new CustomEvent('simalas:open-helpdesk'))}
                  className="inline-flex w-full items-center justify-center rounded-xl border-2 border-blue-500 bg-white px-4 py-3 text-sm font-bold text-blue-600 transition hover:bg-blue-50 dark:border-blue-400 dark:bg-transparent dark:text-blue-300 dark:hover:bg-white/5"
                >
                  Tanya AI Helpdesk →
                </button>
              </div>
            </div>
          </Reveal>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-3">{renderColumn(leftFaqs, 0)}</div>
            <div className="space-y-3">{renderColumn(rightFaqs, 1)}</div>
          </div>
        </div>
      </div>
    </section>
  );
}
