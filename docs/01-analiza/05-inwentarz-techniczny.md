# Inwentarz techniczny — wtyczki AquaSmart (WordPress)

- **Data sporządzenia:** 2026-11-22
- **Zakres:** `E:\workspace\aqua\AquaSmart-plugins\` — katalogi `aquasmart_v103`, `aquasmart-auth`, `aquasmart-shop`, `aquasmart-user`.
- **Pominięto:** katalogi kopii roboczych (nieaktualne) oraz `.git/`. Pliki w katalogu głównym repozytorium (`README.md`, `dokumentacja-techniczna-aquasmart.md`) nie należą do czterech wtyczek.
- **Metoda:** wyłącznie wyszukiwanie (grep) i odczyt plików, bez oceny. Odwołania w formacie `plik:linia`, ścieżki względem `AquaSmart-plugins\`. Liczba linii liczona jak `wc -l` (liczba znaków nowej linii). Pliki `.woff2` są binarne.

---

## 0. Zestawienie nagłówków

| Katalog | `Plugin Name` | `Version` | Stała wersji | `Requires at least` | `Requires PHP` | `Text Domain` | Plików | Linii PHP |
|---|---|---|---|---|---|---|---:|---:|
| `aquasmart_v103` | AquaSmart V1.0 | 1.1.6 | `AQUASMART_K1K2_VERSION` = `'1.1.6'` (`akwasmart-k1k2-r27-final-result.php:11`) | brak | brak | brak | 52 | 4748 |
| `aquasmart-auth` | AquaSmart Auth | 2.4.1 | `AQS_AUTH_VERSION` = `'2.4.1'` (`aquasmart-auth.php:17`) | brak | 8.0 (`aquasmart-auth.php:7`) | `aquasmart-auth` (`:8`) | 10 | 1808 |
| `aquasmart-shop` | Aikwarium Shop | 1.6.13 | `AQS_SHOP_VERSION` = `'1.6.13'` (`aquasmart-shop.php:15`) | brak | 8.0 (`aquasmart-shop.php:7`) | `aquasmart-shop` (`:8`) | 26 | 4945 |
| `aquasmart-user` | AquaSmart User | 1.0.6 | `AQS_USER_VERSION` = `'1.0.6'` (`aquasmart-user.php:17`) | brak | 8.0 (`aquasmart-user.php:7`) | `aquasmart-user` (`:8`) | 14 | 1818 |

Fakty dotyczące nagłówków:

- Pole `Requires at least` nie występuje w nagłówku żadnej z czterech wtyczek.
- Nagłówek `aquasmart_v103` nie ma pól `Requires PHP` ani `Text Domain`.
- `aquasmart_v103` wczytuje etykietę wersji z `BUILD.txt` (`akwasmart-k1k2-r27-final-result.php:12-23`). Pierwsza linia `BUILD.txt` to `Aikwarium V1.0.3`, a druga `Build: 20260603-003`. Gdy pliku brak, etykieta to `Aikwarium V1.0.4` (fallback, `:14`, `:16`). Napis `Aikwarium V1.0.4` występuje też na sztywno w komunikacie admina (`:78`).
- Wersja w nagłówku (`1.1.6`) i etykieta z `BUILD.txt` (`V1.0.3`) są różne.

---

## 1. `aquasmart_v103` (AquaSmart V1.0)

Plik główny: `aquasmart_v103/akwasmart-k1k2-r27-final-result.php` (1060 linii). Opis (`:4`): „Akwarystyczny doradca doboru organizmów, roślin, sprzętu i pielęgnacji. Shortcode [akwa_filtr_r37].”

Klasy: `AquaSmart_K1K2_Plugin_R37` (`:58`), `AquaSmart_K1K2_Admin` (`:864`), `AquaSmart_Data` (`includes/class-akwasmart-data.php:4`), `AquaSmart_Engine` (`includes/class-akwasmart-engine.php:4`), `AQS_Portal` (`includes/class-aqs-portal.php:21`).

### 1.1 Nagłówek i stałe

| Element | Wartość | Plik:linia |
|---|---|---|
| `Plugin Name` | `AquaSmart V1.0` | `akwasmart-k1k2-r27-final-result.php:3` |
| `Version` | `1.1.6` | `:5` |
| `Author` | `AquaSmart` | `:6` |
| `Requires at least` / `Requires PHP` / `Text Domain` | brak | — |
| `AQUASMART_K1K2_VERSION` | `'1.1.6'` | `:11` |
| `AQUASMART_K1K2_VERSION_LABEL` | wynik funkcji czytającej `BUILD.txt` (fallback `Aikwarium V1.0.4`) | `:12-23` |
| `AQUASMART_K1K2_PLUGIN_FILE` / `_DIR` / `_URL` | `__FILE__` / `plugin_dir_path` / `plugin_dir_url` | `:24-26` |
| `FORM_NONCE_ACTION` (private const, `AquaSmart_K1K2_Plugin_R37`) | `'aqs_k1k2_form_action'` | `:59` |
| `PAGE_SLUG` (private const, `AquaSmart_K1K2_Admin`) | `'aquasmart-dane'` | `:865` |
| `NONCE_ACTION` (private const, `AquaSmart_K1K2_Admin`) | `'aqs_k1k2_admin_action'` | `:866` |
| `AquaSmart_Data::OPTION_SOURCE_MODE` | `'aqs_k1k2_dataset_source'` | `includes/class-akwasmart-data.php:7` |
| `AquaSmart_Data::OPTION_IMPORT_MANIFEST` | `'aqs_k1k2_import_manifest'` | `includes/class-akwasmart-data.php:8` |
| `AquaSmart_Engine::TRANSIENT_PREFIX` | `'aqs_k1k2_'` | `includes/class-akwasmart-engine.php:5` |
| `AquaSmart_Engine::TTL` | `DAY_IN_SECONDS` | `includes/class-akwasmart-engine.php:6` |

Pliki wczytywane przez `require_once` w pliku głównym: `includes/class-akwasmart-data.php` (`:28`), `includes/class-akwasmart-engine.php` (`:29`), `includes/class-aqs-portal.php` (`:30`).

### 1.2 Pliki i liczba linii

| Plik | Linie |
|---|---:|
| `aquasmart_v103/.htaccess` | 1 |
| `aquasmart_v103/AUDIT_FAUNA_REGIONFIX.txt` | 30 |
| `aquasmart_v103/BUILD.txt` | 19 |
| `aquasmart_v103/README.txt` | 55 |
| `aquasmart_v103/REGIONFIX_FAUNA_FLORA.txt` | 22 |
| `aquasmart_v103/REGIONFIX_FAUNA_FLORA_FULLDOC.txt` | 19 |
| `aquasmart_v103/akwasmart-k1k2-r27-final-result.php` | 1060 |
| `aquasmart_v103/assets/.htaccess` | 1 |
| `aquasmart_v103/assets/css/.htaccess` | 1 |
| `aquasmart_v103/assets/css/aqs-design.css` | 345 |
| `aquasmart_v103/assets/css/aqs-skin.css` | 477 |
| `aquasmart_v103/assets/css/files/LICENSE-OFL.txt` | 93 |
| `aquasmart_v103/assets/css/files/index.php` | 2 |
| `aquasmart_v103/assets/css/files/inter-latin-400-normal.woff2` | binarny (brak liczby linii) |
| `aquasmart_v103/assets/css/files/inter-latin-500-normal.woff2` | binarny (brak liczby linii) |
| `aquasmart_v103/assets/css/files/inter-latin-600-normal.woff2` | binarny (brak liczby linii) |
| `aquasmart_v103/assets/css/files/inter-latin-700-normal.woff2` | binarny (brak liczby linii) |
| `aquasmart_v103/assets/css/files/inter-latin-ext-400-normal.woff2` | binarny (brak liczby linii) |
| `aquasmart_v103/assets/css/files/inter-latin-ext-500-normal.woff2` | binarny (brak liczby linii) |
| `aquasmart_v103/assets/css/files/inter-latin-ext-600-normal.woff2` | binarny (brak liczby linii) |
| `aquasmart_v103/assets/css/files/inter-latin-ext-700-normal.woff2` | binarny (brak liczby linii) |
| `aquasmart_v103/assets/css/frontend.css` | 188 |
| `aquasmart_v103/assets/css/index.php` | 2 |
| `aquasmart_v103/assets/index.php` | 2 |
| `aquasmart_v103/assets/js/.htaccess` | 1 |
| `aquasmart_v103/assets/js/index.php` | 2 |
| `aquasmart_v103/assets/js/search.js` | 131 |
| `aquasmart_v103/data/.htaccess` | 7 |
| `aquasmart_v103/data/baza_biotop_end2.txt` | 109 |
| `aquasmart_v103/data/baza_fauna_end1.txt` | 185 |
| `aquasmart_v103/data/baza_flora_end1.txt` | 202 |
| `aquasmart_v103/data/baza_pielegnacja_end1.txt` | 111 |
| `aquasmart_v103/data/index.php` | 2 |
| `aquasmart_v103/data/web.config` | 9 |
| `aquasmart_v103/dokumentacja/01_INSTRUKCJA_INSTALACJI.txt` | 45 |
| `aquasmart_v103/dokumentacja/02_OPIS_WERSJI.txt` | 29 |
| `aquasmart_v103/dokumentacja/03_ZASADY_FAUNA_FLORA.txt` | 53 |
| `aquasmart_v103/dokumentacja/04_AUDYT_ZGODNOSCI.txt` | 45 |
| `aquasmart_v103/dokumentacja/05_RAPORT_TESTOW_TECHNICZNYCH.txt` | 53 |
| `aquasmart_v103/dokumentacja/06_CHECKLISTA_WDROZENIA.txt` | 31 |
| `aquasmart_v103/dokumentacja/07_HISTORIA_WERSJI.txt` | 22 |
| `aquasmart_v103/dokumentacja/08_NOTATKA_DLA_UZYTKOWNIKA.txt` | 19 |
| `aquasmart_v103/dokumentacja/09_V1_0_AUDITFIX_REGION_TYP_K3.txt` | 69 |
| `aquasmart_v103/includes/.htaccess` | 1 |
| `aquasmart_v103/includes/class-akwasmart-data.php` | 683 |
| `aquasmart_v103/includes/class-akwasmart-engine.php` | 2696 |
| `aquasmart_v103/includes/class-aqs-portal.php` | 217 |
| `aquasmart_v103/includes/index.php` | 2 |
| `aquasmart_v103/index.php` | 2 |
| `aquasmart_v103/testy/test_regionfix_source.php` | 51 |
| `aquasmart_v103/testy/test_skladni_php.sh` | 7 |
| `aquasmart_v103/testy/test_v1_0_auditfix_source.php` | 27 |

### 1.3 Shortcody (`add_shortcode`)

| Shortcode | Callback | Plik:linia |
|---|---|---|
| `akwa_filtr_r37` | `AquaSmart_K1K2_Plugin_R37::render_shortcode` | `akwasmart-k1k2-r27-final-result.php:63` |
| `aqs_portal_menu` | `AQS_Portal::render` | `includes/class-aqs-portal.php:24` |

### 1.4 REST i AJAX

- `register_rest_route(`: **0 wystąpień**.
- AJAX (`wp_ajax_` / `wp_ajax_nopriv_`):

| Akcja | Callback | Rejestracja (plik:linia) | Sprawdzenie nonce w ciele | `current_user_can` w ciele |
|---|---|---|---|---|
| `wp_ajax_aqs_k1k2_search_records` | `AquaSmart_K1K2_Plugin_R37::ajax_search_records` (`:289`) | `:68` | **tak** — `wp_verify_nonce($nonce, 'aqs_k1k2_search_action')`, `:291` | nie |
| `wp_ajax_nopriv_aqs_k1k2_search_records` | jw. | `:69` | **tak** (jw.) | nie |

Nonce `aqs_k1k2_search_action` generowany w `render_shortcode` (`:128`).

### 1.5 Hooki `add_action` / `add_filter`

Łącznie wywołań `add_action`/`add_filter` w tej wtyczce: **11** (bez linii komentarzy).

| Hook | Typ | Callback | Plik:linia |
|---|---|---|---|
| `plugins_loaded` | action | funkcja anonimowa (inicjalizacja klasy) | `akwasmart-k1k2-r27-final-result.php:49` |
| `wp_enqueue_scripts` (prio 5) | action | `register_assets` | `:64` |
| `wp_enqueue_scripts` (prio 100) | action | `enqueue_skin` | `:67` |
| `admin_notices` | action | `admin_notice_shortcode` | `:70` |
| `wp_ajax_aqs_k1k2_search_records` | action | `ajax_search_records` | `:68` |
| `wp_ajax_nopriv_aqs_k1k2_search_records` | action | `ajax_search_records` | `:69` |
| `admin_menu` | action | `AquaSmart_K1K2_Admin::register_menu` | `:869` |
| `admin_post_aqs_k1k2_switch_source` | action | `handle_switch_source` | `:870` |
| `admin_post_aqs_k1k2_import_media` | action | `handle_import_media` | `:871` |
| `admin_post_aqs_k1k2_delete_import` | action | `handle_delete_import` | `:872` |
| `template_redirect` | action | `AQS_Portal::guard_guests` | `includes/class-aqs-portal.php:25` |

Wywołania `apply_filters` (nie rejestracje): `aqs_recommendation_output` (`akwasmart-k1k2-r27-final-result.php:665`, `:779`); `aqs_portal_guard_enabled` (`includes/class-aqs-portal.php:39`); `aqs_portal_modules` (`:112`); `aqs_portal_login_slug` (`:116`); `aqs_portal_register_slug` (`:120`).

Aktywacja: `register_activation_hook(AQUASMART_K1K2_PLUGIN_FILE, ['AquaSmart_Data', 'activate_hardening'])` (`akwasmart-k1k2-r27-final-result.php:32`). Brak `register_deactivation_hook`.

Brak hooków: `init`, `wp_login`, `user_register`, `admin_init`, `query_vars`, `login_redirect`.

### 1.6 Harmonogram (cron)

Brak `wp_schedule_event`, `wp_schedule_single_event`, `wp_next_scheduled`.

### 1.7 Tabele (`CREATE TABLE`)

Brak. Wtyczka nie tworzy tabel SQL. Dane są w plikach `data/*.txt` (sekcja 7) i w opcjach WordPress (1.8).

### 1.8 Opcje, meta, transienty, CPT, role, uprawnienia, poczta, połączenia

**Opcje (`get_option` / `update_option` / `add_option` / `delete_option`):**

| Klucz | Operacje (plik:linia) | Uwagi z kodu |
|---|---|---|
| `aqs_k1k2_force_reset_build` | get `akwasmart-k1k2-r27-final-result.php:36`; update `:37` | wartość `'20260603-003'`; przy niezgodności: delete `aqs_k1k2_bundled_version` (`:38`), update `aqs_k1k2_dataset_source` = `bundled` (`:39`) |
| `aqs_k1k2_bundled_version` | get `:50`; update `:52`; delete `:38` | wersja, dla której zapisano ustawienia bundled (`:46-53`) |
| `aqs_k1k2_dataset_source` (`OPTION_SOURCE_MODE`) | update `:39`, `:53`; w `class-akwasmart-data.php`: update `:48`, `:109`; get `:103` | tryb źródła danych (`bundled` / `imported`) |
| `aqs_k1k2_import_manifest` (`OPTION_IMPORT_MANIFEST`) | get `class-akwasmart-data.php:114`; update `:184`; delete `:205` | manifest importu z biblioteki mediów |

Meta użytkownika, meta postu: brak (0 wystąpień `get_/update_/add_/delete_user_meta` i `*_post_meta`).

**Transienty:**

| Klucz | Operacja (plik:linia) | TTL |
|---|---|---|
| `aqs_k1k2_activated` | set `includes/class-akwasmart-data.php:52`; get i delete `akwasmart-k1k2-r27-final-result.php:76-77` | 30 s (`class-akwasmart-data.php:52`) |
| `aqs_k1k2_<session_id>` (`TRANSIENT_PREFIX` + `session_id`) | delete `includes/class-akwasmart-engine.php:22`; get `:97`; set `:101` | `TTL` = `DAY_IN_SECONDS` (`:6`) |

**CPT / taksonomie:** brak (`register_post_type`, `register_taxonomy`: 0 wystąpień).

**Role / capabilities:**

- `add_role`, `add_cap`, `remove_role`, `remove_cap`: brak.
- `current_user_can(...)`: wyłącznie `manage_options` — `akwasmart-k1k2-r27-final-result.php:74`, `:886`, `:1041`; `includes/class-akwasmart-engine.php:2628`.

**Poczta (`wp_mail`):** brak.

**Połączenia zewnętrzne:** brak (`wp_remote_*`, `curl_*`, `file_get_contents('http`, `fsockopen`: 0 wystąpień). Brak twardo zakodowanych domen i URL w PHP.

**Pliki danych czytane przez wtyczkę:** `data/*.txt` — sekcja 7.

---

## 2. `aquasmart-auth` (AquaSmart Auth)

Plik główny: `aquasmart-auth/aquasmart-auth.php` (572 linie). Opis (`:4`): „System rejestracji, logowania i ról dla portalu AquaSmart V2.0”.

Klasy: `AQS_Auth_Plugin` (`aquasmart-auth.php:28`), `AQS_Auth` (`includes/class-aqs-auth.php:12`), `AQS_Registration_Security` (`includes/class-aqs-registration-security.php:14`), `AQS_Roles` (`includes/class-aqs-roles.php:12`), `AQS_Shortcodes` (`includes/class-aqs-shortcodes.php:12`), `AQS_Trial` (`includes/class-aqs-trial.php:12`).

### 2.1 Nagłówek i stałe

| Element | Wartość | Plik:linia |
|---|---|---|
| `Plugin Name` | `AquaSmart Auth` | `aquasmart-auth.php:3` |
| `Version` | `2.4.1` | `:5` |
| `Author` | `Aikwarium` | `:6` |
| `Requires PHP` | `8.0` | `:7` |
| `Text Domain` | `aquasmart-auth` | `:8` |
| `Requires at least` | brak | — |
| `AQS_AUTH_VERSION` | `'2.4.1'` | `:17` |
| `AQS_AUTH_FILE` / `_DIR` / `_URL` | `__FILE__` / `plugin_dir_path` / `plugin_dir_url` | `:18-20` |
| `AQS_Registration_Security::CRON_HOOK` | `'aqs_cleanup_unactivated'` | `includes/class-aqs-registration-security.php:35` |
| `TIMING_MIN` / `TIMING_MAX` | `3` / `3600` | `class-aqs-registration-security.php:17`, `:20` |
| `RL_MAX` / `RL_WINDOW` | `5` / `900` | `class-aqs-registration-security.php:23`, `:26` |
| `TOKEN_TTL` / `CLEANUP_AGE` | `604800` / `604800` | `class-aqs-registration-security.php:29`, `:32` |

Pliki wczytywane przez `require_once` (`aquasmart-auth.php:22-26`): `class-aqs-roles.php`, `class-aqs-trial.php`, `class-aqs-auth.php`, `class-aqs-registration-security.php`, `class-aqs-shortcodes.php`.

### 2.2 Pliki i liczba linii

| Plik | Linie |
|---|---:|
| `aquasmart-auth/aquasmart-auth.php` | 572 |
| `aquasmart-auth/assets/css/aqs-auth.css` | 268 |
| `aquasmart-auth/includes/class-aqs-auth.php` | 545 |
| `aquasmart-auth/includes/class-aqs-registration-security.php` | 291 |
| `aquasmart-auth/includes/class-aqs-roles.php` | 59 |
| `aquasmart-auth/includes/class-aqs-shortcodes.php` | 68 |
| `aquasmart-auth/includes/class-aqs-trial.php` | 116 |
| `aquasmart-auth/index.php` | 2 |
| `aquasmart-auth/templates/login-form.php` | 37 |
| `aquasmart-auth/templates/register-form.php` | 118 |

### 2.3 Shortcody

| Shortcode | Callback | Plik:linia |
|---|---|---|
| `aqs_dashboard` | `AQS_Auth_Plugin::render_dashboard_shortcode` (`:49`) | `aquasmart-auth.php:41` |
| `aqs_login` | `AQS_Shortcodes::render_login` | `includes/class-aqs-shortcodes.php:19` |
| `aqs_register` | `AQS_Shortcodes::render_register` | `includes/class-aqs-shortcodes.php:20` |

### 2.4 REST i AJAX

- `register_rest_route(`: **0 wystąpień**.
- `wp_ajax_` / `wp_ajax_nopriv_`: **0 wystąpień**.
- Obsługa formularzy (nie AJAX): weryfikacja nonce `aqs_form_action` w `includes/class-aqs-auth.php:353` (`wp_verify_nonce($nonce, 'aqs_form_action')`); pole nonce `aqs_nonce` w `templates/login-form.php:26`, `templates/register-form.php:36`, `aquasmart-auth.php:99`.
- Panel admina: nonce `aqs_admin_update_status` weryfikowany w `aquasmart-auth.php:297`; `current_user_can('manage_options')` w `:284` i `:330`.

### 2.5 Hooki `add_action` / `add_filter`

Łącznie wywołań `add_action`/`add_filter` w tej wtyczce: **12** (bez linii komentarzy).

| Hook | Typ | Callback | Plik:linia |
|---|---|---|---|
| `plugins_loaded` | action | `AQS_Auth_Plugin::init` | `aquasmart-auth.php:572` |
| `admin_init` | action | `block_admin_for_portal_users` | `aquasmart-auth.php:37` |
| `show_admin_bar` | filter | `hide_admin_bar_for_portal_users` | `:38` |
| `login_redirect` | filter (3 arg) | `redirect_portal_users_after_login` | `:39` |
| `login_url` | filter (3 arg) | `custom_login_url` | `:40` |
| `admin_menu` | action | `register_admin_menu` | `:44` |
| `admin_init` | action | `handle_admin_actions` | `:45` |
| `login_redirect` | filter (3 arg) | `AQS_Auth::redirect_after_login` | `includes/class-aqs-auth.php:24` |
| `template_redirect` | action | `AQS_Auth::handle_front_controller` | `includes/class-aqs-auth.php:25` |
| `wp_enqueue_scripts` | action | `AQS_Shortcodes::enqueue_assets` | `includes/class-aqs-shortcodes.php:21` |
| `pre_do_shortcode_tag` | filter (4 arg) | `AQS_Trial::guard_advisor_shortcode` | `includes/class-aqs-trial.php:16` |
| `aqs_cleanup_unactivated` (const `CRON_HOOK`) | action | `AQS_Registration_Security::cleanup_unactivated` | `includes/class-aqs-registration-security.php:42` |

Wywołanie własnej akcji: `do_action('aqs_subscription_status_changed', $user_id, $old_status, $status)` — `includes/class-aqs-trial.php:73`.

Aktywacja / dezaktywacja: `register_activation_hook(__FILE__, ['AQS_Auth_Plugin', 'activate'])` (`aquasmart-auth.php:570`); `register_deactivation_hook(__FILE__, ['AQS_Auth_Plugin', 'deactivate'])` (`:571`).

Brak rejestracji hooków `wp_login`, `user_register`, `init`. Wywołania funkcji WP: `wp_logout()` (`includes/class-aqs-auth.php:317`), `wp_delete_user()` (`includes/class-aqs-auth.php:320`; `includes/class-aqs-registration-security.php:268`).

### 2.6 Harmonogram (cron)

| Hook | Wywołanie (plik:linia) | Rodzaj | Recurrence | Handler |
|---|---|---|---|---|
| `aqs_cleanup_unactivated` | `wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK)` — `includes/class-aqs-registration-security.php:45`; `wp_next_scheduled` `:44`, `:54`; `wp_clear_scheduled_hook` `:58` | recurring | `daily` | `AQS_Registration_Security::cleanup_unactivated` (`:247`) |

### 2.7 Tabele (`CREATE TABLE`)

| Tabela | Nazwa | Miejsce utworzenia |
|---|---|---|
| `{prefix}aqs_subscriptions` | `AQS_Auth_Plugin::subscriptions_table()` (`aquasmart-auth.php:124`, `preg_replace` w `:127`) | `create_subscriptions_table()` (`aquasmart-auth.php:155-174`) |
| `{prefix}aqs_status_log` | `$wpdb->prefix . 'aqs_status_log'` (`aquasmart-auth.php:182`) | `create_status_log_table()` (`aquasmart-auth.php:178-195`) |

Wywoływane z `activate()` (`aquasmart-auth.php:110-116`).

Pełny SQL, `aqs_subscriptions` (verbatim, `aquasmart-auth.php:161-174`):

```sql
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'trial',
            trial_start DATETIME NOT NULL,
            trial_end DATETIME NOT NULL,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_user_id (user_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB {$charset_collate};";
```

Pełny SQL, `aqs_status_log` (verbatim, `aquasmart-auth.php:184-195`):

```sql
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(20) NOT NULL,
            new_status VARCHAR(20) NOT NULL,
            changed_by BIGINT UNSIGNED NOT NULL,
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            note TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_changed_at (changed_at)
        ) ENGINE=InnoDB {$charset_collate};";
```

`$table_name` to nazwa z `subscriptions_table()` lub `$wpdb->prefix . 'aqs_status_log'`; `{$charset_collate}` to `$wpdb->get_charset_collate()` (`aquasmart-auth.php:160` dla pierwszej tabeli).

### 2.8 Opcje, meta, transienty, CPT, role, uprawnienia, poczta, połączenia

**Opcje:** `get_option('admin_email')` w `includes/class-aqs-auth.php:519` (opcja WordPress). Opcje własne wtyczki: brak.

**Meta użytkownika:**

| Klucz | Operacje (plik:linia) |
|---|---|
| `aqs_pending_activation` | get `includes/class-aqs-auth.php:133`; set `'1'` `:225`; delete `:277`; get `includes/class-aqs-registration-security.php:184` |
| `aqs_account_type` | get `includes/class-aqs-auth.php:255`; set `:480` (`'shop'` / `'user'`) |
| `aqs_full_name` | set `includes/class-aqs-auth.php:479` |
| `aqs_rodo_accepted_at` | set `includes/class-aqs-auth.php:482` |
| `aqs_marketing_consent` | set `includes/class-aqs-auth.php:487` |
| `aqs_marketing_consent_at` | set `:489`; delete `:492` |
| `aqs_marketing_consent_ip` | set `:490`; delete `:493` |
| `aqs_shop_name` | get `includes/class-aqs-auth.php:282`; set `:497` |
| `aqs_shop_slug` | set `includes/class-aqs-auth.php:498` |
| `aqs_nip` | get `includes/class-aqs-auth.php:283`; set `:499` |
| `aqs_shop_address` | set `includes/class-aqs-auth.php:500` |
| `aqs_shop_url` | set `includes/class-aqs-auth.php:501` |
| `aqs_phone` | get `includes/class-aqs-auth.php:284`; set `:502` |
| `aqs_activation_token` | set (hash) `includes/class-aqs-registration-security.php:168`; get `:188`; delete `:203` |
| `aqs_activation_expires` | set `includes/class-aqs-registration-security.php:169`; get `:189`; delete `:204` |

**Transienty:** `aqs_reg_rl_<md5(client_ip)>` — get `includes/class-aqs-registration-security.php:134`, set `:140`; TTL `max(1, exp - now)`, okno `RL_WINDOW` = 900 s (`:26`, `:136`).

**CPT / taksonomie:** brak.

**Role i capabilities:**

Definicja w `includes/class-aqs-roles.php:17-47` (`AQS_Roles::add_roles`), wywoływana przy aktywacji (`aquasmart-auth.php:112`). Usuwanie w `AQS_Roles::remove_roles` (`class-aqs-roles.php:54-58`), wywoływane przy dezaktywacji (`aquasmart-auth.php:120`).

| Rola | Etykieta | Capabilities (plik:linia) |
|---|---|---|
| `aqs_user` | `Użytkownik Aikwarium` | `read => true`, `aqs_use_advisor => true` (`class-aqs-roles.php:20-25`) |
| `aqs_shop` | `Sklep Aikwarium` | `read => true`, `aqs_use_advisor => true`, `aqs_manage_shop => true` (`class-aqs-roles.php:27-35`) |

- `add_role` — `class-aqs-roles.php:41`; `add_cap` — `:46`; `remove_role` — `:56-57`.
- Nadanie roli użytkownikowi: `$user->add_role('aqs_user')` — `includes/class-aqs-auth.php:266`.
- Sprawdzanie ról przez `in_array(..., roles, true)`: `aqs_shop` / `aqs_user` w `aquasmart-auth.php:57-59`, `:538-539`; `includes/class-aqs-auth.php:156`, `:540`; `administrator` w `includes/class-aqs-auth.php:162`.
- `current_user_can(...)`: wyłącznie `manage_options` (`aquasmart-auth.php:284`, `:330`); `user_can($user, 'manage_options')` w `aquasmart-auth.php:534`.
- Capabilities `aqs_use_advisor` i `aqs_manage_shop` nie są sprawdzane przez `current_user_can` / `user_can` / `has_cap` w żadnym pliku (poza definicją w `class-aqs-roles.php`).

**Poczta (`wp_mail`):**

| Plik:linia | Adresat | Temat |
|---|---|---|
| `includes/class-aqs-auth.php:516` | e-mail użytkownika (`$data['email']`) | `'Witaj w Aikwarium'` (`:514`) |
| `includes/class-aqs-auth.php:525` | `$admin_email` (`admin_email`) | `'Nowa rejestracja sklepu Aikwarium'` (`:523`) |
| `includes/class-aqs-registration-security.php:237` | e-mail rejestrującego się | `'Aktywuj konto Aikwarium'` (`:230`) |

**Połączenia zewnętrzne:** brak (`wp_remote_*`, `curl_*`, `file_get_contents('http`: 0 wystąpień). Twardo zakodowane domeny/URL: brak.

---

## 3. `aquasmart-shop` (Aikwarium Shop)

Plik główny: `aquasmart-shop/aquasmart-shop.php` (125 linii). Opis (`:4`): „Panel sklepów, linki sklepowe dla rekomendacji Aikwarium.”

Klasy: `AQS_Shop_Plugin` (`aquasmart-shop.php:34`), `AQS_Shop_DB` (`includes/class-aqs-shop-db.php:7`), `AQS_Shop_State` (`includes/class-aqs-shop-state.php:24`), `AQS_Shop_Buffer`, `AQS_Shop_Report`, `AQS_Shop_Display`, `AQS_Shop_Notify`, `AQS_Shop_Audit`, `AQS_Shop_Panel`, `AQS_Shop_Admin`, `AQS_Catalog_Display` (`includes/class-aqs-catalog-display.php:21`), `AQS_Shop_Billing` (`includes/class-aqs-shop-billing.php:24`).

### 3.1 Nagłówek i stałe

| Element | Wartość | Plik:linia |
|---|---|---|
| `Plugin Name` | `Aikwarium Shop` | `aquasmart-shop.php:3` |
| `Version` | `1.6.13` | `:5` |
| `Author` | `AquaSmart` | `:6` |
| `Requires PHP` | `8.0` | `:7` |
| `Text Domain` | `aquasmart-shop` | `:8` |
| `Requires at least` | brak | — |
| `AQS_SHOP_VERSION` | `'1.6.13'` | `:15` |
| `AQS_SHOP_FILE` / `_DIR` / `_URL` | `__FILE__` / `plugin_dir_path` / `plugin_dir_url` | `:16-18` |
| `BUFFER_PURGE_HOOK` (private const, `AQS_Shop_Plugin`) | `'aqs_shop_purge_old_buffer'` | `aquasmart-shop.php:37` |
| `AQS_Shop_Billing::CRON_HOOK` (public const) | `'aqs_shop_daily_snapshot'` | `includes/class-aqs-shop-billing.php:26` |
| `AQS_Shop_Billing::MONTHLY_CRON_HOOK` (public const) | `'aqs_shop_monthly_rollup'` | `includes/class-aqs-shop-billing.php:34` |
| `AQS_Shop_State::STATE_INACTIVE` / `STATE_ACTIVE` | `'nieaktywny'` / `'aktywny'` | `includes/class-aqs-shop-state.php:26-27` |
| `AQS_Shop_State::META_STATE` / `META_CLOCK` / `META_EVER` | `'aqs_shop_state'` / `'aqs_shop_deactivation_clock'` / `'aqs_shop_ever_activated'` | `class-aqs-shop-state.php:29`, `:30`, `:34` |
| `AQS_Shop_State` — progi | `ENTRY_THRESHOLD=50` (`:37`); `DEACTIVATION_FLOOR=40` (`:38`); `DEACTIVATION_STRIKES=3` (`:39`); `TIER_S_MIN=50` (`:47`); `TIER_M_MIN=101` (`:48`); `TIER_L_MIN=201` (`:49`); `TIER_XL_MIN=401` (`:50`) | `includes/class-aqs-shop-state.php` |
| `AQS_Shop_DB::DB_FAUNA` / `DB_FLORA` / `DB_EQUIPMENT` / `DB_MAINTENANCE` | `'fauna'`, `'flora'`, `'equipment'`, `'maintenance'` | `includes/class-aqs-shop-db.php:8-11` |
| `AQS_Shop_Admin::PAGE_SLUG` (private const) | `'aquasmart-shop'` | `includes/class-aqs-shop-admin.php:8` |

Pliki wczytywane przez `require_once`: `aquasmart-shop.php:20-28` (`class-aqs-shop-db.php`, `class-aqs-shop-state.php`, `class-aqs-shop-buffer.php`, `class-aqs-shop-report.php`, `class-aqs-shop-display.php`, `class-aqs-shop-notify.php`, `class-aqs-shop-audit.php`, `class-aqs-shop-panel.php`, `class-aqs-shop-admin.php`), `:31` (`class-aqs-catalog-display.php`), `:32` (`class-aqs-shop-billing.php`).

### 3.2 Pliki i liczba linii

| Plik | Linie |
|---|---:|
| `aquasmart-shop/aquasmart-shop.php` | 125 |
| `aquasmart-shop/assets/index.php` | 5 |
| `aquasmart-shop/assets/shop-panel.css` | 425 |
| `aquasmart-shop/assets/shop-panel.js` | 265 |
| `aquasmart-shop/includes/class-aqs-catalog-display.php` | 253 |
| `aquasmart-shop/includes/class-aqs-shop-admin.php` | 578 |
| `aquasmart-shop/includes/class-aqs-shop-audit.php` | 62 |
| `aquasmart-shop/includes/class-aqs-shop-billing.php` | 315 |
| `aquasmart-shop/includes/class-aqs-shop-buffer.php` | 251 |
| `aquasmart-shop/includes/class-aqs-shop-db.php` | 992 |
| `aquasmart-shop/includes/class-aqs-shop-display.php` | 217 |
| `aquasmart-shop/includes/class-aqs-shop-notify.php` | 211 |
| `aquasmart-shop/includes/class-aqs-shop-panel.php` | 639 |
| `aquasmart-shop/includes/class-aqs-shop-report.php` | 113 |
| `aquasmart-shop/includes/class-aqs-shop-state.php` | 245 |
| `aquasmart-shop/includes/index.php` | 5 |
| `aquasmart-shop/index.php` | 5 |
| `aquasmart-shop/templates/catalog-product-page.php` | 104 |
| `aquasmart-shop/templates/catalog-shop-page.php` | 81 |
| `aquasmart-shop/templates/index.php` | 5 |
| `aquasmart-shop/templates/panel-add-product.php` | 111 |
| `aquasmart-shop/templates/panel-buffer.php` | 247 |
| `aquasmart-shop/templates/panel-catalog.php` | 122 |
| `aquasmart-shop/templates/panel-dashboard.php` | 135 |
| `aquasmart-shop/templates/panel-products.php` | 85 |
| `aquasmart-shop/templates/panel-report.php` | 39 |

### 3.3 Shortcody

Rejestrowane w `AQS_Shop_Panel::init` (`includes/class-aqs-shop-panel.php:8`):

| Shortcode | Callback | Plik:linia |
|---|---|---|
| `aqs_shop_panel` | `AQS_Shop_Panel::shortcode` | `includes/class-aqs-shop-panel.php:9` |
| `aqs_shop_report` | `AQS_Shop_Report::shortcode` | `includes/class-aqs-shop-panel.php:10` |

### 3.4 REST i AJAX

- `register_rest_route(`: **0 wystąpień**.

| Akcja | Callback | Rejestracja (plik:linia) | Sprawdzenie nonce w ciele | `current_user_can` w ciele |
|---|---|---|---|---|
| `wp_ajax_aqs_shop_get_records` | `AQS_Shop_Panel::ajax_get_records` (`includes/class-aqs-shop-panel.php:108`) | `aquasmart-shop.php:52` | **tak** — `check_ajax_referer('aqs_shop_ajax', 'nonce', false)`, `class-aqs-shop-panel.php:109` | nie |
| `wp_ajax_nopriv_aqs_shop_get_records` | `AQS_Shop_Panel::ajax_get_records_nopriv` (`class-aqs-shop-panel.php:98`) | `aquasmart-shop.php:53` | **nie** — metoda zwraca `wp_send_json_error(..., 401)` bez weryfikacji nonce | nie |

Nonce `aqs_shop_ajax` generowany w `aquasmart-shop.php:71`.

Weryfikacja nonce w metodach obsługi formularzy klasy `AQS_Shop_Panel` (metody prywatne; nie są rejestrowane przez `add_action`, miejsce wywołania nie było przedmiotem sprawdzenia):

- `check_admin_referer` w: `class-aqs-shop-panel.php:159` (`aqs_shop_add_to_buffer`), `:204` i `:218` (`aqs_shop_buffer_action`), `:246` (`aqs_shop_remove_draft`), `:263` (`aqs_shop_save_variant`), `:311` (`aqs_shop_activate_catalog`), `:345` (`aqs_shop_delete_product`).
- Metody: `handle_add_to_buffer` (`:158`), `handle_validate_buffer` (`:203`), `handle_publish_buffer` (`:217`), `handle_remove_draft` (`:245`), `handle_save_variant` (`:262`), `handle_activate_catalog` (`:310`), `handle_delete_product` (`:344`).

### 3.5 Hooki `add_action` / `add_filter`

Łącznie wywołań `add_action`/`add_filter` w tej wtyczce: **22** (bez linii komentarzy).

| Hook | Typ | Callback | Plik:linia |
|---|---|---|---|
| `plugins_loaded` | action | `AQS_Shop_Plugin::instance` | `aquasmart-shop.php:125` |
| `plugins_loaded` (prio 20) | action | `AQS_Shop_DB::maybe_upgrade` | `includes/class-aqs-shop-db.php:992` |
| `aqs_shop_purge_old_buffer` (const `BUFFER_PURGE_HOOK`) | action | `AQS_Shop_DB::purge_old_buffer` | `aquasmart-shop.php:55` |
| `wp_enqueue_scripts` | action | `AQS_Shop_Plugin::register_assets` | `aquasmart-shop.php:62` |
| `admin_enqueue_scripts` | action | `register_admin_assets` | `aquasmart-shop.php:63` |
| `wp_ajax_aqs_shop_get_records` | action | `AQS_Shop_Panel::ajax_get_records` | `aquasmart-shop.php:52` |
| `wp_ajax_nopriv_aqs_shop_get_records` | action | `AQS_Shop_Panel::ajax_get_records_nopriv` | `aquasmart-shop.php:53` |
| `admin_menu` (prio 30) | action | `AQS_Shop_Admin::register_menu` | `includes/class-aqs-shop-admin.php:11` |
| `admin_post_aqs_shop_admin_save_settings` | action | `AQS_Shop_Admin::handle_save_settings` | `includes/class-aqs-shop-admin.php:12` |
| `admin_post_aqs_shop_admin_force_cron` | action | `AQS_Shop_Admin::handle_force_cron` | `includes/class-aqs-shop-admin.php:13` |
| `admin_post_aqs_shop_admin_export_report` | action | `AQS_Shop_Admin::handle_export_report` | `includes/class-aqs-shop-admin.php:14` |
| `aqs_shop_daily_snapshot` (const `CRON_HOOK`) | action | `AQS_Shop_Billing::take_daily_snapshot` | `includes/class-aqs-shop-billing.php:41` |
| `aqs_shop_monthly_rollup` (const `MONTHLY_CRON_HOOK`) | action | `AQS_Shop_Billing::run_monthly_rollup` | `includes/class-aqs-shop-billing.php:42` |
| `init` | action | `AQS_Catalog_Display::add_rewrite_rules` | `includes/class-aqs-catalog-display.php:25` |
| `query_vars` | filter | `AQS_Catalog_Display::query_vars` | `includes/class-aqs-catalog-display.php:26` |
| `template_redirect` | action | `AQS_Catalog_Display::handle_redirect` | `includes/class-aqs-catalog-display.php:27` |
| `init` | action | `AQS_Shop_Display::add_rewrite_rules` | `includes/class-aqs-shop-display.php:10` |
| `query_vars` | filter | `AQS_Shop_Display::query_vars` | `includes/class-aqs-shop-display.php:11` |
| `template_redirect` | action | `AQS_Shop_Display::handle_redirect` | `includes/class-aqs-shop-display.php:12` |
| `aqs_recommendation_output` | filter (3 arg, prio 10) | `AQS_Shop_Display::inject_links` | `includes/class-aqs-shop-display.php:9` |
| `aqs_subscription_status_changed` | action (3 arg, prio 10) | `AQS_Shop_Notify::handle_status_change` | `includes/class-aqs-shop-notify.php:9` |
| `aqs_shop_daily_cron` | action | `AQS_Shop_Notify::daily_cron` | `includes/class-aqs-shop-notify.php:10` |

Filtry wywoływane (`apply_filters`): `aqs_shop_inactive_subscription_statuses` (`includes/class-aqs-shop-notify.php:80`); `aqs_shop_active_subscription_statuses` (`:86`).

Aktywacja / dezaktywacja: `register_activation_hook(__FILE__, ['AQS_Shop_Plugin', 'activate'])` (`aquasmart-shop.php:122`); `register_deactivation_hook(__FILE__, ['AQS_Shop_Plugin', 'deactivate'])` (`:123`). `activate()` (`:88`) wywołuje `AQS_Shop_DB::install()` (`:89`), `AQS_Shop_Display::add_rewrite_rules()` (`:93`), `AQS_Catalog_Display::add_rewrite_rules()` (`:94`), `flush_rewrite_rules()` (`:97`).

Brak rejestracji hooków `wp_login`, `user_register`.

### 3.6 Harmonogram (cron)

| Hook | Wywołanie (plik:linia) | Rodzaj | Recurrence | Handler |
|---|---|---|---|---|
| `aqs_shop_purge_old_buffer` | `wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::BUFFER_PURGE_HOOK)` — `aquasmart-shop.php:109`; `wp_next_scheduled` `:108`, `:114`, `:117` | recurring | `daily` | `AQS_Shop_DB::purge_old_buffer` (`includes/class-aqs-shop-db.php:320`) |
| `aqs_shop_daily_snapshot` | `wp_schedule_event(self::next_run_timestamp(), 'daily', self::CRON_HOOK)` — `includes/class-aqs-shop-billing.php:55`; `wp_next_scheduled` `:52`, `:60`, `:63` | recurring | `daily` | `AQS_Shop_Billing::take_daily_snapshot` (`:252`) |
| `aqs_shop_monthly_rollup` | `wp_schedule_single_event(self::next_monthly_run_timestamp(), self::MONTHLY_CRON_HOOK)` — `includes/class-aqs-shop-billing.php:95`; ponowne planowanie w `:138`; `wp_next_scheduled` `:92` | single (planowane ponownie) | brak (`single`) | `AQS_Shop_Billing::run_monthly_rollup` (`:122`) |
| `aqs_shop_daily_cron` | `wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'aqs_shop_daily_cron')` — `includes/class-aqs-shop-notify.php:15`; `wp_next_scheduled` `:14`, `:20`, `:23` | recurring | `daily` | `AQS_Shop_Notify::daily_cron` (`:69`) |

Ręczne uruchomienie: `AQS_Shop_Admin::handle_force_cron` (`includes/class-aqs-shop-admin.php:13`).

### 3.7 Tabele (`CREATE TABLE`)

Metody: `AQS_Shop_DB::install()` (`includes/class-aqs-shop-db.php:15`) wywołuje `AQS_Shop_DB::create_tables()` (`:27`). Nazwy tabel: `AQS_Shop_DB::table()` (`:340-351`).

| Klucz w `table()` | Nazwa tabeli | Miejsce `CREATE` |
|---|---|---|
| `products` | `{prefix}aqs_shop_products` | `includes/class-aqs-shop-db.php:39-64` |
| `clicks` | `{prefix}aqs_shop_clicks` | `:66-77` |
| `notifications_log` | `{prefix}aqs_shop_notifications_log` | `:79-87` |
| `buffer` | `{prefix}aqs_shop_buffer` | `:89-113` |
| `billing_snapshots` | `{prefix}aqs_shop_billing_snapshots` | `:123-134` |
| `billing_monthly` | `{prefix}aqs_shop_billing_monthly` | `:146-158` |
| `subscriptions` | `{prefix}aqs_subscriptions` | nie tworzona przez shop; tabela z `aquasmart-auth` (`includes/class-aqs-shop-db.php:346`, odczyt `:405-410`) |
| audit (`AQS_Shop_Audit::table()`) | `{prefix}aqs_shop_audit_log` (`includes/class-aqs-shop-audit.php:60`) | `includes/class-aqs-shop-audit.php:13-26` |

Pełny SQL (verbatim):

`aqs_shop_products` (`includes/class-aqs-shop-db.php:39-64`):

```sql
        $sql_products = "CREATE TABLE {$products} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id BIGINT UNSIGNED NOT NULL,
            aqs_db VARCHAR(20) NOT NULL,
            aqs_record_id VARCHAR(40) NOT NULL,
            record_name VARCHAR(255) NULL,
            variant VARCHAR(50) NULL,
            url VARCHAR(2048) NOT NULL,
            price DECIMAL(10,2) NULL,
            last_updated DATETIME NOT NULL,
            published_at DATETIME NULL,
            click_count INT UNSIGNED NOT NULL DEFAULT 0,
            row_checksum VARCHAR(64) NULL,
            url_status VARCHAR(20) NOT NULL DEFAULT 'unchecked',
            url_status_code INT UNSIGNED NULL,
            url_last_checked DATETIME NULL,
            url_final_url VARCHAR(2048) NULL,
            url_warning TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_shop_id (shop_id),
            KEY idx_aqs_record (aqs_db, aqs_record_id),
            KEY idx_url_status (url_status),
            KEY idx_last_updated (last_updated)
        ) {$charset_collate};";
```

`aqs_shop_clicks` (`includes/class-aqs-shop-db.php:66-77`):

```sql
        $sql_clicks = "CREATE TABLE {$clicks} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            shop_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_hash VARCHAR(64) NULL,
            PRIMARY KEY (id),
            KEY idx_product_id (product_id),
            KEY idx_shop_id (shop_id),
            KEY idx_clicked_at (clicked_at)
        ) {$charset_collate};";
```

`aqs_shop_notifications_log` (`includes/class-aqs-shop-db.php:79-87`):

```sql
        $sql_logs = "CREATE TABLE {$logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_shop_id (shop_id),
            KEY idx_type (type)
        ) {$charset_collate};";
```

`aqs_shop_buffer` (`includes/class-aqs-shop-db.php:89-113`):

```sql
        $sql_buffer = "CREATE TABLE {$buffer} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id BIGINT UNSIGNED NOT NULL,
            aqs_db VARCHAR(20) NOT NULL,
            aqs_record_id VARCHAR(40) NOT NULL,
            record_name VARCHAR(255) NULL,
            variant VARCHAR(50) NULL,
            url VARCHAR(2048) NULL,
            price DECIMAL(10,2) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at DATETIME NULL,
            draft_lock VARCHAR(150) GENERATED ALWAYS AS (
                CASE WHEN status = 'draft'
                    THEN CONCAT(shop_id, '|', aqs_db, '|', aqs_record_id, '|', COALESCE(variant, ''))
                    ELSE NULL
                END
            ) STORED,
            PRIMARY KEY (id),
            KEY idx_shop_id (shop_id),
            KEY idx_status (status),
            KEY idx_shop_status (shop_id, status),
            UNIQUE KEY idx_draft_lock (draft_lock)
        ) {$charset_collate};";
```

`aqs_shop_billing_snapshots` (`includes/class-aqs-shop-db.php:123-134`):

```sql
        $sql_snapshots = "CREATE TABLE {$snapshots} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id BIGINT UNSIGNED NOT NULL,
            sample_date DATE NOT NULL,
            products_count INT UNSIGNED NOT NULL DEFAULT 0,
            internal_count INT UNSIGNED NOT NULL DEFAULT 0,
            sampled_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_shop_date (shop_id, sample_date),
            KEY idx_shop_id (shop_id),
            KEY idx_sample_date (sample_date)
        ) {$charset_collate};";
```

`aqs_shop_billing_monthly` (`includes/class-aqs-shop-db.php:146-158`):

```sql
        $sql_monthly = "CREATE TABLE {$monthly} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id BIGINT UNSIGNED NOT NULL,
            period CHAR(7) NOT NULL,
            avg_products DECIMAL(7,2) NOT NULL DEFAULT 0.00,
            avg_internal DECIMAL(7,2) NOT NULL DEFAULT 0.00,
            active_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            rolled_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_shop_period (shop_id, period),
            KEY idx_shop_id (shop_id),
            KEY idx_period (period)
        ) {$charset_collate};";
```

`aqs_shop_audit_log` (`includes/class-aqs-shop-audit.php:13-26`):

```sql
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(64) NOT NULL,
            object_type VARCHAR(32) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_object (object_type, object_id),
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) {$charset_collate};";
```

### 3.8 Opcje, meta, transienty, CPT, role, uprawnienia, poczta, połączenia

**Opcje:**

| Klucz | Operacje (plik:linia) | Wartość / uwagi |
|---|---|---|
| `aqs_shop_db_version` | get `includes/class-aqs-shop-db.php:169`; update `:179` | `'1.0.13'`; sprawdzane w `maybe_upgrade()` (`:168`) |

**Meta użytkownika** (`$shop_id` = ID użytkownika-sklepu):

| Klucz | Operacje (plik:linia) |
|---|---|
| `aqs_shop_state` (`META_STATE`) | get `includes/class-aqs-shop-state.php:76`; set `:91`, `:157` |
| `aqs_shop_ever_activated` (`META_EVER`) | set `includes/class-aqs-shop-state.php:92`; get `:148` |
| `aqs_shop_deactivation_clock` (`META_CLOCK`) | get `includes/class-aqs-shop-state.php:167`; set `:172`, `:179` |
| `aqs_shop_slug` | get `includes/class-aqs-catalog-display.php:60`, `includes/class-aqs-shop-state.php:105`; set `includes/class-aqs-shop-state.php:128` |
| `aqs_shop_name` | get `includes/class-aqs-catalog-display.php:241`, `includes/class-aqs-shop-state.php:110` |
| `aqs_shop_public_contact` | get `includes/class-aqs-catalog-display.php:192`; set `includes/class-aqs-shop-admin.php:80` |
| `aqs_shop_order_notify` | set `includes/class-aqs-shop-admin.php:75`; get `includes/class-aqs-shop-notify.php:29` |
| `aqs_shop_notify_email` | set `includes/class-aqs-shop-admin.php:76`; get `includes/class-aqs-shop-notify.php:32` |
| `aqs_shop_allowed_domain` | set `includes/class-aqs-shop-admin.php:77`; get `includes/class-aqs-shop-db.php:431` |
| `aqs_shop_commission_rules` | get `includes/class-aqs-shop-admin.php:82`, `:253`, `includes/class-aqs-shop-db.php:470`; set `includes/class-aqs-shop-admin.php:89`; delete `:95`, `:109` |

Meta postu i termy: brak.

**Transienty:**

| Klucz | Operacja (plik:linia) | TTL |
|---|---|---|
| `aqs_shop_admin_notice` | set `includes/class-aqs-shop-admin.php:539`; get `:550`; delete `:551` | 5 min (`:545`) |
| `aqs_shop_notice_<get_current_user_id()>` | set `includes/class-aqs-shop-panel.php:617`; get `:629`; delete `:630` (klucz `:628`) | 5 min (`:623`) |
| `aqs_shop_validation_<$shop_id>` | set `includes/class-aqs-shop-panel.php:210` | 5 min (`:210`) |
| `aqs_shop_validation_<get_current_user_id()>` | get `includes/class-aqs-shop-panel.php:636`; delete `:637` (klucz `:635`) | — |

**CPT / taksonomie:** brak.

**Role / capabilities:**

- Brak `add_role`, `add_cap`, `remove_role` w tej wtyczce.
- Rola `aqs_shop` sprawdzana przez `in_array('aqs_shop', (array) $user->roles, true)`: `includes/class-aqs-shop-db.php:394`, `:402`.
- `current_user_can('manage_options')`: `includes/class-aqs-shop-admin.php:39`, `:48`, `:62`, `:127`, `:142`.
- Capability `aqs_manage_shop` nie jest sprawdzana w tej wtyczce.

**Poczta (`wp_mail`):**

| Plik:linia | Adresat | Temat |
|---|---|---|
| `includes/class-aqs-shop-notify.php:52` | e-mail sklepu (wywołanie z `:46`) | `'Aikwarium – kliknięcie w produkt: ' . $record_label` (część stała: `Aikwarium – kliknięcie w produkt: `) |
| `includes/class-aqs-shop-notify.php:115` | `$user->user_email` | `'Aikwarium – Twoja subskrypcja wygasa za ' . $days . ' dni'` |
| `includes/class-aqs-shop-notify.php:148` | `$user->user_email` | `'Aikwarium – oferta wymaga aktualizacji'` |

**Połączenia zewnętrzne (HTTP):**

- `wp_remote_head($url, $args)` — `includes/class-aqs-shop-db.php:745`; fallback `wp_remote_get($url, $args)` — `:747` (gdy błąd lub kod 405).
- `wp_remote_retrieve_response_code` — `:746`, `:756`; `wp_remote_retrieve_header($response, 'location')` — `:758`.
- Adres `$url` pochodzi z linków produktów (`validate_url_remote`, `includes/class-aqs-shop-db.php:726`).
- `curl_*`, `file_get_contents('http`: 0 wystąpień. Twardo zakodowane domeny: brak; w widokach tylko `placeholder="https://"` (`templates/panel-add-product.php:85`, `templates/panel-buffer.php:126`).

---

## 4. `aquasmart-user` (AquaSmart User)

Plik główny: `aquasmart-user/aquasmart-user.php` (76 linii). Opis (`:4`): „Panel użytkownika indywidualnego — zbiorniki, dziennik, parametry wody, przypomnienia”.

Klasy: `AQS_User_Plugin` (`aquasmart-user.php:30`), `AQS_User_DB` (`includes/class-aqs-user-db.php:10`), `AQS_User_Tanks`, `AQS_User_Journal`, `AQS_User_Params`, `AQS_User_Reminders`, `AQS_User_Panel` (`includes/class-aqs-user-panel.php:7`), `AQS_Redfield` (`includes/class-aqs-redfield.php:7`).

### 4.1 Nagłówek i stałe

| Element | Wartość | Plik:linia |
|---|---|---|
| `Plugin Name` | `AquaSmart User` | `aquasmart-user.php:3` |
| `Version` | `1.0.6` | `:5` |
| `Author` | `AquaSmart` | `:6` |
| `Requires PHP` | `8.0` | `:7` |
| `Text Domain` | `aquasmart-user` | `:8` |
| `Requires at least` | brak | — |
| `AQS_USER_VERSION` | `'1.0.6'` | `:17` |
| `AQS_USER_FILE` / `_DIR` / `_URL` | `__FILE__` / `plugin_dir_path` / `plugin_dir_url` | `:18-20` |

Pliki wczytywane przez `require_once` (`aquasmart-user.php:22-28`): `class-aqs-user-db.php`, `class-aqs-user-tanks.php`, `class-aqs-user-journal.php`, `class-aqs-user-params.php`, `class-aqs-user-reminders.php`, `class-aqs-user-panel.php`, `class-aqs-redfield.php`.

### 4.2 Pliki i liczba linii

| Plik | Linie |
|---|---:|
| `aquasmart-user/aquasmart-user.php` | 76 |
| `aquasmart-user/assets/css/aqs-user-panel.css` | 142 |
| `aquasmart-user/assets/js/aqs-user-panel.js` | 49 |
| `aquasmart-user/includes/class-aqs-redfield.php` | 133 |
| `aquasmart-user/includes/class-aqs-user-db.php` | 678 |
| `aquasmart-user/includes/class-aqs-user-journal.php` | 88 |
| `aquasmart-user/includes/class-aqs-user-panel.php` | 126 |
| `aquasmart-user/includes/class-aqs-user-params.php` | 110 |
| `aquasmart-user/includes/class-aqs-user-reminders.php` | 131 |
| `aquasmart-user/includes/class-aqs-user-tanks.php` | 101 |
| `aquasmart-user/templates/panel-journal.php` | 60 |
| `aquasmart-user/templates/panel-params.php` | 91 |
| `aquasmart-user/templates/panel-reminders.php` | 132 |
| `aquasmart-user/templates/panel-tanks.php` | 92 |

### 4.3 Shortcody

| Shortcode | Callback | Plik:linia |
|---|---|---|
| `aqs_redfield` | `AQS_Redfield::shortcode` | `includes/class-aqs-redfield.php:11` |
| `aqs_user_panel` | `AQS_User_Panel::shortcode` | `includes/class-aqs-user-panel.php:11` |

### 4.4 REST i AJAX

- `register_rest_route(`: **0 wystąpień**.
- `wp_ajax_nopriv_`: brak w tej wtyczce.

| Akcja (`wp_ajax_`) | Callback | Rejestracja (plik:linia) | Nonce w ciele (`wp_verify_nonce($nonce, 'aqs_user_nonce')`) | `current_user_can` w ciele |
|---|---|---|---|---|
| `aqs_user_add_tank` | `AQS_User_Tanks::ajax_add` (`includes/class-aqs-user-tanks.php:18`) | `aquasmart-user.php:49` | tak — `class-aqs-user-tanks.php:21` | nie |
| `aqs_user_edit_tank` | `AQS_User_Tanks::ajax_edit` (`class-aqs-user-tanks.php:45`) | `aquasmart-user.php:50` | tak — `:48` | nie |
| `aqs_user_delete_tank` | `AQS_User_Tanks::ajax_delete` (`class-aqs-user-tanks.php:77`) | `aquasmart-user.php:51` | tak — `:80` | nie |
| `aqs_user_add_journal` | `AQS_User_Journal::ajax_add` (`includes/class-aqs-user-journal.php:27`) | `aquasmart-user.php:52` | tak — `class-aqs-user-journal.php:30` | nie |
| `aqs_user_delete_journal` | `AQS_User_Journal::ajax_delete` (`class-aqs-user-journal.php:59`) | `aquasmart-user.php:53` | tak — `:62` | nie |
| `aqs_user_add_params` | `AQS_User_Params::ajax_add` (`includes/class-aqs-user-params.php:35`) | `aquasmart-user.php:54` | tak — `class-aqs-user-params.php:38` | nie |
| `aqs_user_add_reminder` | `AQS_User_Reminders::ajax_add` (`includes/class-aqs-user-reminders.php:29`) | `aquasmart-user.php:55` | tak — `class-aqs-user-reminders.php:32` | nie |
| `aqs_user_mark_done` | `AQS_User_Reminders::ajax_mark_done` (`class-aqs-user-reminders.php:74`) | `aquasmart-user.php:56` | tak — `:77` | nie |
| `aqs_user_delete_reminder` | `AQS_User_Reminders::ajax_delete` (`class-aqs-user-reminders.php:103`) | `aquasmart-user.php:57` | tak — `:106` | nie |

Każdy callback pobiera ID przez `get_current_user_id()` i wywołuje `AQS_Trial::check_access($user_id)` (np. `class-aqs-user-tanks.php:25-26`). Nonce `aqs_user_nonce` generowany w `includes/class-aqs-user-panel.php:19`.

### 4.5 Hooki `add_action` / `add_filter`

Łącznie wywołań `add_action`/`add_filter` w tej wtyczce: **11** (bez linii komentarzy).

| Hook | Typ | Callback | Plik:linia |
|---|---|---|---|
| `plugins_loaded` | action | `AQS_User_Plugin::init` | `aquasmart-user.php:76` |
| `wp_ajax_aqs_user_*` (9 akcji) | action | patrz 4.4 | `aquasmart-user.php:49-57` |
| `wp_enqueue_scripts` | action | `AQS_User_Panel::localize_ajax` | `includes/class-aqs-user-panel.php:12` |

Aktywacja / dezaktywacja: `register_activation_hook(__FILE__, ['AQS_User_Plugin', 'activate'])` (`aquasmart-user.php:74`) wywołuje `AQS_User_DB::create_tables()` (`:65`); `register_deactivation_hook(__FILE__, ['AQS_User_Plugin', 'deactivate'])` (`:75`).

Brak hooków `init`, `admin_menu`, `template_redirect`, `wp_login`, `user_register`, `admin_init`, `query_vars`, `login_redirect`.

### 4.6 Harmonogram (cron)

Brak `wp_schedule_event`, `wp_schedule_single_event`, `wp_next_scheduled`.

### 4.7 Tabele (`CREATE TABLE`)

Metoda `AQS_User_DB::create_tables()` (`includes/class-aqs-user-db.php:18`). Nazwa tabeli: `AQS_User_DB::table($name)` (prywatna, `:12`) = `$wpdb->prefix . 'aqs_user_' . $name` (`:15`).

Pełny SQL (verbatim):

`aqs_user_tanks` (`includes/class-aqs-user-db.php:30-41`):

```sql
        $sql_tanks = "CREATE TABLE {$tanks} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       BIGINT UNSIGNED NOT NULL,
            name          VARCHAR(100) NOT NULL,
            volume_liters SMALLINT UNSIGNED NOT NULL,
            tank_type     VARCHAR(30) NOT NULL,
            notes         TEXT NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id)
        ) ENGINE=InnoDB {$charset_collate};";
```

`aqs_user_journal` (`includes/class-aqs-user-db.php:43-53`):

```sql
        $sql_journal = "CREATE TABLE {$journal} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL,
            tank_id    BIGINT UNSIGNED NOT NULL,
            entry_date DATE NOT NULL,
            content    TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tank_id (tank_id),
            KEY idx_entry_date (entry_date)
        ) ENGINE=InnoDB {$charset_collate};";
```

`aqs_user_parameters` (`includes/class-aqs-user-db.php:55-70`):

```sql
        $sql_parameters = "CREATE TABLE {$parameters} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            tank_id     BIGINT UNSIGNED NOT NULL,
            measured_at DATE NOT NULL,
            ph          DECIMAL(4,2) NULL,
            temp_c      DECIMAL(4,1) NULL,
            kh          DECIMAL(5,1) NULL,
            gh          DECIMAL(5,1) NULL,
            po4         DECIMAL(6,3) NULL,
            no3         DECIMAL(6,1) NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tank_id (tank_id),
            KEY idx_measured_at (measured_at)
        ) ENGINE=InnoDB {$charset_collate};";
```

`aqs_user_reminders` (`includes/class-aqs-user-db.php:72-87`):

```sql
        $sql_reminders = "CREATE TABLE {$reminders} (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id        BIGINT UNSIGNED NOT NULL,
            tank_id        BIGINT UNSIGNED NOT NULL,
            reminder_type  VARCHAR(30) NOT NULL,
            label          VARCHAR(100) NULL,
            is_recurring   TINYINT(1) NOT NULL DEFAULT 0,
            interval_days  SMALLINT UNSIGNED NULL,
            next_due       DATE NOT NULL,
            is_done        TINYINT(1) NOT NULL DEFAULT 0,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tank_id (tank_id),
            KEY idx_next_due (next_due)
        ) ENGINE=InnoDB {$charset_collate};";
```

### 4.8 Opcje, meta, transienty, CPT, role, uprawnienia, poczta, połączenia

- **Opcje** (`get_option` / `update_option` / `add_option` / `delete_option`): brak.
- **Meta użytkownika** i **meta postu**: brak.
- **Transienty:** `aqs_user_notice_<get_current_user_id()>` — set `includes/class-aqs-user-panel.php:112` (TTL `5 * MINUTE_IN_SECONDS`, `:115`); get `:122`; delete `:123`.
- **CPT / taksonomie:** brak.
- **Role / capabilities:** brak `add_role`, `add_cap`, `remove_role`; brak `current_user_can`. Dostęp sprawdzany przez `AQS_Trial::check_access` (`aquasmart-auth`).
- **Poczta (`wp_mail`):** brak.
- **Połączenia zewnętrzne:** brak (`wp_remote_*`, `curl_*`, `file_get_contents('http`: 0 wystąpień).

---

## 5. Odwołania third-party (WooCommerce, ACF, Elementor, bramki płatności, reCAPTCHA itd.)

Przeszukano pliki PHP, JS i CSS czterech wtyczek pod kątem: `wc_`, `WC(`, `woocommerce`, `ACF`, `get_field(`, `elementor`, `stripe`, `payu`, `przelewy`, `paypal`, `tpay`, `recaptcha`, `hcaptcha`, `turnstile`, `google`, `facebook`, `analytics`, `gtag`, `sendgrid`, `smtp`, `mailgun`, `yoast`, `wpml`, `polylang`, `jetpack`.

| Wtyczka | Wynik | Uwagi (plik:linia) |
|---|---|---|
| `aquasmart_v103` | brak trafień | — |
| `aquasmart-auth` | brak trafień | — |
| `aquasmart-shop` | brak trafień w kodzie | Słowo `LiteSpeed` tylko w tekstach komunikatów: `includes/class-aqs-shop-panel.php:100`, `:112`. Słowo `OLX` tylko w komentarzach i opisie wycofanego modelu: `aquasmart-shop.php:29`, `:90`; `includes/class-aqs-catalog-display.php:10`; `includes/class-aqs-shop-panel.php:24` |
| `aquasmart-user` | brak trafień | — |

Wynik: nie znaleziono WooCommerce, ACF, Elementor, bramek płatności (Stripe, PayU, Przelewy24, PayPal, tpay), reCAPTCHA ani usług analitycznych. Kod sklepu opisuje linki do sklepów zewnętrznych (tabela `aqs_shop_products`, pola `url`, `url_status`, `url_final_url`).

---

## 6. Odwołania międzywtyczkowe (cross-plugin)

| # | Z wtyczki | Do wtyczki | Co | Plik:linia |
|---|---|---|---|---|
| 1 | `aquasmart-user` | `aquasmart-auth` | klasa `AQS_Trial::check_access()` | `aquasmart-user/includes/class-aqs-redfield.php:22`; `class-aqs-user-journal.php:35`, `:67`; `class-aqs-user-panel.php:34`; `class-aqs-user-params.php:43`; `class-aqs-user-reminders.php:37`, `:82`, `:111`; `class-aqs-user-tanks.php:26`, `:53`, `:85` |
| 2 | `aquasmart-shop` | `aquasmart-auth` | tabela `{prefix}aqs_subscriptions` (odczyt; `SHOW TABLES LIKE`) | `aquasmart-shop/includes/class-aqs-shop-db.php:346`, `:405-410` (`get_subscription`) |
| 3 | `aquasmart-shop` | `aquasmart-auth` | rola `aqs_shop` (sprawdzanie) | `aquasmart-shop/includes/class-aqs-shop-db.php:394`, `:402` |
| 4 | `aquasmart-shop` | `aquasmart-auth` | meta użytkownika zapisywane w auth, odczytywane w shop: `aqs_shop_name`, `aqs_shop_slug` (zapis: `aquasmart-auth/includes/class-aqs-auth.php:497-498`; odczyt: `aquasmart-shop/includes/class-aqs-catalog-display.php:60`, `:241`, `includes/class-aqs-shop-state.php:105`, `:110`); `aqs_nip`, `aqs_phone`, `aqs_shop_address`, `aqs_shop_url` zapisywane w auth (`class-aqs-auth.php:499-502`) | jw. |
| 5 | `aquasmart-shop` | `aquasmart_v103` | klasa `AquaSmart_Data::all()` (sprawdzenie `class_exists` / `method_exists`) | `aquasmart-shop/includes/class-aqs-shop-db.php:546`, `:788`, `:801` |
| 6 | `aquasmart-shop` | `aquasmart_v103` | stała `AQUASMART_K1K2_PLUGIN_DIR` oraz nazwy plików `data/*.txt` (odczyt plików) | `aquasmart-shop/includes/class-aqs-shop-db.php:831-833`; nazwy plików `:871-880` (`baza_fauna_end1.txt`, `baza_flora_end1.txt`, `baza_biotop_end2.txt`, `baza_pielegnacja_end1.txt`) |
| 7 | `aquasmart-shop` | `aquasmart_v103` | filtr `aqs_recommendation_output` (wywołany w v103, zarejestrowany w shop) | `aquasmart_v103/akwasmart-k1k2-r27-final-result.php:665`, `:779`; `aquasmart-shop/includes/class-aqs-shop-display.php:9` |
| 8 | `aquasmart-shop` | `aquasmart-auth` | akcja `aqs_subscription_status_changed` (wywołana w auth, zarejestrowana w shop) | `aquasmart-auth/includes/class-aqs-trial.php:73`; `aquasmart-shop/includes/class-aqs-shop-notify.php:9`, `:62` |
| 9 | `aquasmart_v103` | `aquasmart-auth` | role `aqs_shop`, `aqs_user` (sprawdzanie) | `aquasmart_v103/includes/class-aqs-portal.php:126-129`, `:197` |
| 10 | `aquasmart-auth` | `aquasmart_v103` | shortcode `akwa_filtr_r37`: warunek ładowania assetów (`class-aqs-shortcodes.php:33`) i obsługa tagu w filtrze `pre_do_shortcode_tag` (`class-aqs-trial.php:108`) | `aquasmart-auth/includes/class-aqs-shortcodes.php:33`; `class-aqs-trial.php:105-108` |
| 11 | `aquasmart-auth` | `aquasmart_v103` | sprawdzanie obecności shortcode `aqs_dashboard` i `akwa_filtr_r37` na stronie | `aquasmart-auth/includes/class-aqs-shortcodes.php:32-33`; `class-aqs-auth.php:79` |
| 12 | `aquasmart_v103` | `aquasmart-auth` | `template_redirect` zarejestrowane w obu wtyczkach: `AQS_Portal::guard_guests` i `AQS_Auth::handle_front_controller` | `aquasmart_v103/includes/class-aqs-portal.php:25`; `aquasmart-auth/includes/class-aqs-auth.php:25` |

Brak innych wspólnych stałych, opcji lub tabel między wtyczkami. Prefiksy: `AQS_` (auth, shop, user, portal), `AquaSmart_` / `AQUASMART_K1K2_` / `aqs_k1k2_` / `akwasmart-` (v103), `aqs_` (opcje, meta, transienty, hooki we wszystkich).

---

## 7. Pliki danych `aquasmart_v103/data/*.txt`

Wspólne cechy (sprawdzone bajt po bajcie): separator TAB; kodowanie UTF-8 bez BOM; każdy wiersz danych ma tyle pól co nagłówek.

Końce linii: w `baza_biotop_end2.txt`, `baza_flora_end1.txt`, `baza_pielegnacja_end1.txt` wszystkie linie kończą się CRLF. W `baza_fauna_end1.txt` nagłówek kończy się LF, a wszystkie wiersze danych CRLF (`file` zgłasza „CRLF, LF”).

Liczby wierszy zgadzają się z `BUILD.txt` (`Fauna: 184 rekordów`, `Flora: 201`, `Sprzęt: 108`, `Pielęgnacja: 110`).

### Fauna: `aquasmart_v103/data/baza_fauna_end1.txt`

| Właściwość | Wartość |
|---|---|
| Wiersze danych (bez nagłówka) | 184 |
| Linie łącznie | 185 |
| Liczba kolumn (wg nagłówka) | 12 |
| Separator | TAB; każdy wiersz ma tyle pól co nagłówek |
| Kodowanie | UTF-8, bez BOM |
| Rola w kodzie (`AQS_Shop_DB`) | fauna (`DB_FAUNA`); kandydaci w `candidate_data_basenames()`, `aquasmart-shop/includes/class-aqs-shop-db.php:871-880` |

Nagłówek (verbatim):

```text
id	Nazwa polska	Nazwa łacińska	Region	Wielkość	Temperatura	pH	Temperament	Strefa	Typ	WielkośćAkwarium	TypAkwarium
```

Przykład 1 (linia 2):

```text
FN0001	Gupik Pawie Oczko	Poecilia reticulata	Ameryka Południowa	Mała (3-5 cm)	Grzałka często (22-25,5)	Woda obojętna (6.8 - 7.5)	Spokojny	Górna	Ryba	Małe (40L - 100L)	Ogólne / Towarzyskie
```

Przykład 2 (linia 3):

```text
FN0002	Gupik Endlera	Poecilia wingei	Ameryka Południowa	Nano (do 3 cm)	Grzałka często (22-25,5)	Woda zasadowa (powyżej 7.5)	Spokojny	Górna	Ryba	Nano (< 40L);Małe (40L - 100L)	Nano / Krewetkarium;Ogólne / Towarzyskie
```

### Flora: `aquasmart_v103/data/baza_flora_end1.txt`

| Właściwość | Wartość |
|---|---|
| Wiersze danych (bez nagłówka) | 201 |
| Linie łącznie | 202 |
| Liczba kolumn (wg nagłówka) | 11 |
| Separator | TAB; każdy wiersz ma tyle pól co nagłówek |
| Kodowanie | UTF-8, bez BOM |
| Rola w kodzie (`AQS_Shop_DB`) | flora (`DB_FLORA`); kandydaci w `candidate_data_basenames()`, `class-aqs-shop-db.php:871-880` |

Nagłówek (verbatim):

```text
id	Nazwa łacińska	Nazwa polska	Region	Wielkość	Trudność	Strefa	CO2	Kolor	WielkośćAkwarium	TypAkwarium
```

Przykład 1 (linia 2):

```text
FL0001	Rotala rotundifolia	Rotala okrągłolistna	Azja	Wysoka (>30 cm)	Początkujący	Środek	Wskazany	Czerwony/Brązowy	Małe (40L - 100L);Średnie (101L - 250L);Duże (251L - 500L)	Ogólne / Towarzyskie
```

Przykład 2 (linia 3):

```text
FL0002	Rotala rotundifolia 'H'ra'	Rotala rotundifolia 'H'ra'	Azja	Wysoka (>30 cm)	Średni	Środek	Wskazany	Czerwony/Brązowy	Średnie (101L - 250L);Duże (251L - 500L);Bardzo duże (> 500L)	Roślinne
```

### Sprzęt / biotop: `aquasmart_v103/data/baza_biotop_end2.txt`

| Właściwość | Wartość |
|---|---|
| Wiersze danych (bez nagłówka) | 108 |
| Linie łącznie | 109 |
| Liczba kolumn (wg nagłówka) | 11 |
| Separator | TAB; każdy wiersz ma tyle pól co nagłówek |
| Kodowanie | UTF-8, bez BOM |
| Rola w kodzie (`AQS_Shop_DB`) | equipment (`DB_EQUIPMENT`); kandydat `baza_biotop_end2.txt`, `class-aqs-shop-db.php:871-880` |

Nagłówek (verbatim):

```text
id	kategoria_glowna	rodzaj	producent	Marka	produkt	Poziom_doświadczenia	Zdalne_sterowanie	WielkośćAkwarium	TypAkwarium	opis_krotki
```

Przykład 1 (linia 2):

```text
SP0001	Filtracja	Filtr zewnętrzny	Aquael	Standard	Ultramax 1000	Średniozaawansowany	nie	Średnie (101L - 250L);Duże (251L - 500L)	Ogólne / Towarzyskie;Roślinne;Biotop Twardowodny (Malawi/Tanganika)	Filtr zewnętrzny do średnich i dużych zbiorników, przeznaczony do akwariów ogólnych, roślinnych i twardowodnych, bez sterowania zdalnego.
```

Przykład 2 (linia 3):

```text
SP0002	Filtracja	Filtr zewnętrzny	Aquael	Standard	Ultramax 1500	Średniozaawansowany	nie	Duże (251L - 500L);Bardzo duże (> 500L)	Ogólne / Towarzyskie;Roślinne;Biotop Twardowodny (Malawi/Tanganika)	Filtr zewnętrzny do dużych i bardzo dużych zbiorników, przeznaczony do akwariów ogólnych, roślinnych i twardowodnych, bez sterowania zdalnego.
```

### Pielęgnacja: `aquasmart_v103/data/baza_pielegnacja_end1.txt`

| Właściwość | Wartość |
|---|---|
| Wiersze danych (bez nagłówka) | 110 |
| Linie łącznie | 111 |
| Liczba kolumn (wg nagłówka) | 8 |
| Separator | TAB; każdy wiersz ma tyle pól co nagłówek |
| Kodowanie | UTF-8, bez BOM |
| Rola w kodzie (`AQS_Shop_DB`) | maintenance (`DB_MAINTENANCE`); kandydat `baza_pielegnacja_end1.txt`, `class-aqs-shop-db.php:871-880` |

Nagłówek (verbatim):

```text
id	kategoria_zadania	rodzaj_zadania	obszar	producent	Marka	produkt	opis
```

Przykład 1 (linia 2):

```text
CH0001	Pielęgnacja na co dzień	Cięcie i pielęgnacja	Czyszczenie szyb	JBL	Premium	Aqua-T Handy Blade	Skrobak JBL Aqua-T Handy Blade pozwala bezpiecznie usuwać z szyb glony i osady wapienne, nie rysując szkła przy prawidłowym użyciu.
```

Przykład 2 (linia 3):

```text
CH0002	Pielęgnacja na co dzień	Cięcie i pielęgnacja	Czyszczenie szyb	Tetra	Premium	Czyścik magnetyczny + skrobak	Zestaw do czyszczenia szyb akwarium: czyścik magnetyczny oraz skrobak do usuwania glonów.
```


---

## 8. Braki i uwagi formalne (fakty)

- `Requires at least` — brak we wszystkich czterech nagłówkach.
- `Requires PHP` — brak w `aquasmart_v103`; `8.0` w `aquasmart-auth`, `aquasmart-shop`, `aquasmart-user`.
- `Text Domain` — brak w `aquasmart_v103`; obecny w pozostałych trzech.
- `register_rest_route` — 0 wystąpień we wszystkich wtyczkach.
- `register_post_type` / `register_taxonomy` — 0 wystąpień.
- Hooki `wp_login`, `user_register`, `init` (w wtyczkach auth i user) — brak; `init` w shop: dwie rejestracje (`class-aqs-catalog-display.php:25`, `class-aqs-shop-display.php:10`).
- Hook `template_redirect` w shop: dwie rejestracje (`class-aqs-catalog-display.php:27`, `class-aqs-shop-display.php:12`).
- Pliki nie zawarte w zestawie: `README.md`, `dokumentacja-techniczna-aquasmart.md` w katalogu nadrzędnym oraz pliki dokumentacji `aquasmart_v103/dokumentacja/*.txt` opisano tylko w tabeli plików (sekcja 1.2), bez analizy treści.
