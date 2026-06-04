<?php namespace JumpLink\Vouchers\Controllers;

use Flash;
use BackendAuth;
use BackendMenu;
use Backend\Classes\Controller;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Classes\NotificationService;

/**
 * Orders Backend Controller – purchase/payment records and physical fulfillment.
 */
class Orders extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['jumplink.vouchers.manage_orders'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('JumpLink.Vouchers', 'vouchers', 'orders');
    }

    /**
     * Mark a physical order as posted: stamps the shipping date and emails the
     * buyer the "on its way" notification. Idempotent (a second click does
     * nothing and sends no duplicate mail).
     */
    public function onMarkShipped()
    {
        $order = VoucherOrder::find((int) post('order_id'));
        if (!$order) {
            throw new \ApplicationException(trans('jumplink.vouchers::lang.error.order_not_found'));
        }

        $user = BackendAuth::getUser();
        if (!$order->markShipped($user ? $user->id : null)) {
            throw new \ApplicationException(trans('jumplink.vouchers::lang.error.cannot_mark_shipped'));
        }

        NotificationService::sendShippingMail($order);
        Flash::success(trans('jumplink.vouchers::lang.flash.marked_shipped'));

        return ['#voucherShippingPanel' => $this->makePartial('shipping', ['formModel' => $order->fresh()])];
    }
}
