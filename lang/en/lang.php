<?php

return [
    'plugin' => [
        'name'        => 'Vouchers',
        'description' => 'Voucher sales (Mollie), digital (image/QR) and physical vouchers, redemption with running balance.',
        'menu_label'  => 'Vouchers',
    ],
    'vouchers' => [
        'menu_label'   => 'Vouchers',
        'label'        => 'Voucher',
        'create_title' => 'Create voucher',
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
        'description' => 'Numbering, service fee, VAT mode, Mollie, sender, voucher design.',
    ],

    // Status / option labels (dropdowns + list columns)
    'voucher_status' => [
        'active'   => 'Active',
        'redeemed' => 'Redeemed (0 €)',
        'void'     => 'Voided',
        'expired'  => 'Expired',
    ],
    'order_status' => [
        'pending'   => 'Pending (awaiting payment)',
        'paid'      => 'Paid',
        'issued'    => 'Issued',
        'failed'    => 'Failed',
        'cancelled' => 'Cancelled',
        'expired'   => 'Expired',
        'refunded'  => 'Refunded',
    ],
    'type' => [
        'digital'  => 'Digital (image/QR)',
        'physical' => 'Physical (card)',
    ],
    'delivery' => [
        'digital'  => 'Digital (image/QR)',
        'physical' => 'Physical (post)',
    ],
    'number_source' => [
        'auto'   => 'Automatic (sequential)',
        'manual' => 'Manual (binder number)',
    ],
    'payment_status' => [
        'paid'   => 'Paid',
        'unpaid' => 'Open / unpaid',
    ],
    'payment_method' => [
        'cash'    => 'Cash',
        'card'    => 'Card / EC',
        'invoice' => 'Invoice',
        'online'  => 'Online (Mollie)',
        'other'   => 'Other',
    ],
    'vat_mode_option' => [
        'multi_purpose'  => 'Multi-purpose voucher (VAT on redemption)',
        'single_purpose' => 'Single-purpose voucher (VAT on sale)',
    ],
    'redemption_kind' => [
        'redeem'   => 'Redemption',
        'reversal' => 'Reversal',
        'adjust'   => 'Correction',
    ],
    // Mollie payment status of the order (set by the provider; "canceled" with one
    // l — intentionally separate from order_status.cancelled).
    'order_payment_status' => [
        'open'       => 'Open',
        'pending'    => 'Pending',
        'authorized' => 'Authorized',
        'paid'       => 'Paid',
        'failed'     => 'Failed',
        'canceled'   => 'Canceled',
        'expired'    => 'Expired',
    ],
    'redemption_source' => [
        'pos'     => 'Till',
        'backend' => 'Backend',
        'api'     => 'API',
    ],

    // Form tabs
    'tab' => [
        'voucher'    => 'Voucher',
        'redemption' => 'Redemption',
        'order'      => 'Order',
        'contact'    => 'Contact',
        'payment'    => 'Payment',
        'shipping'   => 'Shipping',
        'image'      => 'Voucher image',
    ],

    // Backend: voucher image preview (form)
    'voucher_image' => [
        'open'       => 'Open in new tab',
        'download'   => 'Download image',
        'alt'        => 'Voucher preview',
        'hint'       => 'Generated live from the voucher data. Save first after any changes.',
        'save_first' => 'Please save the voucher first — the preview then appears here.',
    ],

    // Form fields + list columns (labels + comments)
    'field' => [
        'code'                    => 'Code',
        'code_comment'            => 'Generated automatically from the number.',
        'number'                  => 'Number',
        'number_short'            => 'No.',
        'number_source'           => 'Number source',
        'number_source_comment'   => 'Automatic = the next free number is allocated. Manual = enter the number from the binder.',
        'number_comment'          => 'Leave empty for “Automatic”; enter it for “Manual”.',
        'type'                    => 'Type',
        'value'                   => 'Value',
        'value_euro'              => 'Value (€)',
        'value_euro_comment'      => 'e.g. 50.00',
        'balance'                 => 'Balance',
        'balance_comment'         => 'Computed from the redemption ledger.',
        'status'                  => 'Status',
        'payment'                 => 'Payment',
        'payment_section_comment' => 'For manual creation: whether and how the customer paid (e.g. already at the regular till).',
        'payment_status'          => 'Payment status',
        'payment_method'          => 'Payment method',
        'payment_method_empty'    => '— please choose —',
        'recipient'               => 'Recipient',
        'recipient_comment'       => 'Suggestions appear once a name has been used before.',
        'address_section'         => 'Shipping address (optional)',
        'address_section_comment' => 'Only if a physical card is to be posted (e.g. a phone order). Leave empty if the card is taken along.',
        'street'                  => 'Street & no.',
        'zip'                     => 'Postcode',
        'city'                    => 'City',
        'valid_until'             => 'Valid until',
        'valid_until_optional'    => 'Valid until (optional)',
        'valid_until_comment'     => 'Empty = default from the settings (no expiry by default). A different date can be set here.',
        'issued_at'               => 'Issued at',
        'created'                 => 'Created',
        'date'                    => 'Date',
        'delivery_type'           => 'Delivery method',
        'delivery'                => 'Delivery',
        'face_value_cents'        => 'Voucher value (cents)',
        'service_fee_cents'       => 'Service fee (cents)',
        'total_cents'             => 'Total (cents)',
        'total'                   => 'Total',
        'vat_mode'                => 'VAT model',
        'message'                 => 'Message',
        'firstname'               => 'First name',
        'lastname'                => 'Last name',
        'email'                   => 'Email',
        'phone'                   => 'Phone',
        'provider'                => 'Provider',
        'payment_id'              => 'Payment ID',
        'paid_at'                 => 'Paid at',
    ],

    // Settings → tabs
    'setting_tab' => [
        'vouchers'     => 'Vouchers',
        'vat'          => 'VAT',
        'payment'      => 'Payment',
        'notification' => 'Notification',
        'design'       => 'Voucher design',
    ],

    // Settings → fields (labels, comments, repeater prompts)
    'setting' => [
        'start_number'           => 'Start number (automatic)',
        'start_number_comment'   => 'Digital vouchers are numbered automatically from this number on. It must lie above the handwritten binder range.',
        'validity_years'         => 'Validity (years)',
        'validity_years_comment' => 'Years until the printed expiry (rounded to year-end, matches the 3-year limitation §§195/199 BGB). 0 = no expiry date (default).',
        'min_value'              => 'Minimum amount (cents)',
        'max_value'              => 'Maximum amount (cents)',
        'service_fee'            => 'Shipping service fee (cents)',
        'service_fee_comment'    => 'Charged only for physical shipping (€2.50).',
        'denominations'          => 'Suggested amounts',
        'denominations_prompt'   => 'Add amount',
        'amount_cents'           => 'Amount (cents)',
        'vat_mode_comment'       => 'Multi-purpose = recommended for a restaurant with 7%/19% redemptions. Confirm with your tax advisor.',
        'vat_rate'               => 'VAT rate on sale (%)',
        'vat_rate_comment'       => 'Relevant only for single-purpose vouchers.',
        'vat_rates'              => 'Selectable rates on redemption (%)',
        'vat_rates_prompt'       => 'Add rate',
        'rate'                   => 'Rate (%)',
        'mollie_mode'            => 'Mollie mode',
        'mollie_mode_comment'    => 'The API key is read from the .env (MOLLIE_API_KEY), never stored here.',
        'pos_page_url'           => 'Till page (URL path)',
        'pos_page_url_comment'   => 'CMS page with the “Voucher till” component. The QR code scan redirects here.',
        'notify_name'            => 'Default recipient name',
        'notify_email'           => 'Default recipient email',
        'notify_email_comment'   => 'Recipient for order/shipping notifications.',
        'send_customer_copy'     => 'Send confirmation to buyer',
        'sender_name'            => 'Sender name',
        'sender_email'           => 'Sender email',
        'sender_email_comment'   => 'Leave empty to use the global mail sender.',
        'brand_name'             => 'Brand name',
        'brand_name_comment'     => 'Appears on the voucher and in the emails.',
        'accent_color'           => 'Accent colour',
        'logo'                   => 'Logo (optional)',
        'logo_comment'           => 'Shown at the top of the voucher.',
        'background'             => 'Stationery / background (optional)',
        'background_comment'     => 'Full-bleed background image for a custom voucher design (landscape card format). The voucher data is overlaid on top.',
        'footer_text'            => 'Footer / legal notice',
    ],

    // Components (backend picker)
    'component' => [
        'purchase_name'        => 'Voucher purchase',
        'purchase_description' => 'Purchase form for vouchers with Mollie payment.',
        'return_name'          => 'Voucher return (after payment)',
        'return_description'   => 'Landing page after the Mollie payment: status + image download.',
        'pos_name'             => 'Voucher till (redemption)',
        'pos_description'      => 'Tablet redemption page for staff: lookup (code/QR), balance, partial redemption, on-site sale.',
    ],

    // Text on the voucher (image + PDF)
    'voucher_card' => [
        'value_over'  => 'Voucher worth :value',
        'for'         => 'For: :name',
        'valid_until' => 'Valid until :date',
        'till_hint'   => 'Present at the till – any remaining balance is kept.',
    ],

    // Emails
    'mail' => [
        // Shared building blocks
        'greeting'          => 'Hello :name,',
        'greeting_plain'    => 'Hello,',
        'regards'           => 'Kind regards',
        'team_with_brand'   => 'Your :brand team',
        'team'              => 'Your team',
        'brand_fallback'    => 'us',
        'label_code'        => 'Voucher code:',
        'label_value'       => 'Value:',
        'label_valid_until' => 'Valid until:',
        'download_image'    => 'Download voucher as image',
        'qr_hint'           => 'The QR code is scanned at the till; any remaining balance is kept.',

        // purchase_confirmation
        'confirmation_subject'  => 'Your voucher for :brand',
        'confirmation_thanks'   => 'thank you for buying a voucher from **:brand**.',
        'confirmation_digital'  => 'Your voucher is attached as an image and can be printed or shown on a smartphone. The QR code is scanned at the till; any remaining balance is kept.',
        'confirmation_physical' => 'Your voucher card will be sent by post to the address you provided.',

        // purchase_notification (restaurant)
        'notification_subject'       => 'New voucher purchase: :total (:delivery)',
        'notification_intro'         => 'A voucher has been purchased and paid for.',
        'label_delivery'             => 'Delivery:',
        'notification_physical_hint' => '(card by post – please issue & ship)',
        'label_total'                => 'Total:',
        'label_buyer'                => 'Buyer:',
        'label_phone'                => 'Phone:',
        'label_address'              => 'Address:',
        'label_recipient'            => 'Recipient:',
        'label_message'              => 'Message:',

        // shipping_notification
        'shipping_subject'    => 'Your voucher is on its way',
        'shipping_body'       => 'Your voucher card was sent by post today to the following address:',
        'shipping_body_brand' => 'Your voucher card from **:brand** was sent by post today to the following address:',

        // voucher_delivery
        'delivery_subject'       => 'Your voucher',
        'delivery_subject_brand' => 'Your voucher from :brand',
        'delivery_intro'         => 'please find your voucher attached.',
        'delivery_intro_brand'   => 'please find your voucher from **:brand** attached.',
    ],

    // Backend: redemption panel (voucher form)
    'redeem' => [
        'balance_label' => 'Balance:',
        'status_label'  => 'Status:',
        'amount_label'  => 'Redeem amount (€)',
        'amount_ph'     => 'e.g. 30.00',
        'note_ph'       => 'Note (optional)',
        'confirm'       => 'Book this redemption now (binding)?',
        'loading'       => 'Booking redemption …',
        'button'        => 'Redeem',
        'help'          => 'Books a (partial) redemption via the tamper-proof ledger. A booked redemption cannot be edited, only offset by a later correcting entry.',
        'not_possible'  => 'No redemption is currently possible for this voucher.',
        'history'       => 'Redemption history',
        'col_time'      => 'Time',
        'col_amount'    => 'Amount',
        'col_balance'   => 'Balance after',
        'col_kind'      => 'Kind',
        'col_source'    => 'Source',
        'col_note'      => 'Note',
        'empty'         => 'No redemptions yet.',
        'no_permission' => 'No permission to redeem.',
        // Redemption model (backend form/list)
        'amount_cents_label'    => 'Amount (cents)',
        'amount_cents_comment'  => 'Positive = redemption, negative = reversal/correction.',
        'balance_after_label'   => 'Balance after (cents)',
        'vat_breakdown'         => 'VAT breakdown (JSON)',
        'vat_breakdown_comment' => 'e.g. [{"rate":7,"net_cents":1308,"vat_cents":92,"gross_cents":1400}]',
    ],

    // Backend: shipping panel (order form)
    'shipping' => [
        'only_physical' => 'Shipping applies only to physical vouchers (by post).',
        'address_label' => 'Delivery address:',
        'write_on_card' => 'Write on the card — voucher no.:',
        'value'         => 'Value',
        'shipped_on'    => '✓ Shipped on :date',
        'mark_button'   => 'Mark as shipped',
        'mark_confirm'  => 'Mark the card as shipped and notify the buyer?',
        'mark_loading'  => 'Marking as shipped …',
        'mark_help'     => 'Sets the shipping date and sends the buyer the “Your voucher is on its way” email.',
    ],

    // Backend: list toolbar
    'toolbar' => [
        'create_manual'  => 'Create voucher manually',
        'open_pos'       => 'Open till / on-site sale',
        'delete'         => 'Delete',
        'delete_confirm' => 'Really delete the selected vouchers?',
    ],

    // Frontend: purchase form
    'purchase' => [
        'amount_label'        => 'Amount (€)',
        'amount_choose'       => 'Choose amount',
        'amount_placeholder'  => 'Amount of your choice in €',
        'phone_hint'          => '(optional, for queries)',
        'delivery_legend'     => 'Delivery',
        'delivery_digital'    => 'Digital (image with QR code, instantly by email)',
        'delivery_physical'   => 'Card by post (plus :fee shipping)',
        'recipient_optional'  => 'Recipient (optional)',
        'message_optional'    => 'Personal message (optional)',
        'submit'              => 'Continue to payment',
        'thank_you'           => 'Thank you.',
        'payment_description' => 'Voucher #:id',
    ],

    // Frontend: return page (after payment)
    'return' => [
        'issued_thanks' => 'Thank you! Your voucher has been created and sent to your email address.',
        'processing'    => 'Your payment is being processed … one moment please.',
    ],

    // Frontend: till (POS)
    'pos' => [
        'staff_only'        => 'This page is staff-only. Please sign in to the :link first.',
        'backend'           => 'backend',
        'back_to_backend'   => '↩ To the backend',
        'redeem_title'      => 'Redeem voucher',
        'redeem_subtitle'   => 'Enter code or scan QR code',
        'code_ph'           => 'Code, e.g. MAM-100042-K',
        'search'            => 'Search',
        'scan_qr'           => 'Scan QR',
        'sell_title'        => 'On-site sale',
        'sell_subtitle'     => 'issue a new voucher at the till',
        'amount'            => 'Amount',
        'value_ph'          => 'Value €',
        'card_type'         => 'Card type',
        'physical_card'     => 'Physical card',
        'digital_card'      => 'Digital (image/QR)',
        'preprinted'        => 'Pre-printed card number',
        'preprinted_hint'   => '(optional — empty = automatic)',
        'preprinted_ph'     => 'e.g. 100042',
        'shipping_address'  => 'Shipping address',
        'shipping_hint'     => '(optional — only if the card is shipped)',
        'email_hint'        => '(required for digital)',
        'email_ph'          => 'customer@example.com',
        'recipient_hint'    => '(optional)',
        'recipient_ph'      => 'Name of the recipient',
        'sell_button'       => 'Sell',
        'scanner_loading'   => 'QR scanner still loading – please tap again.',
        'for_recipient'     => 'for :name',
        'valid_until_short' => 'valid until :date',
        'redeem_amount_ph'  => 'Amount €',
        'no_redeem'         => 'No redemption possible (status: :status).',
        'sold_created'      => 'New voucher :code worth :value created.',
        'sold_write_number' => 'Please write the number :number on the card.',
        'sold_emailed'      => 'The voucher was sent by email.',
    ],

    // Success flashes
    'flash' => [
        'redeemed'       => 'Redeemed. New balance: :balance',
        'redeem_booked'  => 'Redemption booked. New balance: :balance',
        'sold'           => 'Voucher created: :code',
        'marked_shipped' => 'Marked as shipped. Shipping notification sent to the buyer.',
    ],

    // Error / validation messages
    'error' => [
        'voucher_not_found'       => 'No voucher found. Please check the code.',
        'voucher_not_found_short' => 'Voucher not found.',
        'invalid_amount'          => 'Please enter a valid amount.',
        'sell_failed'             => 'Sale failed.',
        'not_authorized'          => 'Not authorized. Please sign in to the backend.',
        'digital_email_required'  => 'A valid email address is required for digital vouchers.',
        'save_failed'             => 'Could not be saved: :error',
        'insufficient_balance'    => 'Amount exceeds the balance (:balance).',
        'voucher_void'            => 'The voucher has been voided.',
        'voucher_expired'         => 'The voucher has expired.',
        'redeem_failed'           => 'Redemption failed.',
        'payment_unavailable'     => 'Online payment is currently unavailable. Please try again later.',
        'payment_start_failed'    => 'The payment could not be started. Please try again.',
        'check_input'             => 'Please check your input.',
        'amount_out_of_range'     => 'The amount is outside the allowed range.',
        'order_not_found'         => 'Order not found.',
        'cannot_mark_shipped'     => 'This order cannot be marked as shipped (no shipping or already shipped).',
    ],
];
