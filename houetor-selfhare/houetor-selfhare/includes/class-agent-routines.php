<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_Routines {

    const CRON_HOOK = 'houetor_selfhare_cron';

    public static function init() {
        add_action(self::CRON_HOOK, [__CLASS__, 'run_scheduled']);
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'houetor_selfhare_weekly', self::CRON_HOOK);
        }
    }

    public static function clear_schedule() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public static function run_scheduled() {
        if (!Houetor_SelfHare_License::is_active()) return;

        global $wpdb;
        $routines = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}houetor_selfhare_routines WHERE active = 1"
        );

        foreach ($routines as $routine) {
            self::execute_routine($routine);
            $wpdb->update(
                "{$wpdb->prefix}houetor_selfhare_routines",
                ['last_run' => current_time('mysql')],
                ['id' => $routine->id]
            );
        }

        $auto_routines = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}houetor_selfhare_routines WHERE trigger_type = 'audit_hebdo' AND active = 1 LIMIT 1"
        );

        if ($auto_routines) {
            self::send_audit_message();
        }
    }

    private static function send_relay($message, $context) {
        $response = wp_remote_post(HOUETOR_SELFHARE_RELAY_URL, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'license_key' => Houetor_SelfHare_License::get_license()['license_key'],
                'message' => $message,
                'site_context' => $context ?: new stdClass(),
                'manifest_schema' => Houetor_SelfHare_Onboarding::build_manifest(),
                'last_tool_result' => null,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return;
        if (wp_remote_retrieve_response_code($response) !== 200) return;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['tool_call']) || !is_array($body['tool_call'])) return;

        $tool_call = $body['tool_call'];
        $tool_call['internal'] = true;
        Houetor_SelfHare_Dispatch::execute($tool_call, Houetor_SelfHare_Onboarding::build_manifest());
    }

    private static function execute_routine($routine) {
        $params = json_decode($routine->params, true) ?: [];
        $context = Houetor_SelfHare_Memory::get_context();

        $message = "Tâche planifiée : {$routine->action_type}";
        if (!empty($params)) {
            $message .= ' — ' . wp_json_encode($params);
        }

        self::send_relay($message, $context);
    }

    private static function send_audit_message() {
        $pages = get_pages();
        $drafts = 0;
        foreach ($pages as $p) {
            if ($p->post_status === 'draft') $drafts++;
        }

        $message = "Audit hebdomadaire du site : " . count($pages) . " pages, $drafts brouillons.";
        self::send_relay($message, Houetor_SelfHare_Memory::get_context());
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        if (!Houetor_SelfHare_License::is_active()) {
            echo '<div class="wrap"><h1>Routines SelfHare</h1><div class="notice notice-error"><p>Veuillez d\'abord activer votre licence dans SelfHare → Activation.</p></div></div>';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_routine'])) {
            check_admin_referer('houetor_selfhare_routines');
            global $wpdb;
            $wpdb->insert("{$wpdb->prefix}houetor_selfhare_routines", [
                'trigger_type' => sanitize_text_field($_POST['trigger_type']),
                'action_type' => sanitize_text_field($_POST['action_type']),
                'params' => wp_json_encode(['message' => sanitize_text_field($_POST['params'] ?? '')]),
                'active' => 1,
            ]);
            echo '<div class="notice notice-success"><p>Routine ajoutée.</p></div>';
        }

        global $wpdb;
        $routines = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}houetor_selfhare_routines ORDER BY id DESC");

        ?>
        <div class="wrap">
            <h1>Routines SelfHare</h1>

            <form method="post" style="max-width:600px;margin-bottom:30px;">
                <?php wp_nonce_field('houetor_selfhare_routines'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="trigger_type">Déclencheur</label></th>
                        <td>
                            <select name="trigger_type" id="trigger_type">
                                <option value="audit_hebdo">Audit hebdomadaire</option>
                                <option value="lundi">Chaque lundi</option>
                                <option value="personnalise">Personnalisé</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="action_type">Action</label></th>
                        <td>
                            <input type="text" name="action_type" id="action_type" class="regular-text" placeholder="Ex : verifier_pages_vides" required />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="params">Message personnalisé</label></th>
                        <td>
                            <textarea name="params" id="params" class="large-text" rows="3" placeholder="Ex : Vérifie les pages vides et fais un rapport"></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Ajouter la routine', 'primary', 'add_routine'); ?>
            </form>

            <h2>Routines existantes</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Déclencheur</th>
                        <th>Action</th>
                        <th>Active</th>
                        <th>Dernière exécution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($routines)): ?>
                        <tr><td colspan="5">Aucune routine pour le moment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($routines as $r): ?>
                            <tr>
                                <td><?php echo esc_html($r->id); ?></td>
                                <td><?php echo esc_html($r->trigger_type); ?></td>
                                <td><?php echo esc_html($r->action_type); ?></td>
                                <td><?php echo $r->active ? 'Oui' : 'Non'; ?></td>
                                <td><?php echo esc_html($r->last_run ?: 'Jamais'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

add_filter('cron_schedules', function ($schedules) {
    $schedules['houetor_selfhare_weekly'] = [
        'interval' => WEEK_IN_SECONDS,
        'display' => 'Une fois par semaine (SelfHare)',
    ];
    return $schedules;
});

add_action('init', [Houetor_SelfHare_Routines::class, 'init']);
add_action('admin_init', [Houetor_SelfHare_Routines::class, 'schedule']);
