<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_Error_Translator {

    const ERROR_MAP = [
        'edit_conflict' => [
            'message' => "Conflit d'édition : le contenu a été modifié entre temps.",
            'hint' => 'Recharge la page et réapplique les modifications.',
            'retryable' => true,
        ],
        'post_not_found' => [
            'message' => 'Contenu introuvable.',
            'hint' => "Vérifie que l'ID de la page ou de l'article est correct.",
            'retryable' => false,
        ],
        'page_not_found' => [
            'message' => 'Page introuvable.',
            'hint' => "Vérifie que l'ID de la page est correct.",
            'retryable' => false,
        ],
        'block_not_found' => [
            'message' => 'Bloc introuvable.',
            'hint' => "La référence du bloc n'existe plus dans cette page.",
            'retryable' => false,
        ],
        'ref_stale' => [
            'message' => 'Référence de bloc obsolète.',
            'hint' => "La référence ne correspond plus à aucun bloc. Relis les blocs de la page pour obtenir les références actuelles.",
            'retryable' => true,
        ],
        'invalid_post_type' => [
            'message' => 'Type de contenu invalide.',
            'hint' => "Utilise un type de contenu reconnu (post, page, product si WooCommerce).",
            'retryable' => false,
        ],
        'invalid_params' => [
            'message' => 'Paramètres invalides.',
            'hint' => "Vérifie les paramètres envoyés à l'action.",
            'retryable' => false,
        ],
        'rate_limit_exceeded' => [
            'message' => 'Limite de taux atteinte.',
            'hint' => "Trop de modifications sur ce contenu. Attends une minute avant de réessayer.",
            'retryable' => true,
        ],
        'preview_required' => [
            'message' => 'Aperçu requis avant exécution.',
            'hint' => "Calcule d'abord l'aperçu de l'action puis confirme-la avant de l'exécuter.",
            'retryable' => false,
        ],
        'find_text_not_found' => [
            'message' => 'Texte à remplacer introuvable.',
            'hint' => "Relis le contenu actuel (get_page_blocks) pour vérifier le texte exact avant de réessayer.",
            'retryable' => true,
        ],
        'license_inactive' => [
            'message' => 'Licence inactive.',
            'hint' => "La licence SelfHare n'est pas active.",
            'retryable' => false,
        ],
        'upstream_error' => [
            'message' => 'Erreur du service IA.',
            'hint' => "Le service d'intelligence artificique a rencontré une erreur temporaire. Réessaie.",
            'retryable' => true,
        ],
        'invalid_json' => [
            'message' => 'JSON invalide.',
            'hint' => "Le format des données envoyées est incorrect.",
            'retryable' => false,
        ],
        'invalid_payload' => [
            'message' => 'Données invalides.',
            'hint' => "Certains champs obligatoires sont manquants ou incorrects.",
            'retryable' => false,
        ],
    ];

    public static function translate($code, $default_message = '') {
        if (isset(self::ERROR_MAP[$code])) {
            return self::ERROR_MAP[$code];
        }
        return [
            'message' => $default_message ?: "Erreur inconnue ($code).",
            'hint' => '',
            'retryable' => false,
        ];
    }

    public static function enrich_result($result) {
        if ($result['success']) return $result;

        $code = isset($result['error']) ? $result['error'] : 'unknown';
        $info = self::translate($code, isset($result['message']) ? $result['message'] : '');

        $result['error_info'] = $info;
        return $result;
    }
}
