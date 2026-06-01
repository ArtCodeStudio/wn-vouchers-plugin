<?php namespace JumpLink\Vouchers\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Orders Backend Controller – purchase/payment records and (M2) fulfillment.
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
}
