<?php

return [
    'map' => [
        'lat' => (float) env('LANDING_MAP_LAT', 1.118671),
        'lng' => (float) env('LANDING_MAP_LNG', 104.048450),
        'zoom' => (int) env('LANDING_MAP_ZOOM', 17),
        'label' => env('LANDING_MAP_LABEL', 'Politeknik Negeri Batam — Lab BRAIL'),
        'url' => env('LANDING_MAP_URL', 'https://goo.gl/maps/E111V3dZtZExxyrZ8'),
    ],

    'social' => [
        'whatsapp' => env('LANDING_SOCIAL_WHATSAPP', 'https://wa.me/6281370000000'),
        'instagram' => env('LANDING_SOCIAL_INSTAGRAM', 'https://instagram.com/polibatam'),
        'telegram' => env('LANDING_SOCIAL_TELEGRAM', 'https://t.me/polibatam'),
        'discord' => env('LANDING_SOCIAL_DISCORD', 'https://discord.gg/'),
    ],
];
