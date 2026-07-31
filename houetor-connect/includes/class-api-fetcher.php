<?php
defined('ABSPATH') || exit;

class HWC_API_Fetcher {

    private $uuid;
    private $layout;
    private $items_count;

    public function __construct() {
        $code = get_option('hwc_code', '');
        $parser = new HWT_Parser($code);
        $this->uuid = $parser->get_uuid();
        $this->layout = get_option('hwc_layout', 'grid');
        $this->items_count = intval(get_option('hwc_items_count', 12));
    }

    public function fetch($module) {
        if (empty($this->uuid)) {
            return '';
        }

        $cache_key = 'hwc_' . md5($this->uuid . '_' . $module . '_' . $this->layout);
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $cached;
        }

        $url = HWC_API_BASE . '/' . urlencode($this->uuid) . '/' . urlencode($module);
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data)) {
            return '';
        }

        $items = array_slice($data, 0, $this->items_count);
        $html = $this->render($items, $module);

        set_transient($cache_key, $html, 5 * MINUTE_IN_SECONDS);

        return $html;
    }

    private function render($items, $module) {
        $layout_class = 'hwc-' . $this->layout;
        $module_class = 'hwc-module-' . sanitize_html_class($module);

        $html = '<div class="hwc-container ' . esc_attr($layout_class) . ' ' . esc_attr($module_class) . '">';

        foreach ($items as $item) {
            $title       = isset($item['title']) ? esc_html($item['title']) : '';
            $description = isset($item['description']) ? esc_html(wp_trim_words($item['description'], 20)) : '';
            $price       = isset($item['price']) ? esc_html($item['price']) : '';
            $image_url   = isset($item['image']) ? esc_url($item['image']) : '';
            $item_id     = isset($item['id']) ? esc_attr($item['id']) : '';

            $html .= '<div class="hwc-card">';

            if (!empty($image_url)) {
                $html .= '<div class="hwc-card-image">';
                $html .= '<img src="' . $image_url . '" alt="' . $title . '" loading="lazy" />';
                $html .= '</div>';
            }

            $html .= '<div class="hwc-card-body">';
            $html .= '<h3 class="hwc-card-title">' . $title . '</h3>';
            $html .= '<p class="hwc-card-description">' . $description . '</p>';

            if (!empty($price)) {
                $html .= '<p class="hwc-card-price">' . $price . '</p>';
            }

            $html .= '<button class="hwc-btn-order hwc-btn" data-item-id="' . $item_id . '">';
            $html .= 'Commander';
            $html .= '</button>';

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
