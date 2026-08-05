# Batteries de régression — houetor-connect (rejouables)

Scripts PHP exécutés via WP-CLI dans un WordPress de test avec le plugin actif.
Tous sont auto-suffisants (aucune dépendance à un chemin du poste) :
ils passent par `WP_REST_Request` interne + `get_option('hwc_token')`.

```bash
# Depuis le répertoire WordPress de test
wp --allow-root eval-file <chemin>/tests/rest-test-v3.php
```

| Script | Batterie | Nombre de tests |
|---|---|---|
| `rest-test.php` | Série 001 (routes d'origine) | 18 |
| `rest-test-v2.php` | Série 002 (v2.3.0 : CAS, rate limit, audit, révisions) | 14 |
| `rest-test-v3.php` | V3 (CRUD bloc 2.x, dont V3-6 refus conteneur) | 32 |
| `rest-test-transform.php` | Transform (T1-T10, dont T7 CAS 409 / T8 dry_run / T10 refus conteneur) | 21 |
| `rest-test-nested-child-native.php` | Enfant DANS core/quote natif (patch 2.8.0) | 11 |
| `rest-test-structural.php` | Ops structurelles move/duplicate/wrap/unwrap (2.7.0) | 42 |
| `rest-test-retention.php` | Rétention audit + journal (2.5.0) | 9 |
| `rest-test-tierpolicy.php` | Tier policy blocs legacy (2.6.0) | 11 |
| `test-connect.php` | Suite héritée (règles connexion etc.) | — |

## Harnesses parse_blocks réels (patch blocs imbriqués)

`test-nested-block-depth1.php` (group > paragraph) et
`test-nested-block-depth2-refs.php` (columns > column > paragraph + ref HWC)
sont des stubs autonomes qui chargent le VRAI `parse_blocks()`/`serialize_blocks()`
de WordPress — exécution directe PHP, sans WP complet :

```bash
WP_INC=/chemin/vers/wp-includes HWC_PLUGIN_INC=/chemin/includes php test-nested-block-depth1.php
# Fallback par défaut : poste du lab HOUETOR (mêmes valeurs si env non posées)
```

Attention : les batteries REST modifient réellement la page de test puis la
restaurent (md5 d'origine vérifié) ; `rest-test-nested-child-native.php` cible la
page 2 et exige un `core/quote` natif avec enfant (état du lab).
