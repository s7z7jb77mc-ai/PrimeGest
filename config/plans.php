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
    ],
];
