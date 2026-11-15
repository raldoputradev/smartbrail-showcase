<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\Carbon;

/**
 * Label fitur di sidebar — "BARU" (fitur baru rilis, tampil sampai akhir
 * semester berjalan) dan "SOON" (fitur sedang dikembangkan).
 *
 * Admin mengatur lewat Pengaturan → Tampilan → Label Fitur:
 * master on/off, tanggal kedaluwarsa label BARU, dan status per fitur
 * (Baru / Soon / Mati). Tersimpan di system_settings key 'feature_badges'.
 */
class FeatureBadge
{
    public const SETTING_KEY = 'feature_badges';

    /** Registry fitur: key => label UI + status default (new|soon|off). */
    public const FEATURES = [
        'asisten-ai'        => ['label' => 'Asisten AI', 'default' => 'new'],
        'pelatihan-ai'      => ['label' => 'Pelatihan AI', 'default' => 'new'],
        'program-mesin'     => ['label' => 'Program Mesin', 'default' => 'new'],
        'pbl-tim'           => ['label' => 'Tim / Proyek PBL', 'default' => 'new'],
        'pengajuan-ruangan' => ['label' => 'Pengajuan Ruangan', 'default' => 'new'],
        'penilaian-pbl'     => ['label' => 'Penilaian PBL / Tim', 'default' => 'soon'],
        'grup-chat-tim'     => ['label' => 'Grup Chat Tim', 'default' => 'soon'],
        'manajemen-tim'     => ['label' => 'Manajemen Tim (Dosen)', 'default' => 'soon'],
        'riwayat-chat'      => ['label' => 'Riwayat Chat Asisten', 'default' => 'soon'],
    ];

    /** Akhir semester berjalan — ganjil → 31 Jan berikutnya, genap → 31 Jul. */
    public static function defaultUntil(): string
    {
        $now = now();

        return $now->month >= 8
            ? $now->copy()->addYear()->month(1)->endOfMonth()->toDateString()
            : $now->copy()->month(7)->endOfMonth()->toDateString();
    }

    public static function config(): array
    {
        $saved = (array) SystemSetting::valueFor(self::SETTING_KEY, []);

        return [
            'enabled' => (bool) ($saved['enabled'] ?? true),
            'until' => (string) ($saved['until'] ?? self::defaultUntil()),
            'items' => (array) ($saved['items'] ?? []),
        ];
    }

    /** Status efektif fitur: 'new' | 'soon' | 'off'. */
    public static function state(string $key): string
    {
        $cfg = self::config();
        if (! $cfg['enabled']) {
            return 'off';
        }

        $mode = (string) ($cfg['items'][$key] ?? (self::FEATURES[$key]['default'] ?? 'off'));
        if ($mode === 'new' && now()->gt(Carbon::parse($cfg['until'])->endOfDay())) {
            return 'off'; // label BARU kedaluwarsa otomatis di akhir semester
        }

        return in_array($mode, ['new', 'soon'], true) ? $mode : 'off';
    }

    public static function tag(string $key): ?string
    {
        return match (self::state($key)) {
            'new' => 'Baru',
            'soon' => 'Soon',
            default => null,
        };
    }

    /** Atribut untuk menu React (SidebarLink.tag / .soon). */
    public static function attrs(string $key): array
    {
        return match (self::state($key)) {
            'new' => ['tag' => 'Baru'],
            'soon' => ['tag' => 'Soon', 'soon' => true],
            default => [],
        };
    }

    /** Peta key => state — dishare ke Inertia untuk sidebar React mahasiswa. */
    public static function states(): array
    {
        $out = [];
        foreach (self::FEATURES as $key => $_) {
            $out[$key] = self::state($key);
        }

        return $out;
    }

    /** Daftar fitur untuk halaman pengaturan: key, label, state efektif. */
    public static function adminList(): array
    {
        $cfg = self::config();
        $out = [];
        foreach (self::FEATURES as $key => $meta) {
            $out[] = [
                'key' => $key,
                'label' => $meta['label'],
                'state' => self::state($key),
                'saved' => (string) ($cfg['items'][$key] ?? ''),
            ];
        }

        return $out;
    }

    public static function save(bool $enabled, string $until, array $items): void
    {
        $clean = [];
        foreach ($items as $key => $mode) {
            if (isset(self::FEATURES[$key]) && in_array($mode, ['new', 'soon', 'off'], true)) {
                $clean[$key] = $mode;
            }
        }

        SystemSetting::setValue(self::SETTING_KEY, [
            'enabled' => $enabled,
            'until' => $until !== '' ? $until : self::defaultUntil(),
            'items' => $clean,
        ]);
    }
}
