<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Create a coupon automatically when a Gift Card is purchased
 */
add_action('woocommerce_thankyou', function ($order_id) {
    pxgc_generate_coupons_from_order($order_id);
    pxgc_send_giftcard_email($order_id);
});

function pxgc_generate_coupons_from_order($order_id)
{

    $generated = [];
    $order = wc_get_order($order_id);

    if (!$order) {
        return $generated;
    }

    foreach ($order->get_items() as $item_id => $item) {

        // Detect gift card product
        if ($item->get_product_id() != pxgc_get_giftcard_product_id()) {
            continue;
        }

        // Get hidden meta saved at checkout
        $type = $item->get_meta('_pxgc_type');
        $id = $item->get_meta('_pxgc_id');
        $value = $item->get_meta('_pxgc_price');

        if (!$type || !$id || !$value) {
            continue;
        }

        // Generate a unique coupon code
        $coupon_code = strtoupper('GC-' . $order_id . '-' . wp_generate_password(6, false, false));

        // Create coupon
        $coupon_id = wp_insert_post([
            'post_title' => $coupon_code,
            'post_content' => 'Automatically generated gift card coupon.',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon'
        ]);

        if (!$coupon_id) {
            continue;
        }

        // Set coupon core properties
        update_post_meta($coupon_id, 'discount_type', 'fixed_cart');
        update_post_meta($coupon_id, 'coupon_amount', $value);
        update_post_meta($coupon_id, 'usage_limit', 1);
        update_post_meta($coupon_id, 'individual_use', 'yes');
        update_post_meta($coupon_id, 'free_shipping', 'no');

        /**
         * -----------------------------------------------------------
         * RESTRICTION LOGIC BY TYPE
         * -----------------------------------------------------------
         */

        if ($type === 'product') {

            // NORMAL PRODUCT → restrict only to this product ID
            update_post_meta($coupon_id, 'product_ids', $id);
        }

        if ($type === 'consultation') {

            // CONSULTATION → always restrict to product **18472**
            update_post_meta($coupon_id, 'product_ids', 18472);

            // Also record the consultation post ID into ACF field "consultation"
            update_field('consultation', $id, $coupon_id); // stores the post ID
            update_field("order_id", $order_id, $coupon_id); // stores the order ID
        }

        // Store coupon code on order item (visible in admin)
        $item->add_meta_data('Generated Gift Card Code', $coupon_code, true);
        $item->save();

        $generated[] = $coupon_code;
    }

    if (!empty($generated)) {
        update_post_meta($order_id, '_pxgc_generated_codes', $generated);
        update_post_meta($order_id, 'Gift Card Codes', implode(', ', $generated));
    } else {
        delete_post_meta($order_id, '_pxgc_generated_codes');
        delete_post_meta($order_id, 'Gift Card Codes');
    }

    return $generated;
}


/**
 * ---------------------------------------------------------
 * Display Selected Item Info In Cart (“Redeemable For:”)
 * ---------------------------------------------------------
 */
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {

    // SHOW ONLY the readable name
    if (isset($cart_item['pxgc_type']) && isset($cart_item['pxgc_id'])) {

        if ($cart_item['pxgc_type'] === 'product') {
            $product = wc_get_product($cart_item['pxgc_id']);
            if ($product) {
                $item_data[] = [
                    'name' => 'Redeemable For',
                    'value' => $product->get_name()
                ];
            }
        }

        if ($cart_item['pxgc_type'] === 'consultation') {
            $item_data[] = [
                'name' => 'Redeemable For',
                'value' => get_the_title($cart_item['pxgc_id'])
            ];
        }
    }

    return $item_data;
}, 10, 2);


/**
 * ---------------------------------------------------------
 * Override Gift Card Product Price Dynamically (AJAX Price)
 * ---------------------------------------------------------
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {

    if (is_admin() && !defined('DOING_AJAX'))
        return;
    if (empty($cart->get_cart()))
        return;

    foreach ($cart->get_cart() as $key => $cart_item) {

        if (!isset($cart_item['pxgc_price'])) {
            continue;
        }

        $price = floatval($cart_item['pxgc_price']);

        if ($price > 0) {

            // FORCE override (WooCommerce safe)
            $cart_item['data']->set_price($price);
            $cart->cart_contents[$key]['data']->set_price($price);
        }
    }

}, 99);


/**
 * ---------------------------------------------------------
 * Rename Cart Item to: "Gift Card for {Name}"
 * ---------------------------------------------------------
 */
