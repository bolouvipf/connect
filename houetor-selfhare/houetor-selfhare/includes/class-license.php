<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_License {

    const OPTION_KEY = 'houetor_selfhare_license';

    public static function get_license() {
        $license = get_option(self::OPTION_KEY, null);
        if ($license && isset($license['license_key'])) {
            $license['license_key'] = self::decrypt($license['license_key']);
        }
        return $license;
    }

    public static function is_active() {
        $license = self::get_license();
        return $license && isset($license['status']) && $license['status'] === 'active';
    }

    public static function validate($license_key) {
        $response = wp_remote_post(HOUETOR_SELFHARE_VALIDATE_URL, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['license_key' => $license_key]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return ['valid' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200 || empty($body['valid'])) {
            return ['valid' => false, 'status' => $body['status'] ?? 'invalid'];
        }

        return [
            'valid' => true,
            'license_key' => $license_key,
            'plan' => $body['plan'],
            'status' => $body['status'],
        ];
    }

    public static function save_license($data) {
        if (isset($data['license_key'])) {
            $data['license_key'] = self::encrypt($data['license_key']);
        }
        update_option(self::OPTION_KEY, $data);
    }

    private static function cipher_key() {
        return substr(hash('sha256', wp_salt('auth') . '|houetor-selfhare'), 0, 32);
    }

    private static function encrypt($plain) {
        if ($plain === null || $plain === '') return $plain;
        if (!function_exists('openssl_encrypt')) return $plain;
        $iv = random_bytes(16);
        return base64_encode($iv) . ':' . base64_encode(openssl_encrypt($plain, 'AES-256-CBC', self::cipher_key(), 0, $iv));
    }

    private static function decrypt($cipher) {
        if (!is_string($cipher) || strpos($cipher, ':') === false) return $cipher;
        if (!function_exists('openssl_decrypt')) return $cipher;
        $parts = explode(':', $cipher, 2);
        $iv = base64_decode($parts[0]);
        $data = base64_decode($parts[1]);
        if ($iv === false || $data === false) return $cipher;
        $plain = openssl_decrypt($data, 'AES-256-CBC', self::cipher_key(), 0, $iv);
        return $plain === false ? $cipher : $plain;
    }

    public static function render_page() {
        $license = self::get_license();
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['license_key'])) {
            check_admin_referer('houetor_selfhare_license');
            $result = self::validate(sanitize_text_field($_POST['license_key']));
            if ($result['valid']) {
                self::save_license($result);
                $message = '<div class="notice notice-success"><p>Licence activée avec succès !</p></div>';
            } else {
                $message = '<div class="notice notice-error"><p>Erreur : ' . esc_html($result['error'] ?? $result['status'] ?? 'clé invalide') . '</p></div>';
            }
        }

        ?>
        <div class="wrap">
            <div style="text-align:center;margin-bottom:24px;">
                <svg width="100" height="100" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M60 10 L10 55 L25 55 L25 100 L95 100 L95 55 L110 55 Z" stroke="#7A9E8E" stroke-width="2.5" fill="none" stroke-linejoin="round"/>
                    <line x1="25" y1="100" x2="25" y2="55" stroke="#7A9E8E" stroke-width="2.5"/>
                    <line x1="95" y1="100" x2="95" y2="55" stroke="#7A9E8E" stroke-width="2.5"/>
                    <ellipse cx="60" cy="55" rx="20" ry="12" stroke="#FB923C" stroke-width="2.5" fill="none"/>
                    <line x1="60" y1="55" x2="60" y2="38" stroke="#F0EDE6" stroke-width="2" stroke-linecap="round"/>
                    <line x1="60" y1="55" x2="60" y2="72" stroke="#F0EDE6" stroke-width="2" stroke-linecap="round"/>
                    <line x1="60" y1="55" x2="46" y2="47" stroke="#F0EDE6" stroke-width="2" stroke-linecap="round"/>
                    <line x1="60" y1="55" x2="74" y2="63" stroke="#F0EDE6" stroke-width="2" stroke-linecap="round"/>
                    <line x1="60" y1="55" x2="46" y2="63" stroke="#F0EDE6" stroke-width="2" stroke-linecap="round"/>
                    <line x1="60" y1="55" x2="74" y2="47" stroke="#F0EDE6" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="60" cy="55" r="5" fill="#FB923C"/>
                </svg>
            </div>
            <h1>Activation SelfHare</h1>
            <?php echo $message; ?>

            <?php if ($license && $license['status'] === 'active'): ?>
                <div class="notice notice-success">
                    <p>
                        <strong>Licence active</strong> &mdash;
                        Plan : <?php echo esc_html($license['plan']); ?> &mdash;
                        Clé : <?php echo esc_html(substr($license['license_key'], 0, 20) . '…'); ?>
                    </p>
                </div>
            <?php elseif ($license && $license['status'] === 'past_due'): ?>
                <div class="notice notice-warning">
                    <p>Votre licence est en retard de paiement. Veuillez mettre à jour vos informations de facturation.</p>
                </div>
            <?php endif; ?>

            <form method="post" style="max-width:600px;margin-top:20px;">
                <?php wp_nonce_field('houetor_selfhare_license'); ?>
                <label for="license_key" style="display:block;margin-bottom:8px;font-weight:600;">
                    Clé de licence SelfHare
                </label>
                <input
                    type="text"
                    name="license_key"
                    id="license_key"
                    required
                    pattern="SLH-.+"
                    style="width:100%;padding:10px;font-size:14px;border:1px solid #ccc;border-radius:4px;"
                    placeholder="SLH-starter-…"
                    value="<?php echo esc_attr($license['license_key'] ?? ''); ?>"
                />
                <p class="description" style="margin-top:4px;">
                    Collez votre clé de licence reçue par email.
                </p>
                <?php submit_button('Activer la licence'); ?>
            </form>
        </div>
        <?php
    }
}
