<?php

return [
    'plugin' => [
        'name'        => 'Vouchers',
        'description' => 'Voucher sales (Mollie), digital (PDF/QR) and physical vouchers, redemption with running balance.',
        'menu_label'  => 'Vouchers',
    ],
    'vouchers' => [
        'menu_label' => 'Vouchers',
        'label'      => 'Voucher',
    ],
    'orders' => [
        'menu_label'    => 'Orders',
        'label'         => 'Order',
        'counter_label' => 'Physical vouchers to post',
    ],
    'redemptions' => [
        'menu_label' => 'Redemptions',
        'label'      => 'Redemption',
    ],
    'permissions' => [
        'manage_vouchers' => 'Manage vouchers & redemptions',
        'manage_orders'   => 'Manage orders',
        'redeem_vouchers' => 'Redeem vouchers (till)',
    ],
    'settings' => [
        'label'       => 'Voucher settings',
        'description' => 'Numbering, service fee, VAT mode, Mollie, sender, PDF.',
    ],
];
