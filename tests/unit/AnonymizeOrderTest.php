<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\VoucherOrder;

/**
 * GDPR erasure (Art. 17): VoucherOrder::anonymize() removes the buyer's personal
 * data while keeping the fiscal record, and is idempotent.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class AnonymizeOrderTest extends PluginTestCase
{
    protected function order(): VoucherOrder
    {
        return VoucherOrder::create([
            'delivery_type'    => 'physical',
            'face_value_cents' => 5000,
            'service_fee_cents' => 250,
            'total_cents'      => 5250,
            'currency'         => 'EUR',
            'vat_mode'         => 'multi_purpose',
            'status'           => 'issued',
            'firstname'        => 'Erika',
            'lastname'         => 'Musterfrau',
            'email'            => 'erika@example.test',
            'phone'            => '+49 123',
            'street'           => 'Hauptstr. 1',
            'zip'              => '27472',
            'city'             => 'Cuxhaven',
            'recipient_name'   => 'Max',
            'message'          => 'Alles Gute!',
            'ip'               => '203.0.113.7',
            'provider'         => 'banktransfer',
            'payment_id'       => 'tr_demo123',
            'paid_at'          => now(),
        ]);
    }

    public function testAnonymizeRemovesPersonalDataButKeepsFiscalFields()
    {
        $order = $this->order();

        $this->assertTrue($order->anonymize());
        $fresh = $order->fresh();

        // Personal data gone.
        foreach (VoucherOrder::PERSONAL_FIELDS as $field) {
            $this->assertNull($fresh->$field, "personal field {$field} should be null");
        }
        $this->assertNotNull($fresh->anonymized_at);
        $this->assertTrue($fresh->isAnonymized());

        // Fiscal record kept.
        $this->assertSame(5250, (int) $fresh->total_cents);
        $this->assertSame(5000, (int) $fresh->face_value_cents);
        $this->assertSame('tr_demo123', $fresh->payment_id);
        $this->assertSame('issued', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame('GS-' . $order->id, $fresh->receipt_number);
    }

    public function testAnonymizeIsIdempotent()
    {
        $order = $this->order();

        $this->assertTrue($order->anonymize());
        $this->assertFalse($order->fresh()->anonymize(), 'a second anonymize must be a no-op');
    }

    public function testChunkedSweepAnonymizesEveryEligibleRowWhileMutating()
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->order()->id;
        }
        // Backdate past the retention window (bypass the model's timestamp handling).
        VoucherOrder::whereIn('id', $ids)->update(['created_at' => now()->subDays(10)]);

        // Mirror AnonymizeOrders::handle() — a small chunk forces several passes
        // while anonymize() mutates anonymized_at (the filtered column). chunkById
        // must stay stable and skip no eligible row (an each()/offset sweep would).
        $cutoff = now()->subDays(1);
        $count = 0;
        VoucherOrder::whereNull('anonymized_at')->where('created_at', '<', $cutoff)
            ->chunkById(2, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    if ($order->anonymize()) {
                        $count++;
                    }
                }
            });

        $this->assertSame(5, $count);
        foreach ($ids as $id) {
            $this->assertNotNull(VoucherOrder::find($id)->anonymized_at, "order {$id} should be anonymized");
        }
    }
}
