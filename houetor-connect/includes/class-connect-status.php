<?php
defined('ABSPATH') || exit;

class Houetor_Connect {

    const TRANSIENT_KEY = 'houetor_connect_remote_status';
    const CACHE_TTL = 900; // 15 minutes

    /**
     * Vérification rapide locale : statut WordPress + token non vide.
     * Consulte aussi le cache distant : si 'desync' dans le transient,
     * ou si l'option locale vaut 'desync', retourne false.
     * Ne DÉCLENCHE PAS d'appel API — c'est le rôle de refresh_remote_status().
     */
    public static function is_connected() {
        $status = get_option('houetor_connection_status', '');
        $token  = get_option('hwc_code', '');

        if ($status !== 'active' || empty($token)) {
            return false;
        }

        $remote = get_transient(self::TRANSIENT_KEY);

        if ($remote === 'desync') {
            return false;
        }

        return true;
    }

    /**
     * Retourne le libellé d'état pour l'écran Réglages :
     * 'active', 'desync', 'disconnected', ou 'unknown'.
     */
    public static function get_status_label() {
        $local = get_option('houetor_connection_status', '');

        if ($local === 'desync') {
            return 'desync';
        }

        if ($local !== 'active') {
            return 'disconnected';
        }

        $remote = get_transient(self::TRANSIENT_KEY);

        if ($remote === 'desync') {
            return 'desync';
        }

        return 'active';
    }

    /**
     * Recharge le statut distant depuis Supabase via GET /status.
     * Utilise un transient de 15 min pour éviter les appels répétés.
     * Appelé uniquement depuis render_page() (écran Réglages admin).
     *
     * Résultats possibles :
     *   - connected + matches_this_site → transient 'verified', local 'active'
     *   - connected + !matches_this_site → transient 'desync', local 'desync'
     *   - !connected → transient 'not_found', local ''
     *   - échec réseau → ne modifie rien
     */
    public static function refresh_remote_status() {
        $token = get_option('hwc_code', '');
        $local = get_option('houetor_connection_status', '');

        if (empty($token) || ($local !== 'active' && $local !== 'desync')) {
            return;
        }

        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached !== false && $cached !== 'desync_forcing') {
            return;
        }

        $site_url = rawurlencode(get_site_url());
        $token_enc = rawurlencode($token);
        $url = "https://houetor.com/api/connect-site/status?token={$token_enc}&site_url={$site_url}";

        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            if ($local === 'desync') {
                set_transient(self::TRANSIENT_KEY, 'desync', self::CACHE_TTL);
            }
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || !is_array($body)) {
            return;
        }

        if (!empty($body['connected']) && !empty($body['matches_this_site'])) {
            set_transient(self::TRANSIENT_KEY, 'verified', self::CACHE_TTL);
            if (get_option('houetor_connection_status') !== 'active') {
                update_option('houetor_connection_status', 'active');
            }
            delete_option('houetor_desync_url');
        } elseif (!empty($body['connected']) && empty($body['matches_this_site'])) {
            set_transient(self::TRANSIENT_KEY, 'desync', self::CACHE_TTL);
            update_option('houetor_connection_status', 'desync');
            update_option('houetor_desync_url', esc_url($body['actual_url']));
        } else {
            set_transient(self::TRANSIENT_KEY, 'not_found', self::CACHE_TTL);
            update_option('houetor_connection_status', '');
            delete_option('houetor_desync_url');
        }
    }
}
