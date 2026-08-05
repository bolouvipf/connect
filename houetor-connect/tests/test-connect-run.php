<?php
// Wrapper d'execution de test-connect.php (suite standalone hors WP).
// Definit ABSPATH car les classes du plugin ont le guard WordPress "defined('ABSPATH') || exit;".
// Usage : php test-connect-run.php  (depuis houetor-connect/tests/)
if (!defined('ABSPATH')) {
    define('ABSPATH', '/stub/');
}
include __DIR__ . '/test-connect.php';
