<?php namespace JumpLink\Vouchers\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Vouchers Backend Controller – manage issued vouchers, balances and status.
 */
class Vouchers extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['jumplink.vouchers.manage_vouchers'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('JumpLink.Vouchers', 'vouchers', 'vouchers');
    }
}
