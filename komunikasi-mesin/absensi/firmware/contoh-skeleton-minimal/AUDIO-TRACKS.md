# Track MP3 — siap uji

## Simpan
`/mp3/0001.mp3` … `/mp3/0011.mp3` (FAT32)

| File | Teks TTS | Kapan |
|------|----------|-------|
| `0001` | Sedang menghubungkan ke jaringan. | Boot / reconnect — **tunggu selesai** sebelum step berikutnya |
| `0002` | Koneksi berhasil. Mesin absensi siap digunakan. | **Setelah** OLED home tampil |
| `0003` | Gagal terhubung ke jaringan. | WiFi gagal |
| `0004` | Absensi berhasil, terima kasih. | Tap OK |
| `0005` | Sidik jari tidak dikenali. | Jari tidak cocok |
| `0006` | Silakan tempelkan jari Anda. | Enroll |
| `0007` | Angkat jari Anda, lalu tempelkan kembali. | Enroll |
| `0008` | Registrasi sidik jari berhasil. | Enroll OK |
| `0009` | Registrasi gagal. Silakan ulangi. | Enroll gagal |
| **`0010`** | **Belum waktunya absen pulang. Silakan menunggu jam pulang.** | Tap pulang terlalu awal |
| **`0011`** | **Maaf, absensi tidak dapat dilakukan.** | Hari libur / **bukan hari wajib** (`BUKAN_HARI_WAJIB`) / sesi tutup (`SESI_DITUTUP`) — **bukan** `0005` |

## Urutan boot (audio)
1. Putar `0001` (WiFi connect berjalan bersamaan).
2. **Tunggu `0001` selesai** (event DFPlayer / min ~4.5s, max ~10s) — tidak di-`stop` paksa.
3. Sync NTP / heartbeat / fingerprint (bisa lama).
4. Tampilkan OLED home.
5. Baru putar `0002`.

Jika kalimat `0001` masih keputus, naikkan `AUDIO_NET_CONNECTING_MIN_MS` di `audio_player.h`.

## OLED hasil absen
- Berhasil (nama dll): tampil **~4 detik**
- Gagal / tidak dikenali / libur: tampil **~3.5 detik**

## Urutan enroll (demo — wajib cocok)

| Step | Aksi kamu | OLED | Serial / audio |
|------|-----------|------|----------------|
| 1 | Ketik finger_id → D | `Cek data...` | — |
| 2 | (otomatis) | `Tempel jari...` | **`0006`** Silakan tempelkan jari |
| 3 | **Tap 1** (tempel sampai OK) | `Angkat jari...` | **`0007`** Angkat jari… (**tunggu ~3s**, jangan angkat terlalu cepat) |
| 4 | Angkat jari (setelah 0007 selesai) | `Tempel lagi...` | **`0006`** lagi |
| 5 | **Tap 2** | `Enroll OK!` / gagal | **`0008`** / **`0009`** |

Serial yang benar setelah tap 1:
```
[Enroll] scan1 OK → 0007 angkat jari
[Audio] play /mp3/0007.mp3
… (tunggu ≥3 detik) …
[Enroll] jari angkat OK → scan2 + 0006
[Audio] play /mp3/0006.mp3
```

`0005` tidak boleh bunyi di enroll.
