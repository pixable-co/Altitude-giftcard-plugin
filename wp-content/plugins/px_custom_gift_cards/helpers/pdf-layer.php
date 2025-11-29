<?php


/**
 * Generate PDF from HTML using PDFLayer API.
 *
 * @param string $html HTML content to convert
 * @param string $filename File name without extension
 * @return string|false Path to PDF, or false on failure
 */
function pxgc_generate_pdf_via_pdflayer($html, $filename)
{
    $api_key = "dbbdb4df859107d236d92faaf1e0126a";

    $upload_dir = wp_get_upload_dir();
    $folder = $upload_dir['basedir'] . '/giftcards/';

    if (!file_exists($folder)) {
        wp_mkdir_p($folder);
    }

    $pdf_path = $folder . $filename . '.pdf';

    // Correct API endpoint
    $url = "https://api.pdflayer.com/api/convert?access_key=" . $api_key;

    $response = wp_remote_post($url, [
        'timeout' => 30,
        'body' => [
            'document_html' => $html,
            'page_size'     => 'A4',
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_left'   => 0,
            'margin_right'  => 0,
            'test'          => 0
        ]
    ]);

    if (is_wp_error($response)) {
        file_put_contents(WP_CONTENT_DIR . '/pdflayer-error.html', $response->get_error_message());
        return false;
    }

    $pdf_binary = wp_remote_retrieve_body($response);

    if (stripos($pdf_binary, '%PDF') !== 0) {
        file_put_contents(WP_CONTENT_DIR . '/pdflayer-error.html', $pdf_binary);
        return false;
    }

    file_put_contents($pdf_path, $pdf_binary);

    return $pdf_path;
}


function pxgc_generate_pdf_html($value, $service, $coupon_code)
{
    $voucher_bg_image = 'https://altitudecentre.com/wp-content/uploads/2025/11/giftcard-img.jpg';

    return '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>

    body {
        margin: 0;
        padding: 0;
        background: #ffffff;
        font-family: Arial, sans-serif;
    }

    /* A4 landscape canvas */
    .a4 {
        width: 1120px;
        height: 793px;
        position: relative;
        background: #ffffff;
        overflow: hidden;
    }

    /* Voucher */
    .voucher {
        width: 1000px;
        height: 600px;
        background-image: url(' . $voucher_bg_image . ');
        background-size: cover;
        background-position: center;
        transform: rotate(0deg);
        position: absolute;
        top: 100px;
        left: 60px;
    }

    /* Overlay */
    .overlay {
        width: 600px;
        height: 400px;
        background: rgba(29,44,77,0.85);
        position: absolute;
        top: 40px;
        left: 40px;
        padding: 25px 30px;
        border-radius: 6px;
        color: #ffffff;
        box-sizing: border-box;
    }

    .title {
        font-size: 32px;
        font-weight: bold;
        margin-bottom: 30px;
    }

    .url {
        font-size: 14px;
        position: absolute;
        top: 1rem;
        right: 2rem;
        text-align: right;
    }

    .note {
        font-size: 12px;
        margin-top: 10px;
    }

    .label {
        font-size: 16px;
        margin-top: 18px;
        margin-bottom: 6px;
        font-weight: bold;
    }

    .value-box {
        background: #ffffff;
        color: #000;
        padding: 10px 12px;
        border-radius: 4px;
        font-size: 14px;
        line-height: 1.4;
        word-wrap: break-word;
        min-height: 24px;
    }

</style>
</head>
<body>

<div class="a4">
    <div class="voucher">
        <div class="overlay">

            <div class="title">Gift Card</div>

            <div class="url">
                www.altitudecentre.com<br><br>
                Book Today: 0207 193 1626<br><br>
                <span class="note">Terms and conditions apply</span>
            </div>

            <div class="label">To the value of:</div>
            <div class="value-box">£' . number_format($value, 2) . '</div>

            <div class="label">For the service of:</div>
            <div class="value-box">' . htmlspecialchars($service) . '</div>

            <div class="label">Code:</div>
            <div class="value-box">' . htmlspecialchars($coupon_code) . '</div>

        </div>
    </div>
</div>

</body>
</html>';
}
