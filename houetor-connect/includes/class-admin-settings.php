<?php
defined('ABSPATH') || exit;

class HWC_Admin_Settings {

    public function redirect_legacy_url() {
        global $pagenow;
        if ($pagenow === 'options-general.php' && isset($_GET['page']) && $_GET['page'] === 'houetor-connect') {
            wp_safe_redirect(admin_url('admin.php?page=houetor-connect'));
            exit;
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'HOUETOR Connect',
            'HOUETOR',
            'manage_options',
            'houetor-connect',
            array($this, 'render_page'),
            'dashicons-admin-plugins',
            30
        );

        add_submenu_page(
            'houetor-connect',
            'Connexion',
            'Connexion',
            'manage_options',
            'houetor-connect',
            array($this, 'render_page')
        );

        if (Houetor_Connect::is_connected()) {
            add_submenu_page(
                'houetor-connect',
                'Annonces',
                'Annonces',
                'manage_options',
                'houetor-connect-annonces',
                array($this, 'render_annonces_page')
            );

            add_submenu_page(
                'houetor-connect',
                'Produits',
                'Produits',
                'manage_options',
                'houetor-connect-produits',
                array($this, 'render_produits_page')
            );

            add_submenu_page(
                'houetor-connect',
                'Formations',
                'Formations',
                'manage_options',
                'houetor-connect-formations',
                array($this, 'render_formations_page')
            );

            add_submenu_page(
                'houetor-connect',
                'Commandes',
                'Commandes',
                'manage_options',
                'houetor-connect-commandes',
                array($this, 'render_commandes_page')
            );
        }
    }

    public function register_settings() {
        register_setting('hwc_settings_group', 'hwc_code');
        register_setting('hwc_settings_group', 'hwc_layout');
        register_setting('hwc_settings_group', 'hwc_items_count');
        register_setting('hwc_settings_group', 'hwc_injections');
        register_setting('hwc_settings_group', 'hwc_token');
        register_setting('hwc_settings_group', 'houetor_connection_status');
        register_setting('hwc_settings_group', 'houetor_site_token');
        register_setting('hwc_settings_group', 'houetor_site_url');
        register_setting('hwc_settings_group', 'houetor_desync_url');
    }

    public function handle_connect() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        check_admin_referer('hwc_connect_action');

        $hwt_token = isset($_POST['hwt_token']) ? sanitize_text_field(wp_unslash($_POST['hwt_token'])) : '';
        if (empty($hwt_token)) {
            add_settings_error('hwc_messages', 'hwc_connect_error', 'Veuillez entrer votre code HWT.', 'error');
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_redirect(admin_url('admin.php?page=houetor-connect'));
            exit;
        }

        $site_url = get_site_url();
        $site_token = get_option('hwc_token', '');
        if (empty($site_token)) {
            $site_token = wp_generate_password(32, false);
            update_option('hwc_token', $site_token);
        }

