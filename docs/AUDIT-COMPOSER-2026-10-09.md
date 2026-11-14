# Composer audit SIMALAS — 2026-10-09

Sumber: `composer audit --format=json` (PHP 8.3, baca-saja). Belum ada update yang dijalankan.
Kolom *target aman* diverifikasi dengan Composer Semver: versi target **tidak** masuk rentang terdampak.

Usulan update minimum (7 paket, sudah dicek `--dry-run`, semua patch/minor dalam major yang sama):

```
C:\tools\php83\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar update laravel/framework:13.30.* symfony/mime symfony/mailer guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises league/commonmark
```

## Paket usulan

### laravel/framework — terpasang 13.8.0 → target 13.30.1

Langsung terdampak. Rule `email` dipakai di AuthController, OTPController, MobileAuthController, AdminController lalu alamatnya dikirimi email (CRLF, high). `URL::temporarySignedRoute` dipakai link verifikasi di OtpMail + middleware `signed` (path confusion). Halaman debug XSS relevan saat APP_DEBUG=true (sekarang sudah false, risiko turun).

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-d5tc-s1qs-h781 | CVE-2026-102279 | low | Laravel: XSS in Debug Page Information | >=13.0.0,<13.30.0 \| <12.69.0 | ya | https://github.com/advisories/GHSA-jh5r-qr3c-85q8 |
| PKSA-m5cs-t1y6-qpcs | - | medium | Laravel Framework: Temporary Signed URL Path Confusion | <12.61.1 \| >=13.0.0,<13.12.0 | ya | https://github.com/advisories/GHSA-crmm-hgp2-wgrp |
| PKSA-3r5d-mb8f-1qw9 | - | high | Laravel Framework: CRLF injection in default email rule  | <12.60.0 \| >=13.0.0,<=13.9.0 | ya | https://github.com/advisories/GHSA-5vg9-5847-vvmq |
| PKSA-mdq4-51ck-6kdq | CVE-2026-48019 | - | Laravel CRLF injection in default email rule | >=9.0.0,<10.0.0 \| >=10.0.0,<11.0.0 \| >=11.0.0,<12.0.0 \| >=12.0.0,<12.60.0 \| >=13.0.0,<13.10.0 | ya | https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq |

### symfony/mime — terpasang 7.4.9 → target 7.4.19

Terdampak: `Address` dan header email dibangun dari alamat yang diisi pengguna (OTP registrasi/reset, status akun). Berpasangan dengan advisori rule `email` Laravel.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-wtxr-p26d-nn42 | CVE-2026-45070 | medium | CVE-2026-45070: Email Header Injection via Non-Token Characters in Mime Parameter Names | >=2.0.0,<3.0.0 \| >=3.0.0,<4.0.0 \| >=4.0.0,<5.0.0 \| >=5.0.0,<5.1.0 \| >=5.1.0,<5.2.0 \| >=5.2.0,<5.3.0 \| >=5.3.0,<5.4.0 \| >=5.4.0,<5.4.52 \| >=6.0.0,<6.1.0 \| >=6.1.0,<6.2.0 \| >=6.2.0,<6.3.0 \| >=6.3.0,<6.4.0 \| >=6.4.0,<6.4.40 \| >=7.0.0,<7.1.0 \| >=7.1.0,<7.2.0 \| >=7.2.0,<7.3.0 \| >=7.3.0,<7.4.0 \| >=7.4.0,<7.4.12 \| >=8.0.0,<8.0.12 | ya | https://symfony.com/cve-2026-45070 |
| PKSA-2n2k-66v2-bwg3 | CVE-2026-45067 | high | CVE-2026-45067: Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\… | >=2.0.0,<3.0.0 \| >=3.0.0,<4.0.0 \| >=4.0.0,<5.0.0 \| >=5.0.0,<5.1.0 \| >=5.1.0,<5.2.0 \| >=5.2.0,<5.3.0 \| >=5.3.0,<5.4.0 \| >=5.4.0,<5.4.52 \| >=6.0.0,<6.1.0 \| >=6.1.0,<6.2.0 \| >=6.2.0,<6.3.0 \| >=6.3.0,<6.4.0 \| >=6.4.0,<6.4.40 \| >=7.0.0,<7.1.0 \| >=7.1.0,<7.2.0 \| >=7.2.0,<7.3.0 \| >=7.3.0,<7.4.0 \| >=7.4.0,<7.4.12 \| >=8.0.0,<8.0.12 | ya | https://symfony.com/cve-2026-45067 |

### symfony/mailer — terpasang 7.4.8 → target 7.4.19