add_filter('woocommerce_cart_item_name', function ($name, $cart_item, $cart_item_key) {

    if (!isset($cart_item['pxgc_type']) || !isset($cart_item['pxgc_id'])) {
        return $name;
    }

    $selected_name = '';

    if ($cart_item['pxgc_type'] === 'product') {
        $product = wc_get_product($cart_item['pxgc_id']);
        if ($product) {
            $selected_name = $product->get_name();
        }
    }

    if ($cart_item['pxgc_type'] === 'consultation') {
        $selected_name = get_the_title($cart_item['pxgc_id']);
    }

    if ($selected_name) {
        return 'Gift Card for ' . esc_html($selected_name);
    }

    return $name;
}, 10, 3);


/**
 * ---------------------------------------------------------
 * Rename Order Line Item (For Emails + Admin Orders)
 * ---------------------------------------------------------
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {

    if (!isset($values['pxgc_type']) || !isset($values['pxgc_id'])) {
        return;
    }

    $selected_name = '';

    if ($values['pxgc_type'] === 'product') {
        $product = wc_get_product($values['pxgc_id']);
        if ($product) {
            $selected_name = $product->get_name();
        }
    }

    if ($values['pxgc_type'] === 'consultation') {
        $selected_name = get_the_title($values['pxgc_id']);
    }

    if ($selected_name) {
        $item->set_name('Gift Card for ' . $selected_name);
    }

}, 10, 4);


/**
 * Save hidden meta (pxgc_type, pxgc_id) to order item
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {

    if (!isset($values['pxgc_type']) || !isset($values['pxgc_id'])) {
        return;
    }

    // Save hidden meta to order line item
    $item->add_meta_data('_pxgc_type', $values['pxgc_type'], true);
    $item->add_meta_data('_pxgc_id', $values['pxgc_id'], true);
    $item->add_meta_data('_pxgc_price', $values['pxgc_price'], true);

}, 10, 4);


/**
 * Consultation Gift Card Coupon Validation
 *
 * For consultation coupons:
 * - Coupon must be linked to consultation ID in ACF field "consultation"
 * - Cart must contain product 18472
 * - That cart line must have meta custom_data['Post ID'] matching the coupon’s consultation ID
 */
add_filter('woocommerce_coupon_is_valid', 'pxgc_validate_consultation_coupon', 10, 3);

function pxgc_validate_consultation_coupon($valid, $coupon, $discount) {

    if (!$valid) {
        return false;
    }

    $coupon_id = $coupon->get_id();

    // ACF field on coupon: stores "consultation" post ID
    $coupon_consultation_id = get_field('consultation', $coupon_id);

    // If no ACF consultation field → this is NOT a consultation gift card → let WooCommerce handle it
    if (!$coupon_consultation_id) {
        return $valid;
    }

    // -----------------------------
    // This *IS* a consultation gift card
    // -----------------------------
    $cart = WC()->cart;
    $matched = false;

    foreach ($cart->get_cart() as $cart_item) {

        // Only valid on consultation product 18472
        if ($cart_item['product_id'] != 18472) {
            continue;
        }

        // Check custom meta structure: custom_data['Post ID']
        if (!empty($cart_item['custom_data']) &&
            !empty($cart_item['custom_data']['Post ID'])) {

            $cart_post_id = intval($cart_item['custom_data']['Post ID']);

            // Must match coupon ACF consultation field
            if ($cart_post_id === intval($coupon_consultation_id)) {
                $matched = true;
                break;
            }
        }
    }

    if (!$matched) {
        wc_add_notice(__(
            "This gift card is only valid for the specific consultation it was issued for.",
            "woocommerce"
        ), 'error');

        return false;
    }

    return true;
}
