# 70 - Inwentarz bazy testowej (staging) starego WordPressa

Zakres: import dumpu `iegh3u_testaikwarium (1).sql` do lokalnej bazy `aikwarium_legacy` (MySQL 8.4, 127.0.0.1). Raport zawiera wyłącznie liczby, strukturę i nazwy; dane osobowe (e-maile, telefony, imiona, NIP, hashe haseł) nie są w nim przepisane.

## 1. Import

- Źródło: MariaDB 11.4.13 (phpMyAdmin 5.2.2), zestaw znaków utf8mb4.
- Dump nie zawiera poleceń `USE` ani `CREATE DATABASE`, więc kopia nie była potrzebna.
- Baza utworzona: `aikwarium_legacy` (utf8mb4, utf8mb4_unicode_ci).
- Import (`mysql --default-character-set=utf8mb4 -u root aikwarium_legacy < dump`) zakończył się kodem 0, bez błędów.
- Poprawki: brak. Dump nie zawiera klauzul DEFINER ani kolacji MariaDB (`utf8mb4_uca1400_*`). Tabele używają `utf8mb4_unicode_520_ci` oraz `utf8mb3_*`, które MySQL 8.4 akceptuje.
- Oryginalny plik nie został zmieniony.

## 2. Prefiks i liczba tabel

- Prefiks tabel: `wp_` (standardowy WordPress).
- Liczba tabel: **128** (12 tabel core WP, 116 nie-core).
- Tabele aplikacji: `wp_aqs_*` (16 tabel) i `wp_aqss_*` (12 tabel). Nie ma tabel o nazwach `aquasmart*`, `akwa*` ani `aikwarium*`. Te ciągi występują w kluczach opcji i w tytułach (patrz sekcje 5 i 8).

### 2.1. Wszystkie tabele z dokładnymi liczbami wierszy (`COUNT(*)`)

| Tabela | Grupa | Wiersze |
|---|---|---:|
| `wp_aqs_activity` | nie-core | 115 |
| `wp_aqs_catalog_products` | nie-core | 0 |
| `wp_aqs_catalog_views` | nie-core | 0 |
| `wp_aqs_shop_audit_log` | nie-core | 7 |
| `wp_aqs_shop_billing_monthly` | nie-core | 20 |
| `wp_aqs_shop_billing_snapshots` | nie-core | 460 |
| `wp_aqs_shop_buffer` | nie-core | 61 |
| `wp_aqs_shop_clicks` | nie-core | 195 |
| `wp_aqs_shop_notifications_log` | nie-core | 79 |
| `wp_aqs_shop_products` | nie-core | 179 |
| `wp_aqs_status_log` | nie-core | 25 |
| `wp_aqs_subscriptions` | nie-core | 687 |
| `wp_aqs_user_journal` | nie-core | 6 |
| `wp_aqs_user_parameters` | nie-core | 33 |
| `wp_aqs_user_reminders` | nie-core | 4 |
| `wp_aqs_user_tanks` | nie-core | 29 |
| `wp_aqss_audit` | nie-core | 78 |
| `wp_aqss_buffer` | nie-core | 0 |
| `wp_aqss_locations` | nie-core | 0 |
| `wp_aqss_messages` | nie-core | 1 |
| `wp_aqss_offers` | nie-core | 52 |
| `wp_aqss_request_events` | nie-core | 39 |
| `wp_aqss_requests` | nie-core | 14 |
| `wp_aqss_shop_members` | nie-core | 5 |
| `wp_aqss_shops` | nie-core | 5 |
| `wp_aqss_stats_daily` | nie-core | 9 |
| `wp_aqss_subscriptions` | nie-core | 1 |
| `wp_aqss_verifications` | nie-core | 5 |
| `wp_cmplz_cookiebanners` | nie-core | 1 |
| `wp_cmplz_cookies` | nie-core | 8 |
| `wp_cmplz_dnsmpd` | nie-core | 0 |
| `wp_cmplz_services` | nie-core | 2 |
| `wp_commentmeta` | core | 0 |
| `wp_comments` | core | 0 |
| `wp_fsmpt_email_logs` | nie-core | 51 |
| `wp_links` | core | 0 |
| `wp_litespeed_avatar` | nie-core | 0 |
| `wp_litespeed_url` | nie-core | 0 |
| `wp_litespeed_url_file` | nie-core | 0 |
| `wp_matomo_access` | nie-core | 0 |
| `wp_matomo_annotations` | nie-core | 0 |
| `wp_matomo_archive_blob_2026_01` | nie-core | 67 |
| `wp_matomo_archive_blob_2026_06` | nie-core | 0 |
| `wp_matomo_archive_blob_2026_07` | nie-core | 1450 |
| `wp_matomo_archive_blob_2026_08` | nie-core | 1540 |
| `wp_matomo_archive_blob_2026_09` | nie-core | 1602 |
| `wp_matomo_archive_blob_2026_10` | nie-core | 1794 |
| `wp_matomo_archive_blob_2026_11` | nie-core | 880 |
| `wp_matomo_archive_invalidations` | nie-core | 0 |
| `wp_matomo_archive_numeric_2026_01` | nie-core | 85 |
| `wp_matomo_archive_numeric_2026_06` | nie-core | 0 |
| `wp_matomo_archive_numeric_2026_07` | nie-core | 2304 |
| `wp_matomo_archive_numeric_2026_08` | nie-core | 2511 |
| `wp_matomo_archive_numeric_2026_09` | nie-core | 2589 |
| `wp_matomo_archive_numeric_2026_10` | nie-core | 2556 |
| `wp_matomo_archive_numeric_2026_11` | nie-core | 1442 |
| `wp_matomo_archiving_metrics` | nie-core | 0 |
| `wp_matomo_brute_force_log` | nie-core | 0 |
| `wp_matomo_changes` | nie-core | 16 |
| `wp_matomo_custom_dimensions` | nie-core | 0 |
| `wp_matomo_goal` | nie-core | 0 |
| `wp_matomo_locks` | nie-core | 0 |
| `wp_matomo_log_action` | nie-core | 542 |
| `wp_matomo_log_bot_request` | nie-core | 0 |
| `wp_matomo_log_conversion` | nie-core | 0 |
| `wp_matomo_log_conversion_item` | nie-core | 0 |
| `wp_matomo_log_link_visit_action` | nie-core | 8271 |
| `wp_matomo_log_profiling` | nie-core | 0 |
| `wp_matomo_log_visit` | nie-core | 588 |
| `wp_matomo_logger_message` | nie-core | 0 |
| `wp_matomo_option` | nie-core | 174 |
| `wp_matomo_plugin_setting` | nie-core | 4 |
| `wp_matomo_privacy_logdata_anonymizations` | nie-core | 0 |
| `wp_matomo_report` | nie-core | 1 |
| `wp_matomo_report_subscriptions` | nie-core | 2 |
| `wp_matomo_segment` | nie-core | 0 |
| `wp_matomo_sequence` | nie-core | 7 |
| `wp_matomo_session` | nie-core | 1 |
| `wp_matomo_site` | nie-core | 1 |
| `wp_matomo_site_setting` | nie-core | 1 |
| `wp_matomo_site_url` | nie-core | 0 |
| `wp_matomo_tagmanager_container` | nie-core | 0 |
| `wp_matomo_tagmanager_container_release` | nie-core | 0 |
| `wp_matomo_tagmanager_container_version` | nie-core | 0 |
| `wp_matomo_tagmanager_tag` | nie-core | 0 |
| `wp_matomo_tagmanager_trigger` | nie-core | 0 |
| `wp_matomo_tagmanager_variable` | nie-core | 0 |
| `wp_matomo_tracking_failure` | nie-core | 0 |
| `wp_matomo_twofactor_recovery_code` | nie-core | 0 |
| `wp_matomo_user` | nie-core | 3 |
| `wp_matomo_user_dashboard` | nie-core | 0 |
| `wp_matomo_user_language` | nie-core | 3 |
| `wp_matomo_user_token_auth` | nie-core | 23 |
| `wp_options` | core | 501 |
| `wp_postmeta` | core | 2417 |
| `wp_posts` | core | 456 |
| `wp_term_relationships` | core | 17 |
| `wp_term_taxonomy` | core | 4 |
| `wp_termmeta` | core | 0 |
| `wp_terms` | core | 4 |
| `wp_usermeta` | core | 6046 |
| `wp_users` | core | 42 |
| `wp_wfauditevents` | nie-core | 0 |
| `wp_wfblockediplog` | nie-core | 20 |
| `wp_wfblocks7` | nie-core | 0 |
| `wp_wfconfig` | nie-core | 287 |
| `wp_wfcrawlers` | nie-core | 0 |
| `wp_wffilechanges` | nie-core | 0 |
| `wp_wffilemods` | nie-core | 17114 |
| `wp_wfhits` | nie-core | 542 |
| `wp_wfhoover` | nie-core | 0 |
| `wp_wfissues` | nie-core | 11 |
| `wp_wfknownfilelist` | nie-core | 17115 |
| `wp_wflivetraffichuman` | nie-core | 0 |
| `wp_wflocs` | nie-core | 1 |
| `wp_wflogins` | nie-core | 637 |
| `wp_wfls_2fa_secrets` | nie-core | 0 |
| `wp_wfls_passkeys` | nie-core | 0 |
| `wp_wfls_role_counts` | nie-core | 0 |
| `wp_wfls_settings` | nie-core | 39 |
| `wp_wfnotifications` | nie-core | 4 |
| `wp_wfpendingissues` | nie-core | 0 |
| `wp_wfreversecache` | nie-core | 1 |
| `wp_wfsecurityevents` | nie-core | 0 |
| `wp_wfsnipcache` | nie-core | 0 |
| `wp_wfstatus` | nie-core | 913 |
| `wp_wftrafficrates` | nie-core | 0 |
| `wp_wfwaffailures` | nie-core | 0 |

