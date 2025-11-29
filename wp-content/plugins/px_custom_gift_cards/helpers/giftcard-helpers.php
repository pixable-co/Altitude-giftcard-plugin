<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Returns the ID of your existing Gift Card product.
 * Update the return value to the actual product ID.
 */
function pxgc_get_giftcard_product_id()
{
    return 19298; // <-- REPLACE THIS WITH YOUR GIFT CARD PRODUCT ID
}

/**
 * Send gift card email to the customer (supports multiple gift cards per order)
 */
function pxgc_send_giftcard_email($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $billing_email = $order->get_billing_email();
    $customer_name = $order->get_billing_first_name();

    if (!$billing_email) {
        return;
    }

    $giftcards = [];
    $attachments = [];

    foreach ($order->get_items() as $item) {
        if ($item->get_product_id() != pxgc_get_giftcard_product_id()) {
            continue;
        }

        $pxgc_type = $item->get_meta('_pxgc_type');
        $pxgc_id   = $item->get_meta('_pxgc_id');
        $raw_value = $item->get_meta('_pxgc_price');

        if ($raw_value === '' || $raw_value === null || !$pxgc_type || !$pxgc_id) {
            continue;
        }

        $gift_value = floatval($raw_value);

        $coupon_codes = [];
        $raw_codes = $item->get_meta('Generated Gift Card Code', false);

        if (!empty($raw_codes)) {
            foreach ((array) $raw_codes as $raw_code) {
                $meta_value = null;

                if ($raw_code instanceof WC_Meta_Data) {
                    $meta_value = $raw_code->value;
                } elseif (is_array($raw_code) && isset($raw_code['value'])) {
                    $meta_value = $raw_code['value'];
                } else {
                    $meta_value = $raw_code;
                }

                if (!empty($meta_value) && is_string($meta_value)) {
                    $coupon_codes[] = $meta_value;
                }
            }
        }

        if (empty($coupon_codes)) {
            $single_code = $item->get_meta('Generated Gift Card Code');
            if (!empty($single_code) && is_string($single_code)) {
                $coupon_codes = [$single_code];
            }
        }

        if (empty($coupon_codes)) {
            continue;
        }

        $redeem_name = '';

        if ($pxgc_type === 'product') {
            $redeem_product = wc_get_product($pxgc_id);
            if ($redeem_product) {
                $redeem_name = $redeem_product->get_name();
            }
        } else {
            $redeem_name = get_the_title($pxgc_id);
        }

        if (!$redeem_name) {
            $redeem_name = __('Selected Service', 'pxgc');
        }

        foreach ($coupon_codes as $coupon_code) {
            if (!$coupon_code) {
                continue;
            }

            // Generate PDF before sending the email so all attachments are ready.
            $pdf_html = pxgc_generate_pdf_html($gift_value, $redeem_name, $coupon_code);
            $pdf_path = pxgc_generate_pdf_via_pdflayer(
                $pdf_html,
                "giftcard-$coupon_code"
            );

            if ($pdf_path && file_exists($pdf_path)) {
                $attachments[] = $pdf_path;
            }

            $giftcards[] = [
                'code'        => $coupon_code,
                'redeem_name' => $redeem_name,
                'value'       => $gift_value,
            ];
        }
    }

    if (empty($giftcards)) {
        return;
    }

    $giftcard_count = count($giftcards);
    $expiry_date = date("d F Y", strtotime("+12 months"));
    $redeem_url = "https://altitudecentre.com/user-registration/";

    ob_start();
    ?>
    <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f0f0f0">
        <tr>
            <td align="center" style="padding:20px 0;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff"
                    style="width:600px;max-width:600px;font-family:Arial,Helvetica,sans-serif;color:#111;">

                    <tr>
                        <td>
                            <img src="https://crm.zoho.eu/crm/viewInLineImage?fileContent=1c0941776bba704871ca659590ede1bb80aa3020ed6fb35ca02e98719c201ccea280cb8c6952ab3ea6458bb7ebced09a3607a99fc31c2f35d96dea7cae56df3298188e923259f4b41fbb22e0635ac6970fd2faa777e25e9ff7cffe711055d98f"
                                alt="Altitude Centre" width="600" style="width:100%;display:block;border:0;">
                        </td>
                    </tr>

                    <tr><td style="height:25px;"></td></tr>

                    <tr>
                        <td align="center">
                            <h2 style="margin:0;font-size:22px;font-weight:700;color:#000;">
                                Your Gift Card<?php echo $giftcard_count > 1 ? 's are' : ' is'; ?> Ready
                            </h2>
                        </td>
                    </tr>

                    <tr><td style="height:20px;"></td></tr>

                    <tr>
                        <td style="padding:0 30px;font-size:15px;line-height:1.6;">
                            Hi <?php echo esc_html($customer_name); ?>,<br><br>
                            Thank you for your purchase! Your gift card<?php echo $giftcard_count > 1 ? 's have' : ' has'; ?> been successfully generated.
                            You'll find each gift card listed below, and your printable PDF voucher<?php echo $giftcard_count > 1 ? 's are' : ' is'; ?> attached.
                        </td>
                    </tr>

                    <?php foreach ($giftcards as $index => $giftcard) : ?>
                        <tr><td style="height:25px;"></td></tr>
                        <tr>
                            <td style="padding:0 30px;">
                                <table width="100%" cellpadding="15" cellspacing="0" bgcolor="#f7f7f7"
                                    style="border:1px solid #ddd;border-radius:6px;">
                                    <tr>
                                        <td style="font-size:15px;line-height:1.6;">
                                            <strong>Gift Card <?php echo esc_html(absint($index + 1)); ?> Code:</strong> <?php echo esc_html($giftcard['code']); ?><br>
                                            <strong>Value:</strong> &pound;<?php echo number_format($giftcard['value'], 2); ?><br>
                                            <strong>Redeemable For:</strong> <?php echo esc_html($giftcard['redeem_name']); ?><br>
                                            <strong>Expires:</strong> <?php echo esc_html($expiry_date); ?><br>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr><td style="height:25px;"></td></tr>

                    <tr>
                        <td style="padding:0 30px;font-size:15px;line-height:1.6;">
                            To redeem a gift card, simply enter the relevant code during checkout when booking
                            your next session. Make sure the correct service is added to your basket.
                        </td>
                    </tr>

                    <tr><td style="height:25px;"></td></tr>

                    <tr>
                        <td align="center">
                            <a href="<?php echo esc_url($redeem_url); ?>" style="background:#283D91;color:#fff;padding:12px 24px;text-decoration:none;
                               font-size:14px;border-radius:5px;display:inline-block;">
                                Redeem Your Gift Card
                            </a>
                        </td>
                    </tr>

                    <tr><td style="height:35px;"></td></tr>

                    <tr>
                        <td style="padding:0 30px;font-size:15px;line-height:1.6;">
                            Best wishes,<br>
                            The Altitude Centre Team
                        </td>
                    </tr>

                    <tr><td style="height:30px;"></td></tr>

                </table>
            </td>
        </tr>
    </table>
    <?php
    $message = ob_get_clean();

    wp_mail(
        $billing_email,
        'Your Altitude Centre Gift Card',
        $message,
        ['Content-Type: text/html; charset=UTF-8'],
        $attachments
    );
}
