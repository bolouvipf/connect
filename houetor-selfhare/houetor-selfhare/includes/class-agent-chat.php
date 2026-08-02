<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_Chat {

    const ACTIVATED_AT_OPTION = 'houetor_selfhare_activated_at';
    const AUTO_MODE_OPTION = 'houetor_selfhare_auto_mode';

    public static function can_skip_preview() {
        $activated_at = get_option(self::ACTIVATED_AT_OPTION, 0);
        $auto_mode = get_option(self::AUTO_MODE_OPTION, false);
        if (!$auto_mode) return false;
        if (time() - intval($activated_at) < DAY_IN_SECONDS) return false;
        return true;
    }

    public static function render_page() {
        if (!current_user_can('houetor_selfhare_agent')) {
            wp_die('Accès refusé.');
        }

        $license = Houetor_SelfHare_License::get_license();
        if (!$license || !Houetor_SelfHare_License::is_active()) {
            echo '<div class="wrap"><h1>Assistant SelfHare</h1><div class="notice notice-error"><p>Veuillez d\'abord activer votre licence dans SelfHare → Activation.</p></div></div>';
            return;
        }

        $activated_at = get_option(self::ACTIVATED_AT_OPTION, 0);
        $is_grace = $activated_at && (time() - intval($activated_at)) < DAY_IN_SECONDS;

        ?>
        <div class="wrap">
            <h1>Assistant SelfHare</h1>
            <?php if ($is_grace): ?>
                <div class="notice notice-info">
                    <p>🔒 Mode aperçu obligatoire pendant les premières 24h suivant l'activation. Chaque action doit être confirmée avant exécution.</p>
                </div>
            <?php endif; ?>
            <p>Choisissez une action et une page, puis décrivez ce que vous voulez faire.</p>

            <div id="houetor-selfhare-chat">
                <div id="houetor-selfhare-toolbar">
                    <div>
                        <label>Action</label>
                        <select id="houetor-selfhare-action">
                            <option value="">-- Action automatique --</option>
                            <option value="inject_page">Injecter du contenu</option>
                            <option value="update_pages">Modifier une page</option>
                            <option value="delete_block">Supprimer un bloc injecté</option>
                        </select>
                    </div>
                    <div>
                        <label>Page</label>
                        <select id="houetor-selfhare-page">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach (Houetor_SelfHare_Page_Cache::get() as $p) : ?>
                            <option value="<?php echo esc_attr($p['id']); ?>"><?php echo esc_html($p['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="houetor-selfhare-messages">
                    <div class="houetor-message system">
                        Bonjour ! Choisissez une action et une page ci-dessus, puis décrivez ce que vous voulez faire.
                    </div>
                </div>

                <form id="houetor-selfhare-form">
                    <div class="form-row">
                        <input type="text" id="houetor-selfhare-input" placeholder="Ex : Ajoute un header avec le logo" required />
                        <label id="houetor-selfhare-upload-label" title="Joindre une image">
                            📎
                            <input type="file" id="houetor-selfhare-file-input" accept="image/jpeg,image/png,image/webp" />
                        </label>
                        <button type="submit" class="button button-primary">Envoyer</button>
                    </div>
                    <div id="houetor-selfhare-file-preview">
                        <span id="houetor-selfhare-file-name"></span>
                        <button type="button" id="houetor-selfhare-file-remove">✕</button>
                    </div>
                </form>

                <div id="houetor-selfhare-tool">
                    <h3>Action proposée</h3>
                    <pre id="houetor-selfhare-tool-preview"></pre>
                    <div id="houetor-selfhare-preview-summary"></div>
                    <div>
                        <button id="houetor-selfhare-execute" class="button button-primary">Exécuter</button>
                        <button id="houetor-selfhare-confirm" class="button button-primary">Confirmer l'exécution</button>
                        <button id="houetor-selfhare-dismiss" class="button">Annuler</button>
                    </div>
                    <div id="houetor-selfhare-execute-result"></div>
                </div>

                <div id="houetor-selfhare-loading">
                    <span class="spinner is-active"></span> Réflexion en cours…
                </div>
            </div>
        </div>

        <div id="houetor-selfhare-modal">
            <div>
                <h3>Confirmer l'action</h3>
                <p id="houetor-selfhare-modal-summary"></p>
                <div id="houetor-selfhare-modal-diff"></div>
                <div class="modal-actions">
                    <button id="houetor-selfhare-modal-cancel" class="button">Annuler</button>
                    <button id="houetor-selfhare-modal-confirm" class="button button-primary">Confirmer et exécuter</button>
                </div>
            </div>
        </div>
        <?php
    }
}

add_action('wp_ajax_houetor_selfhare_chat', 'houetor_selfhare_chat_ajax');
function houetor_selfhare_chat_ajax() {
    check_ajax_referer('houetor_selfhare_nonce');
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error('Accès refusé', 403);
    }
    if (!current_user_can('houetor_selfhare_agent')) {
        wp_send_json_error('Accès refusé.');
    }

    $license = Houetor_SelfHare_License::get_license();
    if (!$license || !Houetor_SelfHare_License::is_active()) {
        wp_send_json_error('Licence inactive.');
    }

    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    if (empty($message)) {
        wp_send_json_error('Message vide.');
    }

    $last_tool_result = null;
    if (!empty($_POST['last_tool_result'])) {
        $decoded = json_decode(stripslashes($_POST['last_tool_result']), true);
        if (is_array($decoded)) $last_tool_result = $decoded;
    }

    $attachment_url = isset($_POST['attachment_url']) ? esc_url_raw($_POST['attachment_url']) : '';
    $selected_action = isset($_POST['selected_action']) ? sanitize_text_field($_POST['selected_action']) : '';
    $selected_page = isset($_POST['selected_page']) ? intval($_POST['selected_page']) : 0;
    $last_tool_name = isset($_POST['last_tool_name']) ? sanitize_text_field($_POST['last_tool_name']) : '';

    $site_context = Houetor_SelfHare_Memory::get_context();
    if (!is_array($site_context)) $site_context = [];
    if ($attachment_url) $site_context['attachment_url'] = $attachment_url;
    if ($selected_action) $site_context['selected_action'] = $selected_action;
    if ($selected_page) $site_context['selected_page'] = $selected_page;
    if ($last_tool_name) $site_context['last_tool_name'] = $last_tool_name;
    $manifest_schema = Houetor_SelfHare_Onboarding::build_manifest();

    $response = wp_remote_post(HOUETOR_SELFHARE_RELAY_URL, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'license_key' => $license['license_key'],
            'message' => $message,
            'site_context' => $site_context ?: new stdClass(),
            'manifest_schema' => $manifest_schema,
            'last_tool_result' => $last_tool_result,
        ]),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Erreur de communication avec le relay : ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (wp_remote_retrieve_response_code($response) !== 200 || isset($body['error'])) {
        $error_code = isset($body['code']) ? $body['code'] : '';
        $hint = '';
        if ($error_code && class_exists('Houetor_SelfHare_Error_Translator')) {
            $info = Houetor_SelfHare_Error_Translator::translate($error_code);
            $hint = $info['hint'];
        }
        $reply = $hint ? "Erreur : {$body['error']}. $hint" : ($body['error'] ?? 'Erreur inconnue du relay');
        wp_send_json_error($reply);
    }

    wp_send_json_success([
        'reply' => $body['reply'] ?? '',
        'tool_call' => $body['tool_call'] ?? null,
    ]);
}

