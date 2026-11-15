<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class LandingPageData
{
    /**
     * @return list<array{slug: string, nama: string, inisial: string, role: string, tags: list<string>, desc: string, photo: ?string}>
     */
    public static function teamMembers(): array
    {
        $members = [
            ['slug' => 'rifki', 'nama' => 'Rifki Amalya Fatekha, S.ST., M.Tr.T.', 'inisial' => 'RA', 'role' => 'Project Manager', 'tags' => ['Project Manager'], 'desc' => 'Memimpin perencanaan dan koordinasi seluruh tim pengembangan.'],
            ['slug' => 'yuyun', 'nama' => 'Yuyun Rahmawati', 'inisial' => 'YR', 'role' => 'Team Leader · Electrical Engineering', 'tags' => ['Electrical', 'Team Leader'], 'desc' => 'Memimpin tim teknis dan integrasi perangkat keras mesin IoT.'],
            [
                'slug' => 'aldo',
                'nama' => 'Rayendra Aldo Putra',
                'inisial' => 'RA',
                'role' => 'Full-stack & IoT',
                'tags' => ['Frontend', 'Backend', 'Mobile Application', 'Communication', 'Mechanical'],
                'desc' => 'Mengembangkan website, API Laravel, aplikasi Flutter, integrasi ESP32, dan desain mekanik — dibangun secara mandiri dengan bantuan AI.',
            ],
            [
                'slug' => 'viona',
                'nama' => 'Viona Syafitri',
                'inisial' => 'VS',
                'role' => 'Communication & Mechanical',
                'tags' => ['Communication', 'Mechanical'],
                'desc' => 'Bertanggung jawab pada komunikasi tim, dokumentasi, dan aspek mekanik perangkat.',
            ],
            ['slug' => 'tini', 'nama' => 'Tini Agustina', 'inisial' => 'TA', 'role' => 'Computer Vision', 'tags' => ['Computer Vision'], 'desc' => 'Mengeksplorasi modul computer vision untuk pengembangan lanjutan.'],
            [
                'slug' => 'allyssa',
                'nama' => 'Allyssa Naura S.',
                'inisial' => 'AN',
                'role' => 'Frontend & Vision',
                'tags' => ['Frontend', 'Communication', 'Vision'],
                'desc' => 'Merancang antarmuka web, komunikasi visual, dan eksplorasi modul vision untuk pengembangan lanjutan.',
            ],
        ];

        return array_map(function (array $m) {
            $m['photo'] = self::teamPhotoUrl($m['slug']);

            return $m;
        }, $members);
    }

    public static function teamPhotoUrl(string $slug): ?string
    {
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
            $relative = "images/team/{$slug}.{$ext}";
            if (File::exists(public_path($relative))) {
                return asset($relative);
            }
        }

        return null;
    }
}
