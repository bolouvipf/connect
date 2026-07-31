<?php
defined('ABSPATH') || exit;

class HWC_Content_Injector {

    public function inject_content($content) {
        if (!is_singular('page') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $injections = get_option('hwc_injections', array());
        if (empty($injections) || !is_array($injections)) {
            return $content;
        }

        $current_page_id = get_the_ID();
        $matched = array();

        foreach ($injections as $inj) {
            if (isset($inj['page_id']) && intval($inj['page_id']) === $current_page_id) {
                $matched[] = $inj;
            }
        }

        if (empty($matched)) {
            return $content;
        }

        $fetcher = new HWC_API_Fetcher();
        $injected_html = '';

        foreach ($matched as $inj) {
            $module = isset($inj['module']) ? $inj['module'] : 'annonces';
            $position = isset($inj['position']) ? $inj['position'] : 'append';

            $marker_pattern = '/<!-- HWC ' . preg_quote($module, '/') . '(?:-[a-zA-Z0-9_]+)? start -->/s';
            if (preg_match($marker_pattern, $content)) {
                error_log(sprintf(
                    '[HWC] Conflit : module "%s" déjà injecté dans post_content (page %d). Injection filtre ignorée.',
                    $module,
                    $current_page_id
                ));
                continue;
            }

            $html = $fetcher->fetch($module);
            if (!empty($html)) {
                $block_id = substr(md5($current_page_id . '|' . $module . '|' . $position), 0, 12);
                $marker = sprintf(
                    '<!-- HWC %s-%s start -->%s<!-- HWC %s-%s end -->',
                    esc_attr($module),
                    $block_id,
                    $html,
                    esc_attr($module),
                    $block_id
                );
                $injected_html .= $marker;
            }
        }

        if (empty($injected_html)) {
            return $content;
        }

        $position = isset($matched[0]['position']) ? $matched[0]['position'] : 'append';

        switch ($position) {
            case 'prepend':
                return $injected_html . $content;
            case 'replace':
                return $injected_html;
            case 'append':
            default:
                return $content . $injected_html;
        }
    }

    public function enqueue_frontend_assets() {
        $injections = get_option('hwc_injections', array());
        if (empty($injections) || !is_array($injections)) {
            return;
        }

        $current_page_id = get_the_ID();
        $should_enqueue = false;

        foreach ($injections as $inj) {
            if (isset($inj['page_id']) && intval($inj['page_id']) === $current_page_id) {
                $should_enqueue = true;
                break;
            }
        }

        if (!$should_enqueue) {
            return;
        }

        wp_enqueue_style('hwc-front', HWC_PLUGIN_URL . 'assets/front.css', array(), HWC_VERSION);
        wp_enqueue_script('hwc-front', HWC_PLUGIN_URL . 'assets/front.js', array('jquery'), HWC_VERSION, true);
        wp_localize_script('hwc-front', 'hwc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('hwc_order_nonce'),
        ));
    }
}
