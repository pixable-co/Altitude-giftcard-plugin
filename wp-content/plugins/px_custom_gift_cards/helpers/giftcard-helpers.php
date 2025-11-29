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
 * Send gift card email to the customer (only one per order)
 */
function pxgc_send_giftcard_email($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) return;

    $billing_email = $order->get_billing_email();
    $customer_name = $order->get_billing_first_name();

    if (!$billing_email) return;

    // ------------------------------------------------------------
    // Find the Gift Card item (only one allowed)
    // ------------------------------------------------------------
    $giftcard_item = null;

    foreach ($order->get_items() as $item) {
        if ($item->get_product_id() == pxgc_get_giftcard_product_id()) {
            $giftcard_item = $item;
            break;
        }
    }

    if (!$giftcard_item) return;

    // ------------------------------------------------------------
    // Extract stored meta
    // ------------------------------------------------------------
    $coupon_code = $giftcard_item->get_meta('Generated Gift Card Code');
    $pxgc_type   = $giftcard_item->get_meta('_pxgc_type');
    $pxgc_id     = $giftcard_item->get_meta('_pxgc_id');
    $value       = $giftcard_item->get_meta('_pxgc_price');

    if (!$coupon_code || !$pxgc_type || !$pxgc_id || !$value) {
        return; // missing information
    }

    // Determine the readable service name
    if ($pxgc_type === 'product') {
        $redeem_name = wc_get_product($pxgc_id)->get_name();
    } else {
        $redeem_name = get_the_title($pxgc_id);
    }

    // Expiry date: 12 months
    $expiry_date = date("d F Y", strtotime("+12 months"));

    // Redeem URL:
    $redeem_url = "https://altitudecentre.com/user-registration/";

    // ------------------------------------------------------------
    // Generate PDF BEFORE sending email
    // ------------------------------------------------------------
    $pdf_html = pxgc_generate_pdf_html($value, $redeem_name, $coupon_code);

    $pdf_path = pxgc_generate_pdf_via_pdflayer(
        $pdf_html,
        "giftcard-$coupon_code"
    );

    $attachments = [];
    if ($pdf_path && file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }

    // ------------------------------------------------------------
    // USE YOUR ORIGINAL EMAIL TEMPLATE HTML EXACTLY
    // ------------------------------------------------------------
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
                                Your Gift Card is Ready
                            </h2>
                        </td>
                    </tr>

                    <tr><td style="height:20px;"></td></tr>

                    <tr>
                        <td style="padding:0 30px;font-size:15px;line-height:1.6;">
                            Hi <?php echo esc_html($customer_name); ?>,<br><br>
                            Thank you for your purchase! Your gift card has been successfully generated.
                            You’ll find all the details below, along with your printable PDF voucher.
                        </td>
                    </tr>

                    <tr><td style="height:25px;"></td></tr>

                    <tr>
                        <td style="padding:0 30px;">
                            <table width="100%" cellpadding="15" cellspacing="0" bgcolor="#f7f7f7"
                                style="border:1px solid #ddd;border-radius:6px;">
                                <tr>
                                    <td style="font-size:15px;line-height:1.6;">
                                        <strong>Gift Card Code:</strong> <?php echo esc_html($coupon_code); ?><br>
                                        <strong>Value:</strong> £<?php echo number_format($value, 2); ?><br>
                                        <strong>Redeemable For:</strong> <?php echo esc_html($redeem_name); ?><br>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr><td style="height:25px;"></td></tr>

                    <tr>
                        <td style="padding:0 30px;font-size:15px;line-height:1.6;">
                            To redeem this gift card, simply enter the code during checkout when booking
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

    // ------------------------------------------------------------
    // SEND EMAIL WITH ATTACHMENT
    // ------------------------------------------------------------
    wp_mail(
        $billing_email,
        'Your Altitude Centre Gift Card',
        $message,
        ['Content-Type: text/html; charset=UTF-8'],
        $attachments
    );
}
