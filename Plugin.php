<?php namespace JumpLink\Vouchers;

use Backend;
use System\Classes\PluginBase;
use JumpLink\Vouchers\Models\VoucherOrder;

/**
 * JumpLink Vouchers Plugin
 *
 * Gutschein-System für Mahlzeit am Meer: Online-Kauf mit Mollie-Zahlung,
 * digitale Gutscheine (PDF mit QR) und physische Karten, Einlösung mit
 * Restguthaben-Ledger. Bewusst eigenständig neben JumpLink.Events (das
 * zahlungs-agnostisch und von mehreren Themes geteilt bleibt).
 *
 * @see docs/engagements/mahlzeit-am-meer-gutscheinsystem/voucher-plugin-spec.md
 */
class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'jumplink.vouchers::lang.plugin.name',
            'description' => 'jumplink.vouchers::lang.plugin.description',
            'author'      => 'JumpLink – Art+Code Studio',
            'icon'        => 'icon-gift',
            'homepage'    => 'https://artandcode.studio',
        ];
    }

    public function registerComponents()
    {
        return [
            \JumpLink\Vouchers\Components\VoucherPurchase::class => 'voucherPurchase',
            \JumpLink\Vouchers\Components\VoucherReturn::class   => 'voucherReturn',
            \JumpLink\Vouchers\Components\VoucherPos::class      => 'voucherPos',
        ];
    }

    public function registerNavigation()
    {
        return [
            'vouchers' => [
                'label'       => 'jumplink.vouchers::lang.plugin.menu_label',
                'url'         => Backend::url('jumplink/vouchers/vouchers'),
                'icon'        => 'icon-gift',
                'permissions' => ['jumplink.vouchers.*'],
                'order'       => 510,
                'sideMenu' => [
                    'vouchers' => [
                        'label'       => 'jumplink.vouchers::lang.vouchers.menu_label',
                        'icon'        => 'icon-ticket',
                        'url'         => Backend::url('jumplink/vouchers/vouchers'),
                        'permissions' => ['jumplink.vouchers.manage_vouchers'],
                    ],
                    'orders' => [
                        'label'        => 'jumplink.vouchers::lang.orders.menu_label',
                        'icon'         => 'icon-shopping-cart',
                        'url'          => Backend::url('jumplink/vouchers/orders'),
                        'permissions'  => ['jumplink.vouchers.manage_orders'],
                        'counter'      => [VoucherOrder::class, 'openFulfillmentCount'],
                        'counterLabel' => 'jumplink.vouchers::lang.orders.counter_label',
                    ],
                    'redemptions' => [
                        'label'       => 'jumplink.vouchers::lang.redemptions.menu_label',
                        'icon'        => 'icon-minus-circle',
                        'url'         => Backend::url('jumplink/vouchers/redemptions'),
                        'permissions' => ['jumplink.vouchers.manage_vouchers'],
                    ],
                ],
            ],
        ];
    }

    public function registerPermissions()
    {
        return [
            'jumplink.vouchers.manage_vouchers' => [
                'tab'   => 'jumplink.vouchers::lang.plugin.menu_label',
                'label' => 'jumplink.vouchers::lang.permissions.manage_vouchers',
            ],
            'jumplink.vouchers.manage_orders' => [
                'tab'   => 'jumplink.vouchers::lang.plugin.menu_label',
                'label' => 'jumplink.vouchers::lang.permissions.manage_orders',
            ],
            'jumplink.vouchers.redeem_vouchers' => [
                'tab'   => 'jumplink.vouchers::lang.plugin.menu_label',
                'label' => 'jumplink.vouchers::lang.permissions.redeem_vouchers',
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'jumplink.vouchers::lang.settings.label',
                'description' => 'jumplink.vouchers::lang.settings.description',
                'category'    => 'jumplink.vouchers::lang.plugin.menu_label',
                'icon'        => 'icon-gift',
                'class'       => \JumpLink\Vouchers\Models\Settings::class,
                'permissions' => ['jumplink.vouchers.manage_vouchers'],
                'order'       => 510,
            ],
        ];
    }

    public function registerMailTemplates()
    {
        return [
            'jumplink.vouchers::mail.purchase_confirmation',
            'jumplink.vouchers::mail.purchase_notification',
            'jumplink.vouchers::mail.shipping_notification',
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand(
            'jumplink.vouchers.verify',
            \JumpLink\Vouchers\Console\VerifyBalances::class
        );
    }
}
