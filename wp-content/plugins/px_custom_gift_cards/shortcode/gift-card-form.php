<?php
if (!defined('ABSPATH'))
    exit;

if (!function_exists('pxgc_localize_giftcard_script')) {
    function pxgc_localize_giftcard_script()
    {
        static $localized = false;

        if ($localized) {
            return;
        }

        wp_localize_script('pxgc-js', 'pxgc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'cart_url' => wc_get_cart_url(),
            'view_cart_text' => __('View cart', 'pxgc'),
            'added_text' => __('has been added to your cart,', 'pxgc'),
            'default_product_label' => __('Gift card', 'pxgc')
        ]);

        $localized = true;
    }
}

add_shortcode('px_giftcard_consultations', function () {

    wp_enqueue_style(
        'pxgc-css',
        PXGC_URL . 'css/giftcard.css',
        [],
        filemtime(PXGC_PATH . 'css/giftcard.css')
    );

    wp_enqueue_script(
        'pxgc-js',
        PXGC_URL . 'js/giftcard.js',
        ['jquery'],
        filemtime(PXGC_PATH . 'js/giftcard.js'),
        true
    );

    pxgc_localize_giftcard_script();

    // Load consultations filtered by ACF field gift_card_allowed = true
    $items = [];
    $posts = get_posts([
        'post_type' => 'consultation',
        'post_status' => 'publish',
        'posts_per_page' => -1,

        'meta_query' => [
            [
                'key' => 'gift_card_allowed',
                'value' => '1',
                'compare' => '='
            ]
        ]
    ]);

    foreach ($posts as $p) {
        $price = get_field('price', $p->ID);
        if ($price !== null && $price !== '') {
            $items[] = [
                'type' => 'consultation',
                'id' => $p->ID,
                'name' => $p->post_title,
                'price' => $price
            ];
        }
    }

    ob_start(); ?>

    <select class="pxgc_select" data-type="consultation">
        <option value="">Choose a Consultation</option>
        <?php foreach ($items as $item): ?>
            <option value="<?php echo esc_attr($item['type'] . ':' . $item['id']); ?>"
                data-price="<?php echo esc_attr($item['price']); ?>">
                <?php echo esc_html($item['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php return ob_get_clean();
});


add_shortcode('px_giftcard_passes', function () {

    wp_enqueue_style(
        'pxgc-css',
        PXGC_URL . 'css/giftcard.css',
        [],
        filemtime(PXGC_PATH . 'css/giftcard.css')
    );

    wp_enqueue_script(
        'pxgc-js',
        PXGC_URL . 'js/giftcard.js',
        ['jquery'],
        filemtime(PXGC_PATH . 'js/giftcard.js'),
        true
    );

    pxgc_localize_giftcard_script();

    // Fixed product IDs
    $ids = [18467, 18468, 18469, 18470, 18471,];

    $items = [];
    foreach ($ids as $pid) {
        if ($prod = wc_get_product($pid)) {
            $items[] = [
                'type' => 'product',
                'id' => $pid,
                'name' => $prod->get_name(),
                'price' => $prod->get_price()
            ];
        }
    }

    ob_start(); ?>

    <select class="pxgc_select" data-type="product">
        <option value="">Choose a Pass</option>
        <?php foreach ($items as $item): ?>
            <option value="<?php echo esc_attr($item['type'] . ':' . $item['id']); ?>"
                data-price="<?php echo esc_attr($item['price']); ?>">
                <?php echo esc_html($item['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php return ob_get_clean();
});

add_shortcode('px_giftcard_button', function () {

    wp_enqueue_script(
        'pxgc-js',
        PXGC_URL . 'js/giftcard.js',
        ['jquery'],
        filemtime(PXGC_PATH . 'js/giftcard.js'),
        true
    );

    pxgc_localize_giftcard_script();

    ob_start(); ?>

    <div id="pxgc_price_wrapper">
        <strong>Gift Card Price:</strong>
        <span id="pxgc_price"></span>
    </div>

    <div class="pxgc_button_section">
        <div class="pxgc_notice" style="display:none;"></div>
        <div class="pxgc_add_button w-btn us-btn-style_1">
            <span class="pxgc-btn-text">Add Gift Card</span>
            <span class="loader" style="display:none;"></span>
        </div>
    </div>

    <?php
    return ob_get_clean();
});
