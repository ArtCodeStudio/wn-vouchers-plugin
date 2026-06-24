<?php namespace JumpLink\Vouchers\Controllers;

use Flash;
use Response;
use BackendAuth;
use BackendMenu;
use Carbon\Carbon;
use Backend\Classes\Controller;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Classes\NotificationService;
use JumpLink\Vouchers\Classes\IssuanceService;
use JumpLink\Vouchers\Classes\DatevExportService;

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

    /**
     * Confirm an incoming bank transfer (Vorkasse): issue the voucher via the same
     * path as the Mollie webhook, then email the buyer the confirmation + voucher.
     * Idempotent — a second click re-uses the existing voucher and sends no
     * duplicate mail (IssuanceService reports created=false).
     */
    public function onMarkPaid()
    {
        $order = VoucherOrder::find((int) post('order_id'));
        if (!$order) {
            throw new \ApplicationException(trans('jumplink.vouchers::lang.error.order_not_found'));
        }
        if (!$order->isBankTransfer()) {
            throw new \ApplicationException(trans('jumplink.vouchers::lang.error.not_bank_transfer'));
        }

        $result = IssuanceService::issueForOrder($order);
        if ($result['created']) {
            NotificationService::sendPurchaseMails($order->fresh(), $result['voucher']);
        }

        Flash::success(trans('jumplink.vouchers::lang.flash.marked_paid'));

        return ['#voucherPaymentPanel' => $this->makePartial('payment', ['formModel' => $order->fresh()])];
    }

    /**
     * Stream a DATEV-Format booking batch (EXTF) of the year's voucher sales as a
     * CSV download. Year defaults to the current one; ?year=YYYY selects another.
     * Operator-agnostic — the account numbers come from Settings → Beleg → DATEV.
     */
    public function datev()
    {
        $year = (int) (input('year') ?: Carbon::now()->format('Y'));
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to   = Carbon::create($year, 12, 31)->endOfDay();

        $csv = DatevExportService::export(DatevExportService::bookableOrders($from, $to), $from, $to);

        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv; charset=Windows-1252',
            'Content-Disposition' => 'attachment; filename="datev-gutscheine-' . $year . '.csv"',
        ]);
    }

    /**
     * GDPR bulk erasure from the orders list: anonymise the checked orders'
     * personal data, keeping the fiscal record. Idempotent per order.
     */
    public function index_onAnonymizeSelected()
    {
        $ids = array_filter((array) post('checked'));
        if (empty($ids)) {
            Flash::error(trans('jumplink.vouchers::lang.orders.anonymize_none'));
            return;
        }

        $count = 0;
        foreach (VoucherOrder::whereIn('id', $ids)->get() as $order) {
            if ($order->anonymize()) {
                $count++;
            }
        }

        Flash::success(trans('jumplink.vouchers::lang.flash.anonymized', ['count' => $count]));
        return $this->listRefresh();
    }
}