Advisori hanya pada SendmailTransport; SIMALAS memakai SMTP, jadi praktis tidak terdampak. Diusulkan karena satu rilis dengan symfony/mime dan dibutuhkan agar versi mime/mailer selaras.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-28rh-rzzn-djk4 | CVE-2026-45068 | medium | CVE-2026-45068: Argument Injection in SendmailTransport via Dash-Prefixed Recipient Addre… | >=2.0.0,<3.0.0 \| >=3.0.0,<4.0.0 \| >=4.0.0,<5.0.0 \| >=5.0.0,<5.1.0 \| >=5.1.0,<5.2.0 \| >=5.2.0,<5.3.0 \| >=5.3.0,<5.4.0 \| >=5.4.0,<5.4.52 \| >=6.0.0,<6.1.0 \| >=6.1.0,<6.2.0 \| >=6.2.0,<6.3.0 \| >=6.3.0,<6.4.0 \| >=6.4.0,<6.4.40 \| >=7.0.0,<7.1.0 \| >=7.1.0,<7.2.0 \| >=7.2.0,<7.3.0 \| >=7.3.0,<7.4.0 \| >=7.4.0,<7.4.12 \| >=8.0.0,<8.0.12 | ya | https://symfony.com/cve-2026-45068 |

### guzzlehttp/guzzle — terpasang 7.10.0 → target 7.15.5

Dipakai Http client Laravel (reCAPTCHA, FCM, AI) dan broadcaster Pusher/Reverb. Tujuan URL tetap, tanpa cookie jar/proxy, jadi paparan rendah; tetap diusulkan karena ada advisori high (host check) dan perbaikannya patch dalam major 7.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-gcrk-3vtt-1r14 | CVE-2026-69246 | high | Guzzle: Noncanonical host can bypass host-based checks | >=8.0.0,<8.0.1 \| <7.15.2 | ya | https://github.com/advisories/GHSA-v5mv-p594-2x33 |
| PKSA-cnw1-2ytm-cgr8 | CVE-2026-69245 | medium | Guzzle: Noncanonical cookie domain keeps subdomain scope | >=8.0.0,<8.0.1 \| <7.15.2 | ya | https://github.com/advisories/GHSA-f7vp-7xgx-4w4r |
| PKSA-fy2t-3c5f-827y | CVE-2026-67354 | medium | Guzzle: URI fragments disclosed in redirect Referer headers | <7.15.1 | ya | https://github.com/advisories/GHSA-h95v-h523-3mw8 |
| PKSA-qxvb-2bpp-dnk6 | CVE-2026-67355 | medium | Guzzle: Host-only cookie scope is not preserved | <7.15.1 | ya | https://github.com/advisories/GHSA-wm3w-8rrp-j577 |
| PKSA-bbs6-q5q9-f3t4 | CVE-2026-67353 | medium | Guzzle: Unbounded response cookies risk denial of service | <7.15.1 | ya | https://github.com/advisories/GHSA-f283-ghqc-fg79 |
| PKSA-bcdd-5xc7-gwfb | CVE-2026-59883 | medium | Guzzle: Cookie Disclosure and Injection via IP-Address Domains | <7.12.3 | ya | https://github.com/advisories/GHSA-g446-98w2-8p5w |
| PKSA-pwsk-hy21-4gby | CVE-2026-67339 | medium | Guzzle: Proxy-Authorization headers can be sent to origin servers | <7.14.2 | ya | https://github.com/advisories/GHSA-94pj-82f3-465w |
| PKSA-93qv-9n9h-6k6p | CVE-2026-55767 | medium | Dot-only cookie domains match all hosts | <7.12.1 | ya | https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx |
| PKSA-k22t-f949-t9g6 | CVE-2026-55568 | medium | Silent HTTPS proxy downgrade to cleartext | <7.12.1 | ya | https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3 |

### guzzlehttp/psr7 — terpasang 2.9.0 → target 2.13.1

Dipakai bersama Guzzle; URI berasal dari konfigurasi, bukan input pengguna, jadi paparan rendah. Wajib naik bersama Guzzle 7.15.x.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-vznr-tgp9-fd7d | CVE-2026-59882 | medium | guzzlehttp/psr7: Host Confusion via Weak URI Host Validation | <2.12.3 | ya | https://github.com/advisories/GHSA-c2w2-prh8-qm98 |
| PKSA-7qs6-zvnz-h66r | CVE-2026-55766 | medium | CRLF injection in HTTP start-line serialization | <2.12.1 | ya | https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432 |
| PKSA-gm5x-j3mz-71n9 | CVE-2026-49214 | medium | CRLF injection via URI host component | <2.10.2 | ya | https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw |
| PKSA-jj5t-2zs1-dcfm | CVE-2026-48998 | medium | Host confusion via authority reinterpretation | <2.10.2 | ya | https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph |

