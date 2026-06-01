<?php

return [
    'plugin' => [
        'name'        => 'Gutscheine',
        'description' => 'Gutschein-Verkauf (Mollie), digitale (PDF/QR) und physische Gutscheine, Einlösung mit Restguthaben.',
        'menu_label'  => 'Gutscheine',
    ],
    'vouchers' => [
        'menu_label' => 'Gutscheine',
        'label'      => 'Gutschein',
    ],
    'orders' => [
        'menu_label'    => 'Bestellungen',
        'label'         => 'Bestellung',
        'counter_label' => 'Physische Gutscheine zu versenden',
    ],
    'redemptions' => [
        'menu_label' => 'Einlösungen',
        'label'      => 'Einlösung',
    ],
    'permissions' => [
        'manage_vouchers' => 'Gutscheine & Einlösungen verwalten',
        'manage_orders'   => 'Bestellungen verwalten',
        'redeem_vouchers' => 'Gutscheine einlösen (Kasse)',
    ],
    'settings' => [
        'label'       => 'Gutschein-Einstellungen',
        'description' => 'Nummerierung, Servicepauschale, MwSt-Modell, Mollie, Absender, PDF.',
    ],
];