        $response = wp_remote_post('https://houetor.com/api/connect-site', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array(
                'hwt_token'  => $hwt_token,
                'site_url'   => $site_url,
                'site_token' => $site_token,
            )),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            add_settings_error('hwc_messages', 'hwc_connect_error', 'Erreur de connexion au serveur HOUETOR : ' . $response->get_error_message(), 'error');
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_redirect(admin_url('admin.php?page=houetor-connect'));
            exit;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['success']) && $data['success']) {
            update_option('houetor_connection_status', 'active');
            update_option('houetor_site_token', $site_token);
            update_option('houetor_site_url', $site_url);
            update_option('hwc_code', $hwt_token);
            add_settings_error('hwc_messages', 'hwc_connect_success', '✓ Connecté au compte HOUETOR.', 'success');
        } elseif ($status_code === 409 && isset($data['error']) && $data['error'] === 'SITE_ALREADY_CONNECTED') {
            $existing_url = isset($data['existing_url']) ? esc_url($data['existing_url']) : '';
            $msg = 'Ce site est déjà connecté à un autre compte HOUETOR. Déconnectez-le depuis l\'autre compte avant de le connecter ici.';
            if (!empty($existing_url)) {
                $msg .= ' Site actuel : <strong>' . $existing_url . '</strong>.';
            }
            add_settings_error('hwc_messages', 'hwc_connect_site_conflict', $msg, 'error');
        } elseif ($status_code === 409 && isset($data['error']) && $data['error'] === 'DUPLICATE_CONNECTION') {
            $msg = 'Conflit : ce token de site est déjà enregistré. Contactez le support si le problème persiste.';
            add_settings_error('hwc_messages', 'hwc_connect_duplicate', $msg, 'error');
        } elseif ($status_code === 409 && isset($data['error']) && $data['error'] === 'ACCOUNT_LIMIT_REACHED') {
            $existing_url = isset($data['existing_url']) ? esc_url($data['existing_url']) : '';
            $msg = sprintf(
                'Ce compte est limité à un seul site connecté. Vous êtes déjà connecté au site <strong>%s</strong>. Déconnectez-vous depuis ce site avant d\'en connecter un autre.',
                $existing_url
            );
            add_settings_error('hwc_messages', 'hwc_limit_reached', $msg, 'error');
        } else {
            $error_msg = isset($data['error']) ? $data['error'] : 'Erreur de connexion (code ' . $status_code . ').';
            add_settings_error('hwc_messages', 'hwc_connect_error', $error_msg, 'error');
        }

        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=houetor-connect'));
        exit;
    }

    public function handle_disconnect() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        check_admin_referer('hwc_disconnect_action');

        $site_url = get_site_url();
        $hwt_token = get_option('hwc_code', '');

        if (!empty($hwt_token)) {
            wp_remote_post('https://houetor.com/api/connect-site', array(
                'method'  => 'DELETE',
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode(array(
                    'hwt_token' => $hwt_token,
                    'site_url'  => $site_url,
                )),
                'timeout' => 15,
            ));
        }

        delete_option('houetor_connection_status');
        delete_option('houetor_site_token');
        delete_option('houetor_site_url');

        add_settings_error('hwc_messages', 'hwc_disconnect_success', 'Déconnecté du compte HOUETOR.', 'success');

        wp_redirect(admin_url('admin.php?page=houetor-connect'));
        exit;
    }

    public function handle_reset_desync() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        check_admin_referer('hwc_reset_desync_action');

        delete_option('houetor_connection_status');
        delete_option('houetor_site_token');
        delete_option('houetor_site_url');
        delete_option('houetor_desync_url');
        delete_transient(Houetor_Connect::TRANSIENT_KEY);

        add_settings_error('hwc_messages', 'hwc_reset_desync_success', 'État désynchronisé effacé. Vous pouvez vous reconnecter.', 'success');

        wp_redirect(admin_url('admin.php?page=houetor-connect'));
        exit;
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'houetor-connect') === false) {
            return;
        }
        wp_enqueue_style('hwc-admin', HWC_PLUGIN_URL . 'assets/admin.css', array(), HWC_VERSION);
    }

    public function render_page() {
        Houetor_Connect::refresh_remote_status();

        $code    = get_option('hwc_code', '');
        $layout  = get_option('hwc_layout', 'grid');
        $count   = get_option('hwc_items_count', 12);
        $token   = get_option('hwc_token', '');
        $injections = get_option('hwc_injections', array());

        if (!is_array($injections)) {
            $injections = array();
        }

        $parser = new HWT_Parser($code);
        $profil = $parser->get_profil();
        $uuid   = $parser->get_uuid();
        $modules = $parser->get_modules();
        $valid  = $parser->is_valid();

        $pages = get_pages(array('number' => 50));
        $profile_labels = array(
            'ONG'       => 'ONG',
            'BOUTIQUE'  => 'Boutique',
            'COACH'     => 'Coach',
            'CM'        => 'CM',
            'MARKETING' => 'Marketing',
        );
        ?>
        <div class="wrap">
            <h1>Houetor Connect</h1>

            <?php
            $stored = get_transient('settings_errors');
            if (is_array($stored)) {
                foreach ($stored as $err) {
                    add_settings_error($err['setting'], $err['code'], $err['message'], $err['type']);
                }
                delete_transient('settings_errors');
            }
            settings_errors('hwc_messages'); ?>

            <div class="hwc-admin-grid">
                <div class="hwc-admin-card">
                    <h2>Code HWT</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('hwc_settings_group'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="hwc_code">Code HWT</label>
                                </th>
                                <td>
                                    <input type="text" id="hwc_code" name="hwc_code"
                                           value="<?php echo esc_attr($code); ?>"
                                           class="regular-text"
                                           placeholder="HWT-ONG-xxxxx" />
                                    <p class="description">
                                        Format : HWT-{PROFIL}-{uuid}
                                    </p>
                                </td>
                            </tr>
                            <?php if (!empty($code)) : ?>
                            <tr>
                                <th scope="row">Statut</th>
                                <td>
                                    <?php if ($valid) : ?>
                                        <span class="hwc-badge hwc-badge-valid">Valide</span>
                                        <span class="hwc-badge hwc-badge-profil">
                                            <?php echo esc_html($profile_labels[$profil]); ?>
                                        </span>
                                        <p>UUID : <code><?php echo esc_html($uuid); ?></code></p>
                                        <p>Modules : <?php echo esc_html(implode(', ', $modules)); ?></p>
                                    <?php else : ?>
                                        <span class="hwc-badge hwc-badge-invalid">Invalide</span>
                                        <p>Le code fourni ne correspond pas au format attendu.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        <?php submit_button('Enregistrer le code'); ?>
                    </form>
                </div>

                <div class="hwc-admin-card">
                    <h2>Connexion HOUETOR</h2>
                    <?php
                    $status_label = Houetor_Connect::get_status_label();
                    $stored_hwt = get_option('hwc_code', '');
                    $stored_site_url = get_option('houetor_site_url', '');
                    $desync_url = get_option('houetor_desync_url', '');
                    ?>
                    <?php if ($status_label === 'desync') : ?>
                        <p style="color: #d63638; font-weight: 600; font-size: 15px;">
                            &#9888; Désynchronisé
                        </p>
                        <p>
                            Ce token HWT est maintenant associé à un autre site :
                            <strong><code><?php echo esc_url($desync_url); ?></code></strong>.
                        </p>
                        <p>Reconnectez-vous ou contactez le support si vous n'êtes pas à l'origine de ce changement.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                            <input type="hidden" name="action" value="hwc_disconnect" />
                            <?php wp_nonce_field('hwc_disconnect_action'); ?>
                            <p>
                                <button type="submit" class="button" style="color: #b32d2e; border-color: #b32d2e;">
                                    Déconnecter
                                </button>
                            </p>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                            <input type="hidden" name="action" value="hwc_reset_desync" />
                            <?php wp_nonce_field('hwc_reset_desync_action'); ?>
                            <button type="submit" class="button" style="color: #8B5CF6; border-color: #8B5CF6;">
                                Effacer l'état local
                            </button>
                        </form>
                    <?php elseif (Houetor_Connect::is_connected()) : ?>
                        <p style="color: #46B450; font-weight: 600; font-size: 15px;">
                            &#9989; Connecté au compte HOUETOR
                        </p>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Site</th>
                                <td><code><?php echo esc_url($stored_site_url); ?></code></td>
                            </tr>
                            <tr>
                                <th scope="row">Token</th>
                                <td><code><?php echo esc_html($this->mask_token($stored_hwt)); ?></code></td>
                            </tr>
                        </table>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="hwc_disconnect" />
                            <?php wp_nonce_field('hwc_disconnect_action'); ?>
                            <p>
                                <button type="submit" class="button" style="color: #b32d2e; border-color: #b32d2e;">
                                    Déconnecter
                                </button>
                            </p>
                        </form>
                    <?php else : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="hwc_connect" />
                            <?php wp_nonce_field('hwc_connect_action'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="hwt_token">Code HWT</label>
                                    </th>
                                    <td>
                                        <input type="text" id="hwt_token" name="hwt_token"
                                               value=""
                                               class="regular-text"
                                               placeholder="HWT-{PROFIL}-{UUID}" />
                                        <p class="description">
                                            Entrez le code HWT fourni par HOUETOR pour lier ce site à votre compte.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button('Se connecter', 'primary', 'hwc_connect_submit'); ?>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="hwc-admin-card">
                    <h2>Affichage</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('hwc_settings_group'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Disposition</th>
                                <td>
                                    <select name="hwc_layout">
                                        <option value="grid" <?php selected($layout, 'grid'); ?>>Grille</option>
                                        <option value="list" <?php selected($layout, 'list'); ?>>Liste</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hwc_items_count">Éléments par page</label>
                                </th>
                                <td>
                                    <input type="number" id="hwc_items_count" name="hwc_items_count"
                                           value="<?php echo esc_attr($count); ?>" min="1" max="100" />
                                </td>
                            </tr>
                        </table>
                        <?php submit_button('Enregistrer l\'affichage'); ?>
                    </form>
                </div>

                <div class="hwc-admin-card hwc-card-wide">
                    <h2>Injections de contenu</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('hwc_settings_group'); ?>
                        <table class="hwc-injection-table">
                            <thead>
                                <tr>
                                    <th>Page</th>
                                    <th>Type de contenu</th>
                                    <th>Position</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="hwc-injections-tbody">
                                <?php foreach ($injections as $i => $inj) : ?>
                                <tr>
                                    <td>
                                        <select name="hwc_injections[<?php echo $i; ?>][page_id]">
                                            <option value="">-- Sélectionner --</option>
                                            <?php foreach ($pages as $page) : ?>
                                                <option value="<?php echo $page->ID; ?>"
                                                    <?php selected($inj['page_id'], $page->ID); ?>>
                                                    <?php echo esc_html($page->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="hwc_injections[<?php echo $i; ?>][module]">
                                            <option value="annonces" <?php selected($inj['module'], 'annonces'); ?>>Annonces</option>
                                            <option value="produits" <?php selected($inj['module'], 'produits'); ?>>Produits</option>
                                            <option value="formations" <?php selected($inj['module'], 'formations'); ?>>Formations</option>
                                            <option value="commandes" <?php selected($inj['module'], 'commandes'); ?>>Commandes</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="hwc_injections[<?php echo $i; ?>][position]">
                                            <option value="append" <?php selected($inj['position'], 'append'); ?>>Après le contenu</option>
                                            <option value="prepend" <?php selected($inj['position'], 'prepend'); ?>>Avant le contenu</option>
                                            <option value="replace" <?php selected($inj['position'], 'replace'); ?>>Remplacer</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="button hwc-remove-injection">Supprimer</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="button" id="hwc-add-injection">Ajouter une injection</button>
                        <?php submit_button('Enregistrer les injections'); ?>
                    </form>
                </div>

                <div class="hwc-admin-card hwc-card-wide">
                    <h2>Connexion API</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Token d'accès</th>
                            <td>
                                <?php if (!empty($token)) : ?>
                                <div class="hwc-token-box">
                                    <code id="hwc-token-display"><?php echo esc_html($token); ?></code>
                                    <button type="button" class="button hwc-copy-token"
                                            data-token="<?php echo esc_attr($token); ?>">
                                        Copier
                                    </button>
                                </div>
                                <?php else : ?>
                                <p style="color: #8B5CF6; font-style: italic;">
                                    Non disponible — connectez votre site via le formulaire ci-dessus pour générer un token.
                                </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Endpoints</th>
                            <td>
                                <ul class="hwc-endpoints">
                                    <li><code><?php echo esc_url(rest_url('houetor/v1/pages')); ?></code></li>
                                    <li><code><?php echo esc_url(rest_url('houetor/v1/menus')); ?></code></li>
                                    <li><code><?php echo esc_url(rest_url('houetor/v1/inject')); ?></code></li>
                                </ul>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var injectionIndex = <?php echo count($injections); ?>;

            $('#hwc-add-injection').on('click', function() {
                var html = '<tr>';
                html += '<td><select name="hwc_injections[' + injectionIndex + '][page_id]">';
                html += '<option value="">-- Sélectionner --</option>';
                <?php foreach ($pages as $page) : ?>
                html += '<option value="<?php echo $page->ID; ?>"><?php echo esc_js($page->post_title); ?></option>';
                <?php endforeach; ?>
                html += '</select></td>';
                html += '<td><select name="hwc_injections[' + injectionIndex + '][module]">';
                html += '<option value="annonces">Annonces</option>';
                html += '<option value="produits">Produits</option>';
                html += '<option value="formations">Formations</option>';
                html += '<option value="commandes">Commandes</option>';
                html += '</select></td>';
                html += '<td><select name="hwc_injections[' + injectionIndex + '][position]">';
                html += '<option value="append">Après le contenu</option>';
                html += '<option value="prepend">Avant le contenu</option>';
                html += '<option value="replace">Remplacer</option>';
                html += '</select></td>';
                html += '<td><button type="button" class="button hwc-remove-injection">Supprimer</button></td>';
                html += '</tr>';
                $('#hwc-injections-tbody').append(html);
                injectionIndex++;
            });

            $(document).on('click', '.hwc-remove-injection', function() {
                $(this).closest('tr').remove();
            });

            $('.hwc-copy-token').on('click', function() {
                var token = $(this).data('token');
                var temp = $('<input>');
                $('body').append(temp);
                temp.val(token).select();
                document.execCommand('copy');
                temp.remove();
                $(this).text('Copié !');
                var btn = $(this);
                setTimeout(function() {
                    btn.text('Copier');
                }, 2000);
            });
        });
        </script>
        <?php
    }

    private function mask_token($token) {
        if (empty($token)) {
            return '';
        }
        $parts = explode('-', $token, 3);
        if (count($parts) === 3) {
            $uuid = $parts[2];
            $prefix = substr($uuid, 0, 4);
            $masked = str_repeat('*', max(4, strlen($uuid) - 4));
            return 'HWT-' . strtoupper($parts[1]) . '-' . $prefix . $masked;
        }
        return substr($token, 0, 8) . '****';
    }

    public function render_annonces_page() {
        $this->render_module_page('annonces', 'Annonces');
    }

    public function render_produits_page() {
        $this->render_module_page('produits', 'Produits');
    }

    public function render_formations_page() {
        $this->render_module_page('formations', 'Formations');
    }

    public function render_commandes_page() {
        $this->render_module_page('commandes', 'Commandes');
    }

    private function render_module_page($module, $title) {
        if (!Houetor_Connect::is_connected()) {
            wp_die('Accès refusé. Veuillez d\'abord connecter votre site à HOUETOR.');
        }
        $fetcher = new HWC_API_Fetcher();
        $content = $fetcher->fetch($module);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <?php if (!empty($content)) : ?>
                <?php echo $content; ?>
            <?php else : ?>
                <p>Aucun contenu disponible pour le moment.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}
