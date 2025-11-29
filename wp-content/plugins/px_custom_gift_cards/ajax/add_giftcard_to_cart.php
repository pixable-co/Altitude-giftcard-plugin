<?php

/**
 * ---------------------------------------------------------
 * AJAX — Add Gift Card Directly to Cart
 * ---------------------------------------------------------
 */
add_action('wp_ajax_pxgc_add_to_cart', 'pxgc_add_to_cart');
add_action('wp_ajax_nopriv_pxgc_add_to_cart', 'pxgc_add_to_cart');


function pxgc_add_to_cart()
{
    if (!isset($_POST['selected_value'], $_POST['price'])) {
        wp_send_json_error(['message' => 'Missing data']);
    }

    $value = sanitize_text_field($_POST['selected_value']);
    $price = floatval($_POST['price']);

    list($type, $id) = explode(':', $value);

    $giftcard_id = pxgc_get_giftcard_product_id();

    if (!$giftcard_id) {
        wp_send_json_error(['message' => 'Gift card product ID not configured.']);
    }

    // ---------------------------------------------------------
    // ENFORCE MAXIMUM OF 3 GIFT CARDS IN CART
    // ---------------------------------------------------------
    $current_qty = 0;
    if (WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['product_id'] == $giftcard_id) {
                $current_qty += isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 0;
            }
        }
    }

    if ($current_qty >= 3) {
        wp_send_json_error([
            'message' => __('You can only purchase maximum 3 gift card at 1 time.', 'pxgc')
        ]);
    }

    // ---------------------------------------------------------
    // ADD TO CART
    // ---------------------------------------------------------
    $cart_item_key = WC()->cart->add_to_cart(
        $giftcard_id,
        1,
        0,
        [],
        [
            'pxgc_type' => $type,
            'pxgc_id' => intval($id),
            'pxgc_price' => $price
        ]
    );

    if (!$cart_item_key) {
        wp_send_json_error(['message' => 'Unable to add gift card to cart.']);
    }

    // ---------------------------------------------------------
    // IMMEDIATELY SET OVERRIDDEN PRICE
    // ---------------------------------------------------------
    if (isset(WC()->cart->cart_contents[$cart_item_key])) {
        WC()->cart->cart_contents[$cart_item_key]['data']->set_price($price);
    }

    $item_name = '';
    if ($type === 'product') {
        $product = wc_get_product($id);
        if ($product) {
            $item_name = $product->get_name();
        }
    } elseif ($type === 'consultation') {
        $item_name = get_the_title($id);
    }

    wp_send_json_success([
        'message' => 'Gift card added successfully',
        'cart_url' => wc_get_cart_url(),
        'item_name' => $item_name ? $item_name : __('Selected gift card', 'pxgc')
    ]);
}
