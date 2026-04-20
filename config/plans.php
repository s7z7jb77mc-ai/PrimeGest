<?php

return [
    'free' => [
        'users'            => 3,
        'produits'         => 50,
        'clients'          => 20,
        'fournisseurs'     => 20,
        'dette_tracking'   => false,
        'reductions'       => false,
        'storage_mb'       => 250,
        'succursales'      => false,
        'exports'          => false,
        'rapports'         => 'basic',
        'offline_sync'     => true,   // tout le monde sync
        'sync_quota'       => 500,    // max 500 records cloud
    ],

    'premium' => [
        'users'            => -1,
        'produits'         => -1,
        'clients'          => -1,
        'fournisseurs'     => -1,
        'dette_tracking'   => true,
        'reductions'       => true,
        'storage_mb'       => -1,
        'succursales'      => false,
        'exports'          => true,
        'rapports'         => 'advanced',
        'offline_sync'     => true,
        'sync_quota'       => -1,     // illimité
    ],

    'pro' => [
        'users'            => -1,
        'produits'         => -1,
        'clients'          => -1,
        'fournisseurs'     => -1,
        'dette_tracking'   => true,
        'reductions'       => true,
        'storage_mb'       => -1,
        'succursales'      => true,
        'exports'          => true,
        'rapports'         => 'advanced',
        'offline_sync'     => true,
        'sync_quota'       => -1,
    ],
];
