<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_HTML_Transformer {

    public static function to_blocks($html) {
        $html = trim($html);
        if (empty($html)) return $html;

        if (preg_match('/^<!--\s*wp:/', $html)) return $html;

        $blocks = parse_blocks($html);
        if (!empty($blocks) && isset($blocks[0]['blockName'])) return $html;

        $patterns = [
            '/<h([1-6])\b[^>]*>(.*?)<\/h[1-6]>/i' => function ($m) {
                $level = $m[1];
                $content = trim(wp_strip_all_tags($m[2]));
                $attrs = json_encode(['level' => (int) $level, 'content' => $content], JSON_UNESCAPED_UNICODE);
                return "\n<!-- wp:heading $attrs --><h$level>$content</h$level><!-- /wp:heading -->\n";
            },
            '/<p\b[^>]*>(.*?)<\/p>/i' => function ($m) {
                $content = trim($m[1]);
                return "\n<!-- wp:paragraph --><p>$content</p><!-- /wp:paragraph -->\n";
            },
            '/<img\b[^>]*src=["\']([^"\']+)["\'][^>]*\/?>/i' => function ($m) {
                $src = $m[1];
                $attrs = json_encode(['url' => $src], JSON_UNESCAPED_UNICODE);
                $caption_guess = pathinfo(parse_url($src, PHP_URL_PATH), PATHINFO_FILENAME);
                return "\n<!-- wp:image $attrs --><figure class=\"wp-block-image\"><img src=\"$src\" alt=\"" . esc_attr($caption_guess) . "\"/></figure><!-- /wp:image -->\n";
            },
            '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i' => function ($m) {
                $url = $m[1];
                $text = trim(wp_strip_all_tags($m[2]));
                $attrs = json_encode(['url' => $url, 'text' => $text], JSON_UNESCAPED_UNICODE);
                return "\n<!-- wp:buttons -->\n<!-- wp:button $attrs --><div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"$url\">$text</a></div><!-- /wp:button -->\n<!-- /wp:buttons -->\n";
            },
            '/<ul\b[^>]*>(.*?)<\/ul>/is' => function ($m) {
                $items = '';
                if (preg_match_all('/<li\b[^>]*>(.*?)<\/li>/i', $m[1], $lis)) {
                    foreach ($lis[1] as $li) {
                        $items .= '<li>' . trim(wp_strip_all_tags($li)) . '</li>';
                    }
                }
                return "\n<!-- wp:list --><ul class=\"wp-block-list\">$items</ul><!-- /wp:list -->\n";
            },
            '/<ol\b[^>]*>(.*?)<\/ol>/is' => function ($m) {
                $items = '';
                if (preg_match_all('/<li\b[^>]*>(.*?)<\/li>/i', $m[1], $lis)) {
                    foreach ($lis[1] as $li) {
                        $items .= '<li>' . trim(wp_strip_all_tags($li)) . '</li>';
                    }
                }
                return "\n<!-- wp:list {\"ordered\":true} --><ol class=\"wp-block-list\">$items</ol><!-- /wp:list -->\n";
            },
        ];

        $transformed = preg_replace_callback_array($patterns, $html);
        return $transformed;
    }

    public static function auto_transform_html($block_name, $attrs, $inner_html) {
        switch ($block_name) {
            case 'core/heading':
                $level = isset($attrs['level']) ? (int) $attrs['level'] : 2;
                $content = isset($attrs['content']) ? esc_html($attrs['content']) : '';
                $tag = "h$level";
                return "<$tag>" . wp_kses_post($content) . "</$tag>";

            case 'core/paragraph':
                $content = isset($attrs['content']) ? wp_kses_post($attrs['content']) : '';
                return '<p>' . $content . '</p>';

            case 'core/list':
                $ordered = !empty($attrs['ordered']);
                $tag = $ordered ? 'ol' : 'ul';
                $items = '';
                if (preg_match_all('/<li>(.*?)<\/li>/s', $inner_html, $matches)) {
                    foreach ($matches[1] as $li) {
                        $items .= '<li>' . wp_kses_post(trim($li)) . '</li>';
                    }
                }
                return "<$tag class=\"wp-block-list\">$items</$tag>";

            case 'core/button':
                $url = isset($attrs['url']) ? esc_url($attrs['url']) : '#';
                $text = isset($attrs['text']) ? esc_html($attrs['text']) : 'Button';
                return '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . $url . '">' . $text . '</a></div>';

            case 'core/image':
                $url = isset($attrs['url']) ? esc_url($attrs['url']) : '';
                $alt = isset($attrs['alt']) ? esc_attr($attrs['alt']) : '';
                $id = isset($attrs['id']) ? (int) $attrs['id'] : 0;
                $img = '<img src="' . $url . '" alt="' . $alt . '" class="wp-image-' . $id . '"/>';
                return '<figure class="wp-block-image">' . $img . '</figure>';

            default:
                return $inner_html;
        }
    }

    public static function tags_from_block_name($name) {
        $map = [
            'core/heading' => ['h2'],
            'core/paragraph' => ['p'],
            'core/image' => ['figure'],
            'core/button' => ['div'],
            'core/buttons' => ['div'],
            'core/list' => ['ul', 'ol'],
            'core/group' => ['div'],
            'core/columns' => ['div'],
            'core/column' => ['div'],
            'core/quote' => ['blockquote'],
            'core/code' => ['pre'],
            'core/preformatted' => ['pre'],
            'core/pullquote' => ['figure'],
            'core/table' => ['figure'],
            'core/cover' => ['div'],
            'core/media-text' => ['div'],
            'core/video' => ['figure'],
            'core/file' => ['div'],
            'core/gallery' => ['figure'],
            'core/audio' => ['figure'],
        ];
        return isset($map[$name]) ? $map[$name] : ['div'];
    }
}
