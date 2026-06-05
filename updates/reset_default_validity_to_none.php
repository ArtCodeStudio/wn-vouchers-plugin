<?php namespace JumpLink\Vouchers\Updates;

use Winter\Storm\Database\Updates\Migration;
use JumpLink\Vouchers\Models\Settings;

/**
 * Default to no printed expiry. Earlier installs seeded a 3-year default; flip it
 * to 0 (no expiry) so newly issued vouchers carry no expiry date by default. Only
 * the old default (3) is reset — a deliberately customised value is left alone.
 * Already-issued vouchers keep their stored valid_until; this only affects new ones.
 */
class ResetDefaultValidityToNone extends Migration
{
    public function up()
    {
        if ((int) Settings::get('default_validity_years', 0) === 3) {
            Settings::set('default_validity_years', 0);
        }
    }

    public function down()
    {
        // No-op: we do not restore a printed expiry on rollback.
    }
}