### guzzlehttp/promises — terpasang 2.3.0 → target 2.5.3

Tidak punya advisori; dibutuhkan Guzzle 7.15.x (requires ^2.5.1).

_Tidak ada advisori langsung._

### league/commonmark — terpasang 2.8.2 → target 2.10.3

Tidak ada pemakaian markdown di app/ (Str::markdown, mail markdown tidak ditemukan). Paparan rendah; diusulkan karena banyak advisori high (DoS) dan kenaikan minor.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-m2dq-1fhr-29b1 | - | medium | league/commonmark: DisallowedRawHtml bypassed when a disallowed tag name ends the raw-HTM… | >=1.3.0,<=2.10.1 | ya | https://github.com/advisories/GHSA-97jj-33gv-5xf9 |
| PKSA-m4t9-vsgq-8khn | - | high | league/commonmark: Quadratic-time denial of service in the GitHub Flavored Markdown Table… | >=2.0.0,<=2.10.1 | ya | https://github.com/advisories/GHSA-3q6v-r5mr-hxv8 |
| PKSA-zyf5-hrxv-hrd7 | - | high | league/commonmark: Denial of service via distinctly-named attributes in the Attributes ex… | >=1.5.0,<2.10.0 | ya | https://github.com/advisories/GHSA-8rr7-cvq3-gmfh |
| PKSA-nv44-1b4d-6gjg | - | high | league/commonmark: Denial of service in the SmartPunct and Attributes extensions | >=1.5.0,<2.9.1 | ya | https://github.com/advisories/GHSA-jjv6-8j6v-6j52 |
| PKSA-kr3s-894t-g5w2 | - | high | league/commonmark XSS: `on*` event-handler filter in `AttributesExtension` bypassed with … | >=2.7.0,<2.9.1 | ya | https://github.com/advisories/GHSA-f8fg-pg57-v4j8 |
| PKSA-9q1p-3s19-bp1q | - | high | league/commonmark: Denial of service via crafted code fences, reference links, and emphas… | >=0.6.0,<2.9.1 | ya | https://github.com/advisories/GHSA-j8pm-gj4c-rq4x |
| PKSA-5mzr-szzf-z6cn | - | medium | league/commonmark: Denial of service via deeply nested XML output | >=2.0.0,<2.9.0 | ya | https://github.com/advisories/GHSA-mj63-m3rc-8ppr |
| PKSA-cqd6-fg4n-nxpf | - | high | league/commonmark: Denial of service via colliding heading slugs | >=2.0.0,<2.9.0 | ya | https://github.com/advisories/GHSA-mh25-x5hq-wrqp |
| PKSA-1q6p-sqkj-8mmj | - | high | league/commonmark:  Denial of service via duplicate footnote definitions | >=1.5.0,<2.9.0 | ya | https://github.com/advisories/GHSA-jfm3-95jq-q3rf |
| PKSA-mc58-w91n-f5gv | - | high | league/commonmark: Denial of service via adjacent inline attribute blocks | >=1.5.0,<2.9.0 | ya | https://github.com/advisories/GHSA-g2gp-3wwq-f4ph |
| PKSA-t21r-vtr5-3mdz | CVE-2026-71488 | high | league/commonmark: Quadratic-time denial of service when parsing crafted Markdown | >=0.6.0,<2.9.0 | ya | https://github.com/advisories/GHSA-2q4p-g7hv-5rgv |
| PKSA-scnn-p8mm-jbft | CVE-2026-71478 | medium | league/commonmark: AttributesExtension href/src unsafe-link filter bypass via embedded co… | >=1.5.0,<=2.8.3 | ya | https://github.com/advisories/GHSA-29pj-957v-52mc |

## Advisori yang belum diusulkan (tidak diabaikan)

### symfony/http-kernel — terpasang 7.4.10 → target 7.4.12

Advisori pada atribut Symfony #[IsGranted]/#[IsSignatureValid]/#[IsCsrfTokenValid]; Laravel tidak memakai atribut ini. Tidak terdampak; ikut update Symfony berikutnya.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-dw7n-x7f5-zf63 | CVE-2026-45075 | high | CVE-2026-45075: HEAD Request Bypasses methods: ['GET'] Filter in #[IsGranted] / #[IsSigna… | >=7.4.0,<7.4.12 \| >=8.0.0,<8.0.12 | ya | https://symfony.com/cve-2026-45075 |

