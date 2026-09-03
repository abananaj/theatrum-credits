<?php

if ( ! defined('ABSPATH')) {
  exit;
}

function theatrum_credits_create_table() {
  global $wpdb;

  $table   = THEATRUM_CREDITS_TABLE;
  $charset = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $table (
  credit_ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  credit_title varchar(255) NOT NULL DEFAULT '',
  credit_name varchar(255) NOT NULL DEFAULT '',
  credit_artist bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  credit_production bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  credit_role varchar(255) NOT NULL DEFAULT '',
  credit_role_group varchar(100) NOT NULL DEFAULT '',
  credit_date varchar(20) NOT NULL DEFAULT '',
  credit_order int(11) UNSIGNED NOT NULL DEFAULT 0,
  credit_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  credit_modified datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (credit_ID),
  KEY credit_artist (credit_artist),
  KEY credit_production (credit_production),
  KEY credit_role_group (credit_role_group),
  KEY credit_order (credit_production, credit_order)
) $charset;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql);

  update_option('theatrum_credits_db_version', '1.1.0');
}

function theatrum_credits_maybe_create_table() {
  if (get_option('theatrum_credits_db_version') !== '1.1.0') {
    theatrum_credits_create_table();
  }
}
add_action('init', 'theatrum_credits_maybe_create_table');
