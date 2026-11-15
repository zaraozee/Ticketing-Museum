<?php

/*
|--------------------------------------------------------------------------
| Inertia
|--------------------------------------------------------------------------
|
| Hanya kunci yang perlu disesuaikan proyek ini. Sisa nilai (SSR, DevTools,
| Big Integers) tetap memakai bawaan paket lewat `mergeConfigFrom`, sehingga
| pembaruan paket tidak perlu disalin ulang ke sini.
|
| Catatan: `mergeConfigFrom` menggabungkan pada tingkat kunci teratas, jadi
| seluruh isi `pages` harus ditulis lengkap - bukan hanya `paths`.
|
*/

return [

    'pages' => [

        'ensure_pages_exist' => false,

        /*
         * Folder halaman proyek ini bernama `resources/js/Pages` (huruf besar).
         * Bawaan paket adalah `js/pages` (huruf kecil); di Windows keduanya
         * menunjuk folder yang sama, tetapi di Linux - termasuk runner CI dan
         * server produksi - nama bersifat case-sensitive. Dengan nilai bawaan,
         * seluruh `assertInertia(...->component(...))` gagal dengan pesan
         * "Inertia page component file [...] does not exist".
         */
        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
