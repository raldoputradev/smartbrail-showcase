import { Reveal } from './Reveal';

const STEPS = [
  {
    title: 'Unduh file APK',
    desc: 'Ketuk tombol "Unduh Sekarang" di halaman ini. File disimpan di folder Unduhan (Downloads) perangkat Anda.',
  },
  {
    title: 'Buka file APK',
    desc: 'Buka notifikasi unduhan, atau buka aplikasi Files / Pengelola File → folder Unduhan → AIoT-SIMALAS-Mobile.apk.',
  },
  {
    title: 'Izinkan instalasi',
    desc: 'Jika muncul peringatan keamanan, pilih Pengaturan → aktifkan "Izinkan dari sumber ini" untuk browser atau file manager yang Anda gunakan, lalu kembali dan lanjutkan instal.',
  },
  {
    title: 'Selesaikan instalasi',
    desc: 'Ketuk Instal / Install dan tunggu hingga proses selesai. Pastikan ruang penyimpanan cukup (±60 MB).',
  },
  {
    title: 'Buka aplikasi',
    desc: 'Setelah terpasang, buka SmartBrail dari launcher. Pastikan perangkat terhubung internet (Wi‑Fi atau data).',
  },
  {
    title: 'Daftar akun mahasiswa',
    desc: 'Pilih tab Daftar, isi data diri & akademik, verifikasi CAPTCHA, lalu kirim OTP ke email. Mahasiswa tidak mendaftar lewat website.',
  },
  {
    title: 'Verifikasi OTP email',
    desc: 'Masukkan 6 digit OTP dari email (cek folder spam/promosi jika tidak ada di inbox). Kode berlaku ±10 menit.',
  },
  {
    title: 'Tunggu persetujuan admin',
    desc: 'Setelah OTP valid, akun menunggu disetujui laboran. Anda akan bisa login setelah status akun disetujui.',
  },
];

const TIPS = [
  'Gunakan Android 8.0 (Oreo) atau lebih baru untuk hasil terbaik.',
  'Jika unduhan gagal, coba browser lain atau unduh ulang lewat Wi‑Fi stabil.',
  'Untuk reset password, gunakan menu Lupa Password di aplikasi (OTP dikirim ke email terdaftar).',
  'Portal web admin/dosen terpisah — mahasiswa hanya memakai aplikasi mobile.',
];

export default function InstallGuide() {
  return (
    <section id="panduan" className="scroll-mt-24">
      <Reveal>
        <h2 className="text-center text-2xl font-extrabold text-slate-900 dark:text-white">
          Panduan Instalasi Lengkap
        </h2>
        <p className="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-600 dark:text-slate-300">
          Ikuti langkah berikut dari unduh hingga siap login di aplikasi SmartBrail.
        </p>
      </Reveal>

      <ol className="mt-10 space-y-4">
        {STEPS.map((step, i) => (
          <Reveal key={step.title} delay={i * 0.04}>
            <li className="flex gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-white/10 dark:bg-[#1c2540]">
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-cyan-500 text-sm font-extrabold text-white">
                {i + 1}
              </span>
              <div>
                <h3 className="font-bold text-slate-900 dark:text-white">{step.title}</h3>
                <p className="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                  {step.desc}
                </p>
              </div>
            </li>
          </Reveal>
        ))}
      </ol>

      <Reveal className="mt-10" delay={0.1}>
        <div className="rounded-2xl border border-blue-200/80 bg-blue-50/80 p-6 dark:border-blue-500/20 dark:bg-blue-500/10">
          <h3 className="text-sm font-bold uppercase tracking-wide text-blue-800 dark:text-blue-200">
            Tips tambahan
          </h3>
          <ul className="mt-3 space-y-2">
            {TIPS.map((tip) => (
              <li key={tip} className="flex gap-2 text-sm text-blue-900/90 dark:text-blue-100/90">
                <span className="text-cyan-600 dark:text-cyan-400">•</span>
                {tip}
              </li>
            ))}
          </ul>
        </div>
      </Reveal>
    </section>
  );
}
