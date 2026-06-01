<?php namespace JumpLink\Vouchers\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Redemptions Backend Controller – read-only audit of the redemption ledger.
 */
class Redemptions extends Controller
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
        BackendMenu::setContext('JumpLink.Vouchers', 'vouchers', 'redemptions');
    }
}