## 3. Tabele nie-core: pełna struktura (`SHOW CREATE TABLE`)

Tabele core WordPress (`wp_commentmeta`, `wp_comments`, `wp_links`, `wp_options`, `wp_postmeta`, `wp_posts`, `wp_term_relationships`, `wp_term_taxonomy`, `wp_termmeta`, `wp_terms`, `wp_usermeta`, `wp_users`) są pominięte.

### `wp_aqs_activity`

```sql
CREATE TABLE `wp_aqs_activity` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `event` varchar(32) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `detail` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `user_event` (`user_id`,`event`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_catalog_products`

```sql
CREATE TABLE `wp_aqs_catalog_products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int unsigned NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `image_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price_note` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `availability` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'available',
  `contact_type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `contact_value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `click_count` int unsigned NOT NULL DEFAULT '0',
  `contact_click_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop` (`shop_id`),
  KEY `idx_slug` (`shop_id`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

```

### `wp_aqs_catalog_views`

```sql
CREATE TABLE `wp_aqs_catalog_views` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `shop_id` bigint unsigned NOT NULL,
  `logged_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_hash` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_logged_at` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

```

### `wp_aqs_shop_audit_log`

```sql
CREATE TABLE `wp_aqs_shop_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `object_type` varchar(32) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `object_id` bigint unsigned NOT NULL,
  `old_value` longtext COLLATE utf8mb4_unicode_520_ci,
  `new_value` longtext COLLATE utf8mb4_unicode_520_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_type`,`object_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_shop_billing_monthly`

```sql
CREATE TABLE `wp_aqs_shop_billing_monthly` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `period` char(7) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `avg_products` decimal(7,2) NOT NULL DEFAULT '0.00',
  `avg_internal` decimal(7,2) NOT NULL DEFAULT '0.00',
  `active_days` smallint unsigned NOT NULL DEFAULT '0',
  `rolled_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shop_period` (`shop_id`,`period`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_period` (`period`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_shop_billing_snapshots`

```sql
CREATE TABLE `wp_aqs_shop_billing_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `sample_date` date NOT NULL,
  `products_count` int unsigned NOT NULL DEFAULT '0',
  `internal_count` int unsigned NOT NULL DEFAULT '0',
  `sampled_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shop_date` (`shop_id`,`sample_date`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_sample_date` (`sample_date`)
) ENGINE=InnoDB AUTO_INCREMENT=479 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_shop_buffer`

```sql
CREATE TABLE `wp_aqs_shop_buffer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `aqs_db` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `aqs_record_id` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `record_name` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `variant` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `status` enum('draft','published') COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at` datetime DEFAULT NULL,
  `draft_lock` varchar(150) COLLATE utf8mb4_unicode_520_ci GENERATED ALWAYS AS ((case when (`status` = _utf8mb4'draft') then concat(`shop_id`,_utf8mb4'|',`aqs_db`,_utf8mb4'|',`aqs_record_id`,_utf8mb4'|',coalesce(`variant`,_utf8mb4'')) else NULL end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_draft_lock` (`draft_lock`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_status` (`status`),
  KEY `idx_shop_status` (`shop_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=216 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_shop_clicks`

```sql
CREATE TABLE `wp_aqs_shop_clicks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `shop_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `clicked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_hash` varchar(64) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_clicked_at` (`clicked_at`)
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_shop_notifications_log`

```sql
CREATE TABLE `wp_aqs_shop_notifications_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_shop_products`

```sql
CREATE TABLE `wp_aqs_shop_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `aqs_db` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `aqs_record_id` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `record_name` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `variant` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `last_updated` datetime NOT NULL,
  `click_count` int unsigned NOT NULL DEFAULT '0',
  `row_checksum` varchar(64) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `url_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'unchecked',
  `url_status_code` int unsigned DEFAULT NULL,
  `url_last_checked` datetime DEFAULT NULL,
  `url_final_url` varchar(2048) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `url_warning` text COLLATE utf8mb4_unicode_520_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_aqs_record` (`aqs_db`,`aqs_record_id`),
  KEY `idx_url_status` (`url_status`),
  KEY `idx_last_updated` (`last_updated`)
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_status_log`

```sql
CREATE TABLE `wp_aqs_status_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `changed_by` bigint unsigned NOT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `note` text COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_subscriptions`

```sql
CREATE TABLE `wp_aqs_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'trial',
  `trial_start` datetime NOT NULL,
  `trial_end` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_520_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=696 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_user_journal`

```sql
CREATE TABLE `wp_aqs_user_journal` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tank_id` bigint unsigned NOT NULL,
  `entry_date` date NOT NULL,
  `content` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tank_id` (`tank_id`),
  KEY `idx_entry_date` (`entry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_user_parameters`

```sql
CREATE TABLE `wp_aqs_user_parameters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tank_id` bigint unsigned NOT NULL,
  `measured_at` date NOT NULL,
  `ph` decimal(4,2) DEFAULT NULL,
  `temp_c` decimal(4,1) DEFAULT NULL,
  `kh` decimal(5,1) DEFAULT NULL,
  `gh` decimal(5,1) DEFAULT NULL,
  `no3` decimal(6,1) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `po4` decimal(6,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tank_id` (`tank_id`),
  KEY `idx_measured_at` (`measured_at`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_user_reminders`

```sql
CREATE TABLE `wp_aqs_user_reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tank_id` bigint unsigned NOT NULL,
  `reminder_type` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT '0',
  `interval_days` smallint unsigned DEFAULT NULL,
  `next_due` date NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tank_id` (`tank_id`),
  KEY `idx_next_due` (`next_due`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqs_user_tanks`

```sql
CREATE TABLE `wp_aqs_user_tanks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `volume_liters` smallint unsigned NOT NULL,
  `tank_type` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_520_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `size_band` varchar(40) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `fish_size_band` varchar(40) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `temp_band` varchar(60) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `ph_band` varchar(60) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_audit`

```sql
CREATE TABLE `wp_aqss_audit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `actor_id` bigint unsigned NOT NULL DEFAULT '0',
  `action` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `object_type` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `object_id` bigint unsigned NOT NULL DEFAULT '0',
  `before_json` text COLLATE utf8mb4_unicode_520_ci,
  `after_json` text COLLATE utf8mb4_unicode_520_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `object` (`object_type`,`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_buffer`

```sql
CREATE TABLE `wp_aqss_buffer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `item_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `aqs_db` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `aqs_record_id` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `display_name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `variant` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `scientific_name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `other_desc` text COLLATE utf8mb4_unicode_520_ci,
  `price` decimal(10,2) NOT NULL,
  `pool_qty` int unsigned NOT NULL DEFAULT '0',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shop` (`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_locations`

```sql
CREATE TABLE `wp_aqss_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `voivodeship` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `city_id` bigint unsigned DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `type_active` (`type`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_messages`

```sql
CREATE TABLE `wp_aqss_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint unsigned NOT NULL,
  `author_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `author_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_offers`

```sql
CREATE TABLE `wp_aqss_offers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `item_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `aqs_db` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `aqs_record_id` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `display_name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `scientific_name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `other_desc` text COLLATE utf8mb4_unicode_520_ci,
  `price` decimal(10,2) NOT NULL,
  `pool_qty` int unsigned NOT NULL DEFAULT '0',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'oczekuje_moderacji',
  `mode` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'ZGLOSZENIE',
  `moderation_reason` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `variant` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `pool_reserved` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `shop_status` (`shop_id`,`status`),
  KEY `record` (`aqs_db`,`aqs_record_id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_request_events`

```sql
CREATE TABLE `wp_aqss_request_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint unsigned NOT NULL,
  `from_state` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `to_state` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `actor_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `actor_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta` text COLLATE utf8mb4_unicode_520_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_requests`

```sql
CREATE TABLE `wp_aqss_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(20) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `shop_id` bigint unsigned NOT NULL,
  `offer_id` bigint unsigned DEFAULT NULL,
  `snap_name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `snap_price` decimal(10,2) NOT NULL,
  `qty_requested` int unsigned NOT NULL,
  `qty_confirmed` int unsigned DEFAULT NULL,
  `state` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `payment_state` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'NIE_DOTYCZY',
  `contact_name` varchar(120) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `contact_phone` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `pickup_from` datetime DEFAULT NULL,
  `pickup_to` datetime DEFAULT NULL,
  `pickup_code` varchar(12) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `code_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `code_locked_until` datetime DEFAULT NULL,
  `proposal` text COLLATE utf8mb4_unicode_520_ci,
  `reason_code` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `state_changed_at` datetime NOT NULL,
  `anonymized_at` datetime DEFAULT NULL,
  `basket` varchar(40) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `number` (`number`),
  UNIQUE KEY `pickup_code` (`pickup_code`),
  KEY `shop_state` (`shop_id`,`state`),
  KEY `user_id` (`user_id`),
  KEY `basket` (`basket`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_shop_members`

```sql
CREATE TABLE `wp_aqss_shop_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'aktywny',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_user` (`shop_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_shops`

```sql
CREATE TABLE `wp_aqss_shops` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'w_weryfikacji',
  `location_id` bigint unsigned DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `hours` text COLLATE utf8mb4_unicode_520_ci,
  `phone` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `email` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `website` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `description` text COLLATE utf8mb4_unicode_520_ci,
  `specialties` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `data_aktywacji` datetime DEFAULT NULL,
  `category` varchar(1) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `rating` tinyint unsigned DEFAULT NULL,
  `rating_sources` text COLLATE utf8mb4_unicode_520_ci,
  `rating_date` datetime DEFAULT NULL,
  `rating_note` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `location_id` (`location_id`),
  KEY `owner_id` (`owner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_stats_daily`

```sql
CREATE TABLE `wp_aqss_stats_daily` (
  `day` date NOT NULL,
  `shop_id` bigint unsigned NOT NULL,
  `event` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `cnt` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`day`,`shop_id`,`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_subscriptions`

```sql
CREATE TABLE `wp_aqss_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'aktywny',
  `activated_at` datetime DEFAULT NULL,
  `extra_tanks` int unsigned NOT NULL DEFAULT '0',
  `note` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_id` (`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_aqss_verifications`

```sql
CREATE TABLE `wp_aqss_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `rep_name` varchar(120) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `rep_email` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `nip` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `docs_ref` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `decision` varchar(20) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_cmplz_cookiebanners`

```sql
CREATE TABLE `wp_cmplz_cookiebanners` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `title` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `banner_version` int NOT NULL,
  `default` int NOT NULL,
  `position` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `checkbox_style` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `use_logo` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `logo_attachment_id` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `close_button` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `revoke` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `manage_consent_options` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `header` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `dismiss` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `save_preferences` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `view_preferences` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `category_functional` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `category_all` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `category_stats` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `category_prefs` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `accept` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `message_optin` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `use_categories` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `disable_cookiebanner` int NOT NULL,
  `banner_width` int NOT NULL,
  `soft_cookiewall` int NOT NULL,
  `dismiss_on_scroll` int NOT NULL,
  `dismiss_on_timeout` int NOT NULL,
  `dismiss_timeout` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `accept_informational` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `message_optout` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `use_custom_cookie_css` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `custom_css` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `statistics` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `functional_text` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `statistics_text` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `statistics_text_anonymous` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `preferences_text` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `marketing_text` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `colorpalette_background` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `colorpalette_text` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `colorpalette_toggles` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `colorpalette_border_radius` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `border_width` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `font_size` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `colorpalette_button_accept` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `colorpalette_button_deny` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `colorpalette_button_settings` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `buttons_border_radius` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `animation` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `use_box_shadow` int NOT NULL,
  `header_footer_shadow` int NOT NULL,
  `hide_preview` int NOT NULL,
  `disable_width_correction` int NOT NULL,
  `legal_documents` int NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_cmplz_cookies`

```sql
CREATE TABLE `wp_cmplz_cookies` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `slug` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `sync` int NOT NULL,
  `ignored` int NOT NULL,
  `retention` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `type` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `serviceID` int NOT NULL,
  `cookieFunction` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `collectedPersonalData` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `purpose` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `language` varchar(6) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `isTranslationFrom` int NOT NULL,
  `isOwnDomainCookie` int NOT NULL,
  `domain` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `deleted` int NOT NULL,
  `isMembersOnly` int NOT NULL,
  `showOnPolicy` int NOT NULL,
  `lastUpdatedDate` int NOT NULL,
  `lastAddDate` int NOT NULL,
  `firstAddDate` int NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_cmplz_dnsmpd`

```sql
CREATE TABLE `wp_cmplz_dnsmpd` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `region` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `global_optout` int NOT NULL,
  `cross_context` int NOT NULL,
  `limit_sensitive` int NOT NULL,
  `request_for_access` int NOT NULL,
  `right_to_be_forgotten` int NOT NULL,
  `right_to_data_portability` int NOT NULL,
  `request_date` int NOT NULL,
  `resolved` int NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_cmplz_services`

```sql
CREATE TABLE `wp_cmplz_services` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `slug` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `serviceType` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `category` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `thirdParty` int NOT NULL,
  `sharesData` int NOT NULL,
  `secondParty` int NOT NULL,
  `privacyStatementURL` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `language` varchar(6) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `isTranslationFrom` int NOT NULL,
  `sync` int NOT NULL,
  `lastUpdatedDate` int NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_fsmpt_email_logs`

```sql
CREATE TABLE `wp_fsmpt_email_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int unsigned DEFAULT NULL,
  `to` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `from` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `body` longtext COLLATE utf8mb4_unicode_520_ci,
  `headers` longtext COLLATE utf8mb4_unicode_520_ci,
  `attachments` longtext COLLATE utf8mb4_unicode_520_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_520_ci DEFAULT 'pending',
  `response` text COLLATE utf8mb4_unicode_520_ci,
  `extra` text COLLATE utf8mb4_unicode_520_ci,
  `retries` int unsigned DEFAULT '0',
  `resent_count` int unsigned DEFAULT '0',
  `source` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=477 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_litespeed_avatar`

```sql
CREATE TABLE `wp_litespeed_avatar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(1000) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `md5` varchar(128) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `dateline` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `md5` (`md5`),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci

```

### `wp_litespeed_url`

```sql
CREATE TABLE `wp_litespeed_url` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `url` varchar(500) COLLATE utf8mb3_unicode_ci NOT NULL,
  `cache_tags` varchar(1000) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`(191)),
  KEY `cache_tags` (`cache_tags`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci

```

### `wp_litespeed_url_file`

```sql
CREATE TABLE `wp_litespeed_url_file` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `url_id` bigint NOT NULL,
  `vary` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '' COMMENT 'md5 of final vary',
  `filename` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '' COMMENT 'md5 of file content',
  `type` tinyint NOT NULL COMMENT 'css=1,js=2,ccss=3,ucss=4',
  `mobile` tinyint NOT NULL COMMENT 'mobile=1',
  `webp` tinyint NOT NULL COMMENT 'webp=1',
  `expired` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `filename` (`filename`),
  KEY `type` (`type`),
  KEY `url_id_2` (`url_id`,`vary`,`type`),
  KEY `filename_2` (`filename`,`expired`),
  KEY `url_id` (`url_id`,`expired`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci

```

### `wp_matomo_access`

```sql
CREATE TABLE `wp_matomo_access` (
  `idaccess` int unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned NOT NULL,
  `access` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`idaccess`),
  KEY `index_loginidsite` (`login`,`idsite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_annotations`

```sql
CREATE TABLE `wp_matomo_annotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idsite` int unsigned NOT NULL,
  `date` datetime NOT NULL,
  `note` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `starred` tinyint(1) NOT NULL DEFAULT '0',
  `user` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index_idsite_date` (`idsite`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_blob_2026_01`

```sql
CREATE TABLE `wp_matomo_archive_blob_2026_01` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` longblob,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_blob_2026_06`

```sql
CREATE TABLE `wp_matomo_archive_blob_2026_06` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` longblob,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_blob_2026_07`

```sql
CREATE TABLE `wp_matomo_archive_blob_2026_07` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` longblob,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_blob_2026_08`

```sql
CREATE TABLE `wp_matomo_archive_blob_2026_08` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` longblob,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_blob_2026_09`

```sql
CREATE TABLE `wp_matomo_archive_blob_2026_09` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` longblob,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_blob_2026_10`

```sql
CREATE TABLE `wp_matomo_archive_blob_2026_10` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` longblob,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_blob_2026_11`

```sql
CREATE TABLE `wp_matomo_archive_blob_2026_11` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` longblob,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_invalidations`

```sql
CREATE TABLE `wp_matomo_archive_invalidations` (
  `idinvalidation` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idarchive` int unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned NOT NULL,
  `date1` date NOT NULL,
  `date2` date NOT NULL,
  `period` tinyint unsigned NOT NULL,
  `ts_invalidated` datetime DEFAULT NULL,
  `ts_started` datetime DEFAULT NULL,
  `status` tinyint unsigned DEFAULT '0',
  `report` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `processing_host` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `process_id` varchar(15) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`idinvalidation`),
  KEY `index_idsite_dates_period_name` (`idsite`,`date1`,`period`)
) ENGINE=InnoDB AUTO_INCREMENT=8607 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_numeric_2026_01`

```sql
CREATE TABLE `wp_matomo_archive_numeric_2026_01` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_idsite_dates_period` (`idsite`,`date1`,`date2`,`period`,`name`(6)),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_numeric_2026_06`

```sql
CREATE TABLE `wp_matomo_archive_numeric_2026_06` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_idsite_dates_period` (`idsite`,`date1`,`date2`,`period`,`name`(6)),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_numeric_2026_07`

```sql
CREATE TABLE `wp_matomo_archive_numeric_2026_07` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_idsite_dates_period` (`idsite`,`date1`,`date2`,`period`,`name`(6)),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_numeric_2026_08`

```sql
CREATE TABLE `wp_matomo_archive_numeric_2026_08` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_idsite_dates_period` (`idsite`,`date1`,`date2`,`period`,`name`(6)),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_numeric_2026_09`

```sql
CREATE TABLE `wp_matomo_archive_numeric_2026_09` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_idsite_dates_period` (`idsite`,`date1`,`date2`,`period`,`name`(6)),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_numeric_2026_10`

```sql
CREATE TABLE `wp_matomo_archive_numeric_2026_10` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_idsite_dates_period` (`idsite`,`date1`,`date2`,`period`,`name`(6)),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archive_numeric_2026_11`

```sql
CREATE TABLE `wp_matomo_archive_numeric_2026_11` (
  `idarchive` int unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned DEFAULT NULL,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `period` tinyint unsigned DEFAULT NULL,
  `ts_archived` datetime DEFAULT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`idarchive`,`name`),
  KEY `index_idsite_dates_period` (`idsite`,`date1`,`date2`,`period`,`name`(6)),
  KEY `index_period_archived` (`period`,`ts_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_archiving_metrics`

```sql
CREATE TABLE `wp_matomo_archiving_metrics` (
  `metadataid` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idarchive` bigint unsigned NOT NULL,
  `idsite` int unsigned NOT NULL,
  `archive_name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `date1` date NOT NULL,
  `date2` date NOT NULL,
  `period` tinyint unsigned NOT NULL,
  `ts_started` datetime NOT NULL,
  `ts_finished` datetime NOT NULL,
  `total_time` bigint unsigned NOT NULL,
  `total_time_exclusive` bigint unsigned NOT NULL,
  `is_temporary` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`metadataid`),
  KEY `index_idarchive` (`idarchive`),
  KEY `index_idsite_archive_name` (`idsite`,`archive_name`),
  KEY `index_idsite_date1_period` (`idsite`,`date1`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_brute_force_log`

```sql
CREATE TABLE `wp_matomo_brute_force_log` (
  `id_brute_force_log` bigint NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(60) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `attempted_at` datetime NOT NULL,
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`id_brute_force_log`),
  KEY `index_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_changes`

```sql
CREATE TABLE `wp_matomo_changes` (
  `idchange` int unsigned NOT NULL AUTO_INCREMENT,
  `created_time` datetime NOT NULL,
  `plugin_name` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_520_ci,
  `link_name` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `link` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`idchange`),
  UNIQUE KEY `unique_plugin_version_title` (`plugin_name`,`version`,`title`(100))
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_custom_dimensions`

```sql
CREATE TABLE `wp_matomo_custom_dimensions` (
  `idcustomdimension` bigint unsigned NOT NULL,
  `idsite` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `index` smallint unsigned NOT NULL,
  `scope` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `extractions` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `case_sensitive` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`idcustomdimension`,`idsite`),
  UNIQUE KEY `uniq_hash` (`idsite`,`scope`,`index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_goal`

```sql
CREATE TABLE `wp_matomo_goal` (
  `idsite` int NOT NULL,
  `idgoal` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `match_attribute` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `pattern` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `pattern_type` varchar(25) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `case_sensitive` tinyint NOT NULL,
  `allow_multiple` tinyint NOT NULL,
  `revenue` double NOT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
  `event_value_as_revenue` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`idsite`,`idgoal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_locks`

```sql
CREATE TABLE `wp_matomo_locks` (
  `key` varchar(70) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `expiry_time` bigint unsigned DEFAULT '9999999999',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_log_action`

```sql
CREATE TABLE `wp_matomo_log_action` (
  `idaction` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(4096) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `hash` int unsigned NOT NULL,
  `type` tinyint unsigned DEFAULT NULL,
  `url_prefix` tinyint DEFAULT NULL,
  PRIMARY KEY (`idaction`),
  KEY `index_type_hash` (`type`,`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=543 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_log_bot_request`

```sql
CREATE TABLE `wp_matomo_log_bot_request` (
  `idrequest` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idsite` int unsigned NOT NULL,
  `server_time` datetime NOT NULL,
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `idaction_url` int unsigned DEFAULT NULL,
  `bot_name` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `bot_type` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `http_status_code` smallint unsigned DEFAULT NULL,
  `response_size_bytes` int unsigned DEFAULT NULL,
  `response_time_ms` int unsigned DEFAULT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`idrequest`),
  KEY `index_idsite_server_time` (`idsite`,`server_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_log_conversion`

```sql
CREATE TABLE `wp_matomo_log_conversion` (
  `idvisit` bigint unsigned NOT NULL,
  `idsite` int unsigned NOT NULL,
  `idvisitor` binary(8) NOT NULL,
  `server_time` datetime NOT NULL,
  `idaction_url` int unsigned DEFAULT NULL,
  `idlink_va` bigint unsigned DEFAULT NULL,
  `idgoal` int NOT NULL,
  `buster` int unsigned NOT NULL,
  `idorder` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `items` smallint unsigned DEFAULT NULL,
  `url` varchar(4096) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `revenue` float DEFAULT NULL,
  `revenue_shipping` double DEFAULT NULL,
  `revenue_subtotal` double DEFAULT NULL,
  `revenue_tax` double DEFAULT NULL,
  `revenue_discount` double DEFAULT NULL,
  `pageviews_before` smallint unsigned DEFAULT NULL,
  `custom_dimension_1` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_2` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_3` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_4` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_5` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `visitor_returning` tinyint(1) DEFAULT NULL,
  `visitor_seconds_since_first` int unsigned DEFAULT NULL,
  `visitor_seconds_since_order` int unsigned DEFAULT NULL,
  `visitor_count_visits` int unsigned NOT NULL DEFAULT '0',
  `referer_keyword` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `referer_name` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `referer_type` tinyint unsigned DEFAULT NULL,
  `config_browser_name` varchar(40) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `config_client_type` tinyint(1) DEFAULT NULL,
  `config_device_brand` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `config_device_model` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `config_device_type` tinyint DEFAULT NULL,
  `location_city` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `location_country` char(3) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `location_latitude` decimal(9,6) DEFAULT NULL,
  `location_longitude` decimal(9,6) DEFAULT NULL,
  `location_region` char(3) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`idvisit`,`idgoal`,`buster`),
  UNIQUE KEY `unique_idsite_idorder` (`idsite`,`idorder`),
  KEY `index_idsite_datetime` (`idsite`,`server_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_log_conversion_item`

```sql
CREATE TABLE `wp_matomo_log_conversion_item` (
  `idsite` int unsigned NOT NULL,
  `idvisitor` binary(8) NOT NULL,
  `server_time` datetime NOT NULL,
  `idvisit` bigint unsigned NOT NULL,
  `idorder` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idaction_sku` int unsigned NOT NULL,
  `idaction_name` int unsigned NOT NULL,
  `idaction_category` int unsigned NOT NULL,
  `idaction_category2` int unsigned NOT NULL,
  `idaction_category3` int unsigned NOT NULL,
  `idaction_category4` int unsigned NOT NULL,
  `idaction_category5` int unsigned NOT NULL,
  `price` double NOT NULL,
  `quantity` int unsigned NOT NULL,
  `deleted` tinyint unsigned NOT NULL,
  PRIMARY KEY (`idvisit`,`idorder`,`idaction_sku`),
  KEY `index_idsite_servertime` (`idsite`,`server_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_log_link_visit_action`

```sql
CREATE TABLE `wp_matomo_log_link_visit_action` (
  `idlink_va` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idsite` int unsigned NOT NULL,
  `idvisitor` binary(8) NOT NULL,
  `idvisit` bigint unsigned NOT NULL,
  `idaction_url_ref` int unsigned DEFAULT '0',
  `idaction_name_ref` int unsigned DEFAULT NULL,
  `custom_float` double DEFAULT NULL,
  `pageview_position` mediumint unsigned DEFAULT NULL,
  `time_spent` int unsigned DEFAULT NULL,
  `custom_dimension_1` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_2` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_3` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_4` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_5` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `server_time` datetime NOT NULL,
  `idpageview` char(6) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `idaction_name` int unsigned DEFAULT NULL,
  `idaction_url` int unsigned DEFAULT NULL,
  `search_cat` varchar(200) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `search_count` int unsigned DEFAULT NULL,
  `time_spent_ref_action` int unsigned DEFAULT NULL,
  `idaction_product_cat` int unsigned DEFAULT NULL,
  `idaction_product_cat2` int unsigned DEFAULT NULL,
  `idaction_product_cat3` int unsigned DEFAULT NULL,
  `idaction_product_cat4` int unsigned DEFAULT NULL,
  `idaction_product_cat5` int unsigned DEFAULT NULL,
  `idaction_product_name` int unsigned DEFAULT NULL,
  `product_price` double DEFAULT NULL,
  `idaction_product_sku` int unsigned DEFAULT NULL,
  `idaction_event_action` int unsigned DEFAULT NULL,
  `idaction_event_category` int unsigned DEFAULT NULL,
  `idaction_content_interaction` int unsigned DEFAULT NULL,
  `idaction_content_name` int unsigned DEFAULT NULL,
  `idaction_content_piece` int unsigned DEFAULT NULL,
  `idaction_content_target` int unsigned DEFAULT NULL,
  `time_dom_completion` mediumint unsigned DEFAULT NULL,
  `time_dom_processing` mediumint unsigned DEFAULT NULL,
  `time_network` mediumint unsigned DEFAULT NULL,
  `time_on_load` mediumint unsigned DEFAULT NULL,
  `time_server` mediumint unsigned DEFAULT NULL,
  `time_transfer` mediumint unsigned DEFAULT NULL,
  PRIMARY KEY (`idlink_va`),
  KEY `index_idvisit` (`idvisit`),
  KEY `index_idsite_servertime` (`idsite`,`server_time`)
) ENGINE=InnoDB AUTO_INCREMENT=8272 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_log_profiling`

```sql
CREATE TABLE `wp_matomo_log_profiling` (
  `query` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `count` int unsigned DEFAULT NULL,
  `sum_time_ms` float DEFAULT NULL,
  `idprofiling` bigint unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`idprofiling`),
  UNIQUE KEY `query` (`query`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_log_visit`

```sql
CREATE TABLE `wp_matomo_log_visit` (
  `idvisit` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idsite` int unsigned NOT NULL,
  `idvisitor` binary(8) NOT NULL,
  `visit_last_action_time` datetime NOT NULL,
  `config_id` binary(8) NOT NULL,
  `location_ip` varbinary(16) NOT NULL,
  `last_idlink_va` bigint unsigned DEFAULT NULL,
  `custom_dimension_1` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_2` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_3` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_4` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `custom_dimension_5` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `profilable` tinyint(1) DEFAULT NULL,
  `user_id` varchar(200) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `visit_first_action_time` datetime NOT NULL,
  `visit_goal_buyer` tinyint(1) DEFAULT NULL,
  `visit_goal_converted` tinyint(1) DEFAULT NULL,
  `visitor_returning` tinyint(1) DEFAULT NULL,
  `visitor_seconds_since_first` int unsigned DEFAULT NULL,
  `visitor_seconds_since_order` int unsigned DEFAULT NULL,
  `visitor_count_visits` int unsigned NOT NULL DEFAULT '0',
  `visit_entry_idaction_name` int unsigned DEFAULT NULL,
  `visit_entry_idaction_url` int unsigned DEFAULT NULL,
  `visit_exit_idaction_name` int unsigned DEFAULT NULL,
  `visit_exit_idaction_url` int unsigned DEFAULT '0',
  `visit_total_actions` int unsigned DEFAULT NULL,
  `visit_total_interactions` mediumint unsigned DEFAULT '0',
  `visit_total_searches` smallint unsigned DEFAULT NULL,
  `referer_keyword` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `referer_name` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `referer_type` tinyint unsigned DEFAULT NULL,
  `referer_url` varchar(1500) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `location_browser_lang` varchar(20) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `config_browser_engine` varchar(10) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `config_browser_name` varchar(40) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `config_browser_version` varchar(20) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `config_client_type` tinyint(1) DEFAULT NULL,
  `config_device_brand` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `config_device_model` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `config_device_type` tinyint DEFAULT NULL,
  `config_os` char(3) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `config_os_version` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `visit_total_events` int unsigned DEFAULT NULL,
  `visitor_localtime` time DEFAULT NULL,
  `visitor_seconds_since_last` int unsigned DEFAULT NULL,
  `config_resolution` varchar(18) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `config_cookie` tinyint(1) DEFAULT NULL,
  `config_flash` tinyint(1) DEFAULT NULL,
  `config_java` tinyint(1) DEFAULT NULL,
  `config_pdf` tinyint(1) DEFAULT NULL,
  `config_quicktime` tinyint(1) DEFAULT NULL,
  `config_realplayer` tinyint(1) DEFAULT NULL,
  `config_silverlight` tinyint(1) DEFAULT NULL,
  `config_windowsmedia` tinyint(1) DEFAULT NULL,
  `ai_agent_name` varchar(40) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `visit_total_time` int unsigned NOT NULL,
  `location_city` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `location_country` char(3) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `location_latitude` decimal(9,6) DEFAULT NULL,
  `location_longitude` decimal(9,6) DEFAULT NULL,
  `location_region` char(3) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`idvisit`),
  KEY `index_idsite_config_datetime` (`idsite`,`config_id`,`visit_last_action_time`),
  KEY `index_idsite_datetime` (`idsite`,`visit_last_action_time`),
  KEY `index_idsite_idvisitor_time` (`idsite`,`idvisitor`,`visit_last_action_time`)
) ENGINE=InnoDB AUTO_INCREMENT=589 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_logger_message`

```sql
CREATE TABLE `wp_matomo_logger_message` (
  `idlogger_message` int unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT NULL,
  `level` varchar(16) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`idlogger_message`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_option`

```sql
CREATE TABLE `wp_matomo_option` (
  `option_name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `option_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `autoload` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`option_name`),
  KEY `autoload` (`autoload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_plugin_setting`

```sql
CREATE TABLE `wp_matomo_plugin_setting` (
  `plugin_name` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `setting_name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `json_encoded` tinyint unsigned NOT NULL DEFAULT '0',
  `user_login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `idplugin_setting` bigint unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`idplugin_setting`),
  KEY `plugin_name` (`plugin_name`,`user_login`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_privacy_logdata_anonymizations`

```sql
CREATE TABLE `wp_matomo_privacy_logdata_anonymizations` (
  `idlogdata_anonymization` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idsites` text COLLATE utf8mb4_unicode_520_ci,
  `date_start` datetime NOT NULL,
  `date_end` datetime NOT NULL,
  `anonymize_ip` tinyint unsigned NOT NULL DEFAULT '0',
  `anonymize_location` tinyint unsigned NOT NULL DEFAULT '0',
  `anonymize_userid` tinyint unsigned NOT NULL DEFAULT '0',
  `unset_visit_columns` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `unset_link_visit_action_columns` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `output` mediumtext COLLATE utf8mb4_unicode_520_ci,
  `scheduled_date` datetime DEFAULT NULL,
  `job_start_date` datetime DEFAULT NULL,
  `job_finish_date` datetime DEFAULT NULL,
  `requester` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`idlogdata_anonymization`),
  KEY `job_start_date` (`job_start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_report`

```sql
CREATE TABLE `wp_matomo_report` (
  `idreport` int NOT NULL AUTO_INCREMENT,
  `idsite` int NOT NULL,
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsegment` int DEFAULT NULL,
  `period` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `hour` tinyint NOT NULL DEFAULT '0',
  `type` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `format` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `reports` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `parameters` text COLLATE utf8mb4_unicode_520_ci,
  `ts_created` timestamp NULL DEFAULT NULL,
  `ts_last_sent` timestamp NULL DEFAULT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
  `evolution_graph_within_period` tinyint NOT NULL DEFAULT '0',
  `evolution_graph_period_n` int NOT NULL,
  `period_param` varchar(10) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`idreport`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_report_subscriptions`

```sql
CREATE TABLE `wp_matomo_report_subscriptions` (
  `idreport` int NOT NULL,
  `token` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `ts_subscribed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ts_unsubscribed` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`idreport`,`email`),
  UNIQUE KEY `unique_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_segment`

```sql
CREATE TABLE `wp_matomo_segment` (
  `idsegment` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `definition` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `hash` char(32) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `enable_all_users` tinyint NOT NULL DEFAULT '0',
  `enable_only_idsite` int DEFAULT NULL,
  `auto_archive` tinyint NOT NULL DEFAULT '0',
  `ts_created` timestamp NULL DEFAULT NULL,
  `ts_last_edit` timestamp NULL DEFAULT NULL,
  `starred` tinyint NOT NULL DEFAULT '0',
  `starred_by` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`idsegment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_sequence`

```sql
CREATE TABLE `wp_matomo_sequence` (
  `name` varchar(120) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `value` bigint unsigned NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_session`

```sql
CREATE TABLE `wp_matomo_session` (
  `id` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `modified` int DEFAULT NULL,
  `lifetime` int DEFAULT NULL,
  `data` mediumtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_site`

```sql
CREATE TABLE `wp_matomo_site` (
  `idsite` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(90) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `main_url` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `ts_created` timestamp NULL DEFAULT NULL,
  `ecommerce` tinyint DEFAULT '0',
  `sitesearch` tinyint DEFAULT '1',
  `sitesearch_keyword_parameters` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `sitesearch_category_parameters` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `timezone` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `exclude_unknown_urls` tinyint(1) DEFAULT '0',
  `excluded_ips` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `excluded_parameters` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `excluded_user_agents` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `excluded_referrers` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `group` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `keep_url_fragment` tinyint NOT NULL DEFAULT '0',
  `creator_login` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`idsite`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_site_setting`

```sql
CREATE TABLE `wp_matomo_site_setting` (
  `idsite` int unsigned NOT NULL,
  `plugin_name` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `setting_name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `json_encoded` tinyint unsigned NOT NULL DEFAULT '0',
  `idsite_setting` bigint unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`idsite_setting`),
  KEY `idsite` (`idsite`,`plugin_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_site_url`

```sql
CREATE TABLE `wp_matomo_site_url` (
  `idsite` int unsigned NOT NULL,
  `url` varchar(190) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`idsite`,`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_tagmanager_container`

```sql
CREATE TABLE `wp_matomo_tagmanager_container` (
  `idcontainer` varchar(8) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned NOT NULL,
  `context` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `ignoreGtmDataLayer` tinyint unsigned NOT NULL DEFAULT '0',
  `activelySyncGtmDataLayer` tinyint unsigned NOT NULL DEFAULT '1',
  `isTagFireLimitAllowedInPreviewMode` tinyint unsigned NOT NULL DEFAULT '0',
  `status` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created_date` datetime NOT NULL,
  `updated_date` datetime NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`idcontainer`),
  KEY `idsite` (`idsite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_tagmanager_container_release`

```sql
CREATE TABLE `wp_matomo_tagmanager_container_release` (
  `idcontainerrelease` bigint NOT NULL AUTO_INCREMENT,
  `idcontainer` varchar(8) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idcontainerversion` bigint unsigned NOT NULL,
  `idsite` int unsigned NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `environment` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `release_login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `release_date` datetime NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`idcontainerrelease`),
  KEY `idsite` (`idsite`,`idcontainer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_tagmanager_container_version`

```sql
CREATE TABLE `wp_matomo_tagmanager_container_version` (
  `idcontainerversion` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idcontainer` varchar(8) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `idsite` int unsigned NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `revision` mediumint unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `description` varchar(1000) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `created_date` datetime NOT NULL,
  `updated_date` datetime NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`idcontainerversion`),
  KEY `idcontainer` (`idcontainer`),
  KEY `idsite` (`idsite`,`idcontainer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_tagmanager_tag`

```sql
CREATE TABLE `wp_matomo_tagmanager_tag` (
  `idtag` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idcontainerversion` bigint unsigned NOT NULL,
  `idsite` int unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `parameters` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `fire_trigger_ids` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `block_trigger_ids` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `fire_limit` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'unlimited',
  `priority` smallint unsigned NOT NULL,
  `fire_delay` mediumint unsigned NOT NULL DEFAULT '0',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `updated_date` datetime NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`idtag`),
  KEY `idsite` (`idsite`,`idcontainerversion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_tagmanager_trigger`

```sql
CREATE TABLE `wp_matomo_tagmanager_trigger` (
  `idtrigger` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idcontainerversion` bigint unsigned NOT NULL,
  `idsite` int unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `parameters` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `conditions` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created_date` datetime NOT NULL,
  `updated_date` datetime NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`idtrigger`),
  KEY `idsite` (`idsite`,`idcontainerversion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_tagmanager_variable`

```sql
CREATE TABLE `wp_matomo_tagmanager_variable` (
  `idvariable` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idcontainerversion` bigint unsigned NOT NULL,
  `idsite` int unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `parameters` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `lookup_table` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `default_value` text COLLATE utf8mb4_unicode_520_ci,
  `created_date` datetime NOT NULL,
  `updated_date` datetime NOT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`idvariable`),
  KEY `idsite` (`idsite`,`idcontainerversion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_tracking_failure`

```sql
CREATE TABLE `wp_matomo_tracking_failure` (
  `idsite` bigint unsigned NOT NULL,
  `idfailure` smallint unsigned NOT NULL,
  `date_first_occurred` datetime NOT NULL,
  `request_url` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`idsite`,`idfailure`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_twofactor_recovery_code`

```sql
CREATE TABLE `wp_matomo_twofactor_recovery_code` (
  `idrecoverycode` bigint unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `recovery_code` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`idrecoverycode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_user`

```sql
CREATE TABLE `wp_matomo_user` (
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `twofactor_secret` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `superuser_access` tinyint unsigned NOT NULL DEFAULT '0',
  `date_registered` timestamp NULL DEFAULT NULL,
  `ts_password_modified` timestamp NULL DEFAULT NULL,
  `idchange_last_viewed` int unsigned DEFAULT NULL,
  `invited_by` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `invite_token` varchar(191) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `invite_link_token` varchar(191) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `invite_expired_at` timestamp NULL DEFAULT NULL,
  `invite_accept_at` timestamp NULL DEFAULT NULL,
  `ts_changes_shown` timestamp NULL DEFAULT NULL,
  `ts_last_seen` timestamp NULL DEFAULT NULL,
  `ts_inactivity_notified` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`login`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_user_dashboard`

```sql
CREATE TABLE `wp_matomo_user_dashboard` (
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `iddashboard` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `layout` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`login`,`iddashboard`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_user_language`

```sql
CREATE TABLE `wp_matomo_user_language` (
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `language` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `use_12_hour_clock` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_matomo_user_token_auth`

```sql
CREATE TABLE `wp_matomo_user_token_auth` (
  `idusertokenauth` bigint unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `hash_algo` varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `system_token` tinyint(1) NOT NULL DEFAULT '0',
  `last_used` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_expired` datetime DEFAULT NULL,
  `secure_only` tinyint unsigned NOT NULL DEFAULT '0',
  `ts_rotation_notified` datetime DEFAULT NULL,
  `ts_expiration_warning_notified` datetime DEFAULT NULL,
  PRIMARY KEY (`idusertokenauth`),
  UNIQUE KEY `uniq_password` (`password`)
) ENGINE=InnoDB AUTO_INCREMENT=2112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC

```

### `wp_wfauditevents`

```sql
CREATE TABLE `wp_wfauditevents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  `event_time` double(14,4) NOT NULL,
  `request_id` bigint unsigned NOT NULL,
  `state` enum('new','sending','sent') NOT NULL DEFAULT 'new',
  `state_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfblockediplog`

```sql
CREATE TABLE `wp_wfblockediplog` (
  `IP` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `countryCode` varchar(2) NOT NULL,
  `blockCount` int unsigned NOT NULL DEFAULT '0',
  `unixday` int unsigned NOT NULL,
  `blockType` varchar(50) NOT NULL DEFAULT 'generic',
  PRIMARY KEY (`IP`,`unixday`,`blockType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfblocks7`

```sql
CREATE TABLE `wp_wfblocks7` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` int unsigned NOT NULL DEFAULT '0',
  `IP` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `blockedTime` bigint NOT NULL,
  `reason` varchar(255) NOT NULL,
  `lastAttempt` int unsigned DEFAULT '0',
  `blockedHits` int unsigned DEFAULT '0',
  `expiration` bigint unsigned NOT NULL DEFAULT '0',
  `parameters` text,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `IP` (`IP`),
  KEY `expiration` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfconfig`

```sql
CREATE TABLE `wp_wfconfig` (
  `name` varchar(100) NOT NULL,
  `val` longblob,
  `autoload` enum('no','yes') NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfcrawlers`

```sql
CREATE TABLE `wp_wfcrawlers` (
  `IP` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `patternSig` binary(16) NOT NULL,
  `status` char(8) NOT NULL,
  `lastUpdate` int unsigned NOT NULL,
  `PTR` varchar(255) DEFAULT '',
  PRIMARY KEY (`IP`,`patternSig`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wffilechanges`

```sql
CREATE TABLE `wp_wffilechanges` (
  `filenameHash` char(64) NOT NULL,
  `file` varchar(1000) NOT NULL,
  `md5` char(32) NOT NULL,
  PRIMARY KEY (`filenameHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wffilemods`

```sql
CREATE TABLE `wp_wffilemods` (
  `filenameMD5` binary(16) NOT NULL,
  `filename` varchar(1000) NOT NULL,
  `real_path` text NOT NULL,
  `knownFile` tinyint unsigned NOT NULL,
  `oldMD5` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `newMD5` binary(16) NOT NULL,
  `SHAC` binary(32) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `stoppedOnSignature` varchar(255) NOT NULL DEFAULT '',
  `stoppedOnPosition` int unsigned NOT NULL DEFAULT '0',
  `isSafeFile` varchar(1) NOT NULL DEFAULT '?',
  PRIMARY KEY (`filenameMD5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfhits`

```sql
CREATE TABLE `wp_wfhits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `attackLogTime` double(17,6) unsigned NOT NULL,
  `ctime` double(17,6) unsigned NOT NULL,
  `IP` binary(16) DEFAULT NULL,
  `jsRun` tinyint DEFAULT '0',
  `statusCode` int NOT NULL DEFAULT '200',
  `isGoogle` tinyint NOT NULL,
  `userID` int unsigned NOT NULL,
  `newVisit` tinyint unsigned NOT NULL,
  `URL` text,
  `referer` text,
  `UA` text,
  `action` varchar(64) NOT NULL DEFAULT '',
  `actionDescription` text,
  `actionData` text,
  PRIMARY KEY (`id`),
  KEY `k1` (`ctime`),
  KEY `k2` (`IP`,`ctime`),
  KEY `attackLogTime` (`attackLogTime`)
) ENGINE=InnoDB AUTO_INCREMENT=4697 DEFAULT CHARSET=utf8mb3

```

### `wp_wfhoover`

```sql
CREATE TABLE `wp_wfhoover` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner` text,
  `host` text,
  `path` text,
  `hostKey` varbinary(124) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `k2` (`hostKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfissues`

```sql
CREATE TABLE `wp_wfissues` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `time` int unsigned NOT NULL,
  `lastUpdated` int unsigned NOT NULL,
  `status` varchar(10) NOT NULL,
  `type` varchar(20) NOT NULL,
  `severity` tinyint unsigned NOT NULL,
  `ignoreP` char(32) NOT NULL,
  `ignoreC` char(32) NOT NULL,
  `shortMsg` varchar(255) NOT NULL,
  `longMsg` text,
  `data` text,
  PRIMARY KEY (`id`),
  KEY `lastUpdated` (`lastUpdated`),
  KEY `status` (`status`),
  KEY `ignoreP` (`ignoreP`),
  KEY `ignoreC` (`ignoreC`)
) ENGINE=InnoDB AUTO_INCREMENT=857 DEFAULT CHARSET=utf8mb3

```

### `wp_wfknownfilelist`

```sql
CREATE TABLE `wp_wfknownfilelist` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `path` text NOT NULL,
  `wordpress_path` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17116 DEFAULT CHARSET=utf8mb3

```

### `wp_wflivetraffichuman`

```sql
CREATE TABLE `wp_wflivetraffichuman` (
  `IP` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `identifier` binary(32) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `expiration` int unsigned NOT NULL,
  PRIMARY KEY (`IP`,`identifier`),
  KEY `expiration` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wflocs`

```sql
CREATE TABLE `wp_wflocs` (
  `IP` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `ctime` int unsigned NOT NULL,
  `failed` tinyint unsigned NOT NULL,
  `city` varchar(255) DEFAULT '',
  `region` varchar(255) DEFAULT '',
  `countryName` varchar(255) DEFAULT '',
  `countryCode` char(2) DEFAULT '',
  `lat` float(10,7) DEFAULT '0.0000000',
  `lon` float(10,7) DEFAULT '0.0000000',
  PRIMARY KEY (`IP`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wflogins`

```sql
CREATE TABLE `wp_wflogins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `hitID` int DEFAULT NULL,
  `ctime` double(17,6) unsigned NOT NULL,
  `fail` tinyint unsigned NOT NULL,
  `action` varchar(40) NOT NULL,
  `username` varchar(255) NOT NULL,
  `userID` int unsigned NOT NULL,
  `IP` binary(16) DEFAULT NULL,
  `UA` text,
  PRIMARY KEY (`id`),
  KEY `k1` (`IP`,`fail`),
  KEY `hitID` (`hitID`)
) ENGINE=InnoDB AUTO_INCREMENT=638 DEFAULT CHARSET=utf8mb3

```

### `wp_wfls_2fa_secrets`

```sql
CREATE TABLE `wp_wfls_2fa_secrets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `secret` tinyblob NOT NULL,
  `recovery` blob NOT NULL,
  `ctime` int unsigned NOT NULL,
  `vtime` int unsigned NOT NULL,
  `mode` enum('authenticator') NOT NULL DEFAULT 'authenticator',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3

```

### `wp_wfls_passkeys`

```sql
CREATE TABLE `wp_wfls_passkeys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `credential_id` varbinary(1023) NOT NULL,
  `credential_id_hash` binary(32) NOT NULL,
  `public_key` blob NOT NULL,
  `sign_count` bigint unsigned NOT NULL DEFAULT '0',
  `transports` varchar(255) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `user_handle` varbinary(255) DEFAULT NULL,
  `ctime` int unsigned NOT NULL,
  `mtime` int unsigned NOT NULL,
  `last_used_at` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_id_hash` (`credential_id_hash`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfls_role_counts`

```sql
CREATE TABLE `wp_wfls_role_counts` (
  `serialized_roles` varbinary(255) NOT NULL,
  `two_factor_inactive` tinyint(1) NOT NULL,
  `passkey_inactive` tinyint(1) NOT NULL,
  `user_count` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`serialized_roles`,`two_factor_inactive`,`passkey_inactive`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb3

```

### `wp_wfls_settings`

```sql
CREATE TABLE `wp_wfls_settings` (
  `name` varchar(191) NOT NULL DEFAULT '',
  `value` longblob,
  `autoload` enum('no','yes') NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfnotifications`

```sql
CREATE TABLE `wp_wfnotifications` (
  `id` varchar(32) NOT NULL DEFAULT '',
  `new` tinyint unsigned NOT NULL DEFAULT '1',
  `category` varchar(255) NOT NULL,
  `priority` int NOT NULL DEFAULT '1000',
  `ctime` int unsigned NOT NULL,
  `html` text NOT NULL,
  `links` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfpendingissues`

```sql
CREATE TABLE `wp_wfpendingissues` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `time` int unsigned NOT NULL,
  `lastUpdated` int unsigned NOT NULL,
  `status` varchar(10) NOT NULL,
  `type` varchar(20) NOT NULL,
  `severity` tinyint unsigned NOT NULL,
  `ignoreP` char(32) NOT NULL,
  `ignoreC` char(32) NOT NULL,
  `shortMsg` varchar(255) NOT NULL,
  `longMsg` text,
  `data` text,
  PRIMARY KEY (`id`),
  KEY `lastUpdated` (`lastUpdated`),
  KEY `status` (`status`),
  KEY `ignoreP` (`ignoreP`),
  KEY `ignoreC` (`ignoreC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfreversecache`

```sql
CREATE TABLE `wp_wfreversecache` (
  `IP` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `host` varchar(255) NOT NULL,
  `lastUpdate` int unsigned NOT NULL,
  PRIMARY KEY (`IP`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfsecurityevents`

```sql
CREATE TABLE `wp_wfsecurityevents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  `event_time` double(14,4) NOT NULL,
  `state` enum('new','sending','sent') NOT NULL DEFAULT 'new',
  `state_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfsnipcache`

```sql
CREATE TABLE `wp_wfsnipcache` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `IP` varchar(45) NOT NULL DEFAULT '',
  `expiration` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `body` varchar(255) NOT NULL DEFAULT '',
  `count` int unsigned NOT NULL DEFAULT '0',
  `type` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `expiration` (`expiration`),
  KEY `IP` (`IP`),
  KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb3

```

### `wp_wfstatus`

```sql
CREATE TABLE `wp_wfstatus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ctime` double(17,6) unsigned NOT NULL,
  `level` tinyint unsigned NOT NULL,
  `type` char(5) NOT NULL,
  `msg` varchar(1000) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `k1` (`ctime`),
  KEY `k2` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=13497 DEFAULT CHARSET=utf8mb3

```

### `wp_wftrafficrates`

```sql
CREATE TABLE `wp_wftrafficrates` (
  `eMin` int unsigned NOT NULL,
  `IP` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `hitType` enum('hit','404') NOT NULL DEFAULT 'hit',
  `hits` int unsigned NOT NULL,
  PRIMARY KEY (`eMin`,`IP`,`hitType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

### `wp_wfwaffailures`

```sql
CREATE TABLE `wp_wfwaffailures` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `throwable` text NOT NULL,
  `rule_id` int unsigned DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3

```

## 5. wp_options

### 5.1. Nazwy opcji z fragmentami projektu (nazwa i długość wartości; wartości pominięte)

| option_name | długość wartości |
|---|---:|
| `_transient_aqs_k1k2_b344bdc1-a197-495a-a84c-cd70ec348e56` | 486 |
| `_transient_aqs_k1k2_f5576dff-39d9-4176-bc2e-5992b62066c5` | 486 |
| `_transient_timeout_aqs_k1k2_b344bdc1-a197-495a-a84c-cd70ec348e56` | 10 |
| `_transient_timeout_aqs_k1k2_f5576dff-39d9-4176-bc2e-5992b62066c5` | 10 |
| `aqs_activity_db_version` | 1 |
| `aqs_app_cookie_page_id` | 1 |
| `aqs_app_start_page_id` | 3 |
| `aqs_auth_schema` | 5 |
| `aqs_catalog_db_version` | 5 |
| `aqs_debug_inject` | 50 |
| `aqs_health_state` | 1110 |
| `aqs_health_urls` | 222 |
| `aqs_jj_dir` | 118 |
| `aqs_jj_last_backup` | 146 |
| `aqs_jj_last_result` | 195 |
| `aqs_k1k2_bundled_version` | 6 |
| `aqs_k1k2_dataset_source` | 7 |
| `aqs_k1k2_force_reset_build` | 12 |
| `aqs_monitor_enabled` | 1 |
| `aqs_report_emails` | 17 |
| `aqs_shop_db_version` | 6 |
| `aqs_th_allow` | 1 |
| `aqs_th_last_result` | 1060 |
| `aqs_th_removed` | 454 |
| `aqs_th_smtp` | 200 |
| `aqs_th_to` | 18 |
| `aqs_th_unblock` | 1 |
| `aqs_user_db_version` | 6 |
| `aqss_a53_moderation_off` | 1 |
| `aqss_order_seq` | 1 |
| `aqss_schema` | 1 |
| `aqss_search_page_id` | 3 |
| `aqss_settings` | 240 |

### 5.2. Opcje nieosobowe (wartości)

| option_name | wartość |
|---|---|
| `active_plugins` | `a:13:{i:0;s:27:"fluent-smtp/fluent-smtp.php";i:1;s:33:"aquasmart-auth/aquasmart-auth.php";i:2;s:35:"aquasmart-shops/aquasmart-shops.php";i:3;s:33:"aquasmart-user/aquasmart-user.php";i:4;s:50:"aquasmart_v103/akwasmart-k1k2-r27-final-result.php";i:5;s:47:"better-search-replace/better-search-replace.php";i:6;s:37:"blogger-importer/blogger-importer.php";i:7;s:33:"complianz-gdpr/complianz-gpdr.php";i:8;s:31:"filester/ninja-file-manager.php";i:9;s:35:"litespeed-cache/litespeed-cache.php";i:10;s:17:"matomo/matomo.php";i:11;s:33:"nav-menu-roles/nav-menu-roles.php";i:12;s:41:"wordpress-importer/wordpress-importer.php";}` |
| `template` | `astra` |
| `stylesheet` | `astra` |
| `siteurl` | `https://test.aikwarium.pl` |
| `home` | `https://test.aikwarium.pl` |
| `db_version` | `61833` |

## 6. Użytkownicy

- Wiersze w `wp_users`: **42**.
- Wiersze `wp_usermeta` z `meta_key = wp_capabilities`: **321**. Z tego 42 dotyczą kont istniejących w `wp_users`, a **279 to wiersze osierocone** (`user_id` nieistniejący w `wp_users`).

### 6.1. Role (liczba wierszy wg `wp_capabilities`)

| Rola | Wszystkie wiersze (321) | Tylko istniejące konta (42) |
|---|---:|---:|
| `administrator` | 3 | 3 |
| `aqs_user` | 319 | 40 |
| `aqs_shop` | 17 | 17 |

Role mogą się nakładać (jeden użytkownik może mieć kilka). Pozostałe role WP (editor, author, subscriber, customer itd.) nie występują.

### 6.2. Klucze `aqs_*` w usermeta (nazwa klucza i liczba wierszy)

| meta_key | wiersze |
|---|---:|
| `aqs_account_type` | 318 |
| `aqs_act_dobor` | 6 |
| `aqs_act_dobor_sessions` | 6 |
| `aqs_act_last_login` | 6 |
| `aqs_act_last_open` | 6 |
| `aqs_act_last_seen` | 8 |
| `aqs_act_logins` | 6 |
| `aqs_act_visits` | 8 |
| `aqs_dobor_count` | 8 |
| `aqs_dobor_last_session` | 8 |
| `aqs_full_name` | 318 |
| `aqs_marketing_consent` | 318 |
| `aqs_nip` | 16 |
| `aqs_onb_answers` | 4 |
| `aqs_onb_completed_at` | 4 |
| `aqs_onb_status` | 4 |
| `aqs_phone` | 16 |
| `aqs_rodo_accepted_at` | 318 |
| `aqs_shop_address` | 16 |
| `aqs_shop_deactivation_clock` | 1 |
| `aqs_shop_ever_activated` | 1 |
| `aqs_shop_name` | 16 |
| `aqs_shop_slug` | 17 |
| `aqs_shop_state` | 1 |
| `aqs_shop_status` | 5 |
| `aqs_shop_url` | 16 |
| `aqs_survey_login_state` | 27 |
| `aqss_cart` | 6 |

### 6.3. Wartości statusów kont (liczby)

| meta_key | wartość | wiersze |
|---|---|---:|
| `aqs_account_type` | shop | 16 |
| `aqs_account_type` | user | 302 |
| `aqs_shop_state` | aktywny | 1 |
| `aqs_shop_status` | oczekuje_na_weryfikacje | 5 |

## 7. Posty i strony

### 7.1. Typy i statusy

| post_type | post_status | wiersze |
|---|---|---:|
| attachment | inherit | 376 |
| attachment | private | 1 |
| custom_css | publish | 1 |
| nav_menu_item | publish | 16 |
| page | publish | 15 |
| page | trash | 2 |
| post | auto-draft | 1 |
| revision | inherit | 42 |
| wp_global_styles | publish | 1 |
| wp_navigation | publish | 1 |

### 7.2. Strony (`post_type = page`)

| ID | tytuł | slug | status |
|---:|---|---|---|
| 9 | Doradca | doradca | publish |
| 26 | Dashboard | dashboard | publish |
| 31 | Rejestracja | rejestracja | publish |
| 38 | logowanie | logowanie | publish |
| 43 | Panel sklepu | panel-sklepu__trashed | trash |
| 62 | moje-akwarium | moje-akwarium | publish |
| 70 | redfield | redfield | publish |
| 119 | Z pasji do akwarystyki | portal | publish |
| 164 | Polityka plików cookies (EU) | polityka-plikow-cookies-eu | publish |
| 220 | Regulamin platformy Aikwarium.pl | regulamin-platformy-aikwarium | publish |
| 225 | Polityka prywatności | polityka-prywatnosci | publish |
| 237 | /poznajmy-sie/ | poznajmy-sie | publish |
| 825 | Oferty w Twojej okolicy | oferty-w-twojej-okolicy | publish |
| 828 | kup | kup | publish |
| 841 | nowy start | nowy-start | publish |
| 892 | dobor-z-zbiornika, | dobor-z-zbiornika__trashed | trash |
| 904 | Panel sklepu | panel-sklepu | publish |

## 8. Baza wiedzy (fauna, flora, sprzęt, pielęgnacja)

Baza wiedzy NIE jest w plikach (w tej wersji). Rejestr katalogu i zdjęcia są w bazie. Pełne opisy gatunków i produktów w dumpie nie występują (patrz 8.4).

### 8.1. Katalog i rejestr ID: `wp_aqs_shop_products`

- Wiersze: **179**.
- Kolumna `aqs_db` (kategoria): `fauna` 157 wierszy (77 unikalnych `aqs_record_id`), `flora` 7 (7 unikalnych), `equipment` (sprzęt) 9 (8), `maintenance` (pielęgnacja) 6 (6).
- Kolumny: id, shop_id, aqs_db, aqs_record_id (ID rejestru), record_name, variant, url, price, last_updated, click_count, row_checksum, url_status, url_status_code, url_last_checked, url_final_url, url_warning, created_at, updated_at, published_at.
- Przykładowe wiersze (dane katalogowe):
  - `fauna` / `FN0006` / "Poecilia latipinna (Molinezja Żaglopłetwa)" / variant NULL / price 5.00
  - `flora` / `FL0006` / "Hygrophila polysperma (Nadwódka wielonasienna)" / variant NULL / price 45.00

### 8.2. Zdjęcia gatunków: `wp_posts` + `wp_postmeta`

- `wp_posts` z `post_type = attachment`: **376** (375 JPEG, 1 WebP; 1 wpis ze statusem `private`).
- `wp_postmeta`: `_aquasmart_species_id` 204 wiersze (powiązanie zdjęcia z gatunkiem), `_wp_attachment_image_alt` 204, `_aquasmart_jj_source_file` 204, oraz `_aquasmart_photo_author`, `_aquasmart_photo_license`, `_aquasmart_photo_source_url` po 204 (atrybucja zdjęć; wartości pominięte).
- Przykładowe rekordy (tytuły to nazwy gatunków): ID 265 "Żyworódka Wspaniała" (slug `zyworodka-wspaniala`); ID 273 "Bystrzyk Czarny / Fantom Czarny" (slug `bystrzyk-czarny-fantom-czarny`).

### 8.3. Tabele katalogu (puste) i opcje k1k2

- `wp_aqs_catalog_products`: **0** wierszy. Kolumny: id, shop_id, slug, name, description, image_url, price_note, availability, contact_type, contact_value, status, click_count, contact_click_count, created_at, updated_at. Kolumna `description` i `image_url` są puste (brak danych).
- `wp_aqs_catalog_views`: **0** wierszy (id, product_id, shop_id, logged_at, ip_hash).
- Opcje: `aqs_catalog_db_version` = 5; `aqs_k1k2_dataset_source` = `bundled`; `aqs_k1k2_bundled_version` = `1.1.60`; `aqs_k1k2_force_reset_build` = `20260603-003`.
- Transienty `_transient_aqs_k1k2_*` (2 szt., po 486 znaków) to stan sesji, a nie zestaw danych.

### 8.4. Brakujące elementy

- Opisy (`description`) gatunków i produktów: brak wypełnionych danych w bazie.
- Pełny zestaw wiedzy k1k2: wartość `bundled` wskazuje, że dane są ładowane z plików wtyczki `aquasmart_v103` (wymienionej w `active_plugins`), a nie z tabel. Do potwierdzenia przez właściciela.

## 9. Ustalenia do weryfikacji

1. 279 osieroconych wierszy `wp_usermeta` (brak konta w `wp_users`): możliwa niespójność dumpu albo usunięte konta.
2. Baza wiedzy: rejestr i zdjęcia są w bazie, ale opisy nie. Tryb `bundled` wskazuje na pliki wtyczki.
3. `wp_aqs_catalog_products` i `wp_aqs_catalog_views` są puste, mimo że schemat zakłada katalog z opisami.

---

## Korekta po weryfikacji (22.11.2026)

- **Baza wiedzy nie jest w bazie danych.** `wp_aqs_shop_products` (179 wierszy: `url`, `price`, `click_count`, `url_status`…) to tabela ofert **starej** wtyczki sklepu z modelu afiliacyjnego, a nie katalog wiedzy. Baza wiedzy (fauna, flora, sprzęt, pielęgnacja) jest w plikach wtyczki Doradcy (`aquasmart_v103/data/*.txt.php`, tryb `bundled` według opcji `aqs_k1k2_dataset_source`). Panel stagingu pokazuje 200 / 194 / 108 / 110 rekordów. Pliki z aktualnej gałęzi (commit `49b0eca`) są źródłem dla v2.
- W bazie testowej są dane obu modeli sklepów: starego (`wp_aqs_shop_*`: oferty 179, kliknięcia 195, bufor 61, próbki rozliczeń 460) i nowego Shops 0.4.0 (`wp_aqss_*`: sklepy 5, oferty 52, zamówienia/pozycje 14, zdarzenia 39, członkostwa 5, weryfikacje 5).
- Śmieci do wyczyszczenia przy migracji: 687 subskrypcji przy 42 kontach, 279 osieroconych wpisów ról (`wp_capabilities`) dla nieistniejących użytkowników.