add_action('wp_ajax_houetor_selfhare_preview', 'houetor_selfhare_preview_ajax');
function houetor_selfhare_preview_ajax() {
    check_ajax_referer('houetor_selfhare_nonce');
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error('Accès refusé', 403);
    }
    if (!current_user_can('houetor_selfhare_agent')) {
        wp_send_json_error('Accès refusé.');
    }

    $tool_call_json = isset($_POST['tool_call']) ? stripslashes($_POST['tool_call']) : '';
    $tool_call = json_decode($tool_call_json, true);
    if (!$tool_call || empty($tool_call['name'])) {
        wp_send_json_error('tool_call invalide.');
    }

    $manifest_schema = Houetor_SelfHare_Onboarding::build_manifest();
    $result = Houetor_SelfHare_Dispatch::preview($tool_call, $manifest_schema);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message'] ?? 'Échec du calcul de l\'aperçu.');
    }
}

add_action('wp_ajax_houetor_selfhare_dispatch', 'houetor_selfhare_dispatch_ajax');
function houetor_selfhare_dispatch_ajax() {
    check_ajax_referer('houetor_selfhare_nonce');
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error('Accès refusé', 403);
    }
    if (!current_user_can('houetor_selfhare_agent')) {
        wp_send_json_error('Accès refusé.');
    }

    $tool_call_json = isset($_POST['tool_call']) ? stripslashes($_POST['tool_call']) : '';
    $tool_call = json_decode($tool_call_json, true);
    if (!$tool_call || empty($tool_call['name'])) {
        wp_send_json_error('tool_call invalide.');
    }

    $manifest_schema = Houetor_SelfHare_Onboarding::build_manifest();
    $result = Houetor_SelfHare_Dispatch::execute($tool_call, $manifest_schema);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message'] ?? 'Échec de l\'exécution.');
    }
}

add_action('wp_ajax_houetor_selfhare_upload', 'houetor_selfhare_upload_ajax');
function houetor_selfhare_upload_ajax() {
    check_ajax_referer('houetor_selfhare_nonce');
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error('Accès refusé', 403);
    }
    if (!current_user_can('houetor_selfhare_agent')) {
        wp_send_json_error('Accès refusé.');
    }

    if (empty($_FILES['file'])) {
        wp_send_json_error('Aucun fichier reçu.');
    }

    $file = $_FILES['file'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed_types, true)) {
        wp_send_json_error('Format non supporté. Utilisez JPG, PNG ou WebP.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        wp_send_json_error('Image trop volumineuse. Maximum 5 Mo.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_sideload([
        'name'     => $file['name'],
        'tmp_name' => $file['tmp_name'],
        'error'    => $file['error'],
    ], 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error('Erreur upload : ' . $attachment_id->get_error_message());
    }

    $url = wp_get_attachment_url($attachment_id);
    wp_send_json_success([
        'id'  => $attachment_id,
        'url' => $url,
        'filename' => basename($url),
    ]);
}