### symfony/http-foundation — terpasang 7.4.8 → target 7.4.13

IpUtils::PRIVATE_SUBNETS (IPv6 transisi). SIMALAS tidak memakai pengecekan subnet privat untuk keputusan keamanan. Rendah; tahap berikutnya.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-y6py-qpv1-h52p | CVE-2026-48736 | medium | CVE-2026-48736: IpUtils::PRIVATE_SUBNETS Omits IPv6 Transition Forms (6to4, NAT64, Teredo… | >=6.4.0,<6.4.41 \| >=7.0.0,<7.1.0 \| >=7.1.0,<7.2.0 \| >=7.2.0,<7.3.0 \| >=7.3.0,<7.4.0 \| >=7.4.0,<7.4.13 \| >=8.0.0,<8.0.13 | ya | https://symfony.com/cve-2026-48736 |

### symfony/routing — terpasang 7.4.9 → target 7.4.13

UrlGenerator Symfony; Laravel memakai UrlGenerator sendiri. Praktis tidak terdampak; tahap berikutnya.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-bf7t-jnpz-492k | CVE-2026-48784 | medium | CVE-2026-48784: UrlGenerator Dot-Segment Encoding Skips Every Other Chained `../` or `./`… | >=2.0.0,<3.0.0 \| >=3.0.0,<4.0.0 \| >=4.0.0,<5.0.0 \| >=5.0.0,<5.1.0 \| >=5.1.0,<5.2.0 \| >=5.2.0,<5.3.0 \| >=5.3.0,<5.4.0 \| >=5.4.0,<5.4.53 \| >=6.0.0,<6.1.0 \| >=6.1.0,<6.2.0 \| >=6.2.0,<6.3.0 \| >=6.3.0,<6.4.0 \| >=6.4.0,<6.4.41 \| >=7.0.0,<7.1.0 \| >=7.1.0,<7.2.0 \| >=7.2.0,<7.3.0 \| >=7.3.0,<7.4.0 \| >=7.4.0,<7.4.13 \| >=8.0.0,<8.0.13 | ya | https://symfony.com/cve-2026-48784 |
| PKSA-yc7t-91v9-99xs | CVE-2026-45065 | medium | CVE-2026-45065: UrlGenerator Route-Requirement Bypass via Unanchored Regex Alternation → … | >=2.0.0,<3.0.0 \| >=3.0.0,<4.0.0 \| >=4.0.0,<5.0.0 \| >=5.0.0,<5.1.0 \| >=5.1.0,<5.2.0 \| >=5.2.0,<5.3.0 \| >=5.3.0,<5.4.0 \| >=5.4.0,<5.4.52 \| >=6.0.0,<6.1.0 \| >=6.1.0,<6.2.0 \| >=6.2.0,<6.3.0 \| >=6.3.0,<6.4.0 \| >=6.4.0,<6.4.40 \| >=7.0.0,<7.1.0 \| >=7.1.0,<7.2.0 \| >=7.2.0,<7.3.0 \| >=7.3.0,<7.4.0 \| >=7.4.0,<7.4.12 \| >=8.0.0,<8.0.12 | ya | https://symfony.com/cve-2026-45065 |

### league/flysystem — terpasang 3.33.0 → target 3.36.0

Normalizer path; path upload dibuat aplikasi, bukan dari input mentah. Low; tahap berikutnya.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-w9tt-7782-78jx | CVE-2026-102601 | low | Flysystem: WhitespacePathNormalizer's control-character (CorruptedPathDetected) check is … | <=3.35.2 | ya | https://github.com/advisories/GHSA-cxf4-7mrp-vvpr |

### symfony/polyfill-intl-idn — terpasang 1.37.0 → target 1.38.1

Polyfill hanya aktif bila ext-intl tidak ada; ext-intl aktif di PHP 8.3 web dan CLI. Low; tahap berikutnya.

| Advisory ID | CVE | Severity | Judul | Rentang terdampak | Target aman | Link |
|---|---|---|---|---|---|---|
| PKSA-dwsq-ppd2-mb1x | CVE-2026-46644 | low | CVE-2026-46644: symfony/polyfill-intl-idn accepts xn-- labels whose Punycode payload deco… | >=1.17.1,<1.38.1 | ya | https://symfony.com/cve-2026-46644 |

Total advisori: 38; paket audit tanpa rencana: tidak ada; target yang masih terdampak: 0.
