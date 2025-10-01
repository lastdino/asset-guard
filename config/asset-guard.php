<?php

// config for Lastdino/AssetGuard
return [


    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the URL prefix, middleware stack, and guards for all approval-flow
    | routes. This gives you control over route access and grouping.
    |
    */
    'routes' => [
        'prefix' => 'asset-guard',
        'middleware' => ['web'],
        'guards' => ['web'],
    ],

];
