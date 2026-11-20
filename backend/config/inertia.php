<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    */

    'ssr' => [
        'enabled' => true,
        'url' => 'http://127.0.0.1:13714',
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    'pages' => [

        'paths' => [
            base_path('../frontend/src/modules/storefront/pages'),
            base_path('../frontend/src/modules/catalog/pages'),
            base_path('../frontend/src/modules/cart/pages'),
            base_path('../frontend/src/modules/checkout/pages'),
            base_path('../frontend/src/modules/account/pages'),
            base_path('../frontend/src/modules/admin/pages'),
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

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
