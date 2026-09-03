<?php

if ( ! defined('ABSPATH')) {
  exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- ct_credits is a custom table; $wpdb is the only way to reach it, there is no WP API to prefer.

/**
 * Production Credits Model — queries run directly against ct_credits via $wpdb; returns stdClass rows, not WP_Query.
 */

/**
 * Cache group for ct_credits reads. Keys embed wp_cache_get_last_changed(), so one
 * theatrum_credits_cache_invalidate() on write retires every key at once and there is no key list
 * to keep in step with the queries. Without a persistent object cache this is a per-request win,
 * which still matters: a production page resolves the same credit set several times over.
 */
const THEATRUM_CREDITS_CACHE_GROUP = 'theatrum_credits';

/**
 * @param string $name  Distinguishes one query shape from another.
 * @param array  $parts Arguments that change the result.
 * @return string
 */
function theatrum_credits_cache_key($name, array $parts) {
  return $name . ':' . md5(wp_json_encode($parts)) . ':' . wp_cache_get_last_changed(THEATRUM_CREDITS_CACHE_GROUP);
}

/**
 * Retires every cached ct_credits read. Call after any write to the table.
 */
function theatrum_credits_cache_invalidate() {
  wp_cache_set('last_changed', microtime(), THEATRUM_CREDITS_CACHE_GROUP);
}

/**
 * Get all credits for a production.
 *
 * @param int   $production_id
 * @param array $args  role_group (string), count_only (bool)
 * @return array|int   Array of row objects, or int when count_only is true
 */
function theatrum_credits_get_production_credits($production_id, $args = array()) {
  global $wpdb;

  $defaults = array(
    'role_group' => '',
    'count_only' => false,
  );
  $args     = wp_parse_args($args, $defaults);

  $table = THEATRUM_CREDITS_TABLE;

  if ( ! empty($args['role_group'])) {
    $sql = $wpdb->prepare(
        "SELECT * FROM %i WHERE credit_production = %d AND credit_role_group = %s ORDER BY credit_order ASC",
        $table,
        $production_id,
        $args['role_group']
    );
  } else {
    $sql = $wpdb->prepare(
        "SELECT * FROM %i WHERE credit_production = %d ORDER BY credit_order ASC",
        $table,
        $production_id
    );
  }

  $key    = theatrum_credits_cache_key('production_credits', array($production_id, $args['role_group'], (bool) $args['count_only']));
  $cached = wp_cache_get($key, THEATRUM_CREDITS_CACHE_GROUP);

  if (false !== $cached) {
    return $cached;
  }

  if ($args['count_only']) {
    if ( ! empty($args['role_group'])) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- cached above/below on $key.
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE credit_production = %d AND credit_role_group = %s",
            $table,
            $production_id,
            $args['role_group']
        )
    );
    } else {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- cached above/below on $key.
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE credit_production = %d",
            $table,
            $production_id
        )
    );
    }

    wp_cache_set($key, $count, THEATRUM_CREDITS_CACHE_GROUP);

    return $count;
  }

  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is always the result of $wpdb->prepare() above (the sniff can't trace it through the if/else); cached on $key.
  $rows = $wpdb->get_results($sql);

  wp_cache_set($key, $rows, THEATRUM_CREDITS_CACHE_GROUP);

  return $rows;
}

/**
 * Get all credits for an artist, ordered by date descending.
 *
 * @param int   $artist_id
 * @param array $args  role_group (string)
 * @return array  Array of row objects
 */
function theatrum_credits_get_artist_productions($artist_id, $args = array()) {
  global $wpdb;

  $defaults = array(
    'role_group' => '',
  );
  $args     = wp_parse_args($args, $defaults);

  $table = THEATRUM_CREDITS_TABLE;

  $key    = theatrum_credits_cache_key('artist_productions', array($artist_id, $args['role_group']));
  $cached = wp_cache_get($key, THEATRUM_CREDITS_CACHE_GROUP);

  if (false !== $cached) {
    return $cached;
  }

  if ( ! empty($args['role_group'])) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- cached above/below on $key.
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM %i WHERE credit_artist = %d AND credit_role_group = %s ORDER BY credit_date DESC, credit_order ASC",
            $table,
            $artist_id,
            $args['role_group']
        )
    );
  } else {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- cached above/below on $key.
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM %i WHERE credit_artist = %d ORDER BY credit_date DESC, credit_order ASC",
            $table,
            $artist_id
        )
    );
  }

  wp_cache_set($key, $rows, THEATRUM_CREDITS_CACHE_GROUP);

  return $rows;
}

/**
 * Get production credits organized by role group.
 *
 * @param int $production_id
 * @return array  Keyed by role_group string
 */
function theatrum_credits_get_credits_by_group($production_id) {
  $credits   = theatrum_credits_get_production_credits($production_id);
  $organized = array();

  foreach ($credits as $row) {
    $group = $row->credit_role_group;

    if ( ! isset($organized[$group])) {
      $organized[$group] = array();
    }

    $organized[$group][] = array(
      'credit_id'    => (int) $row->credit_ID,
      'artist_id'    => (int) $row->credit_artist,
      'artist_name'  => get_the_title($row->credit_artist),
      'artist_link'  => get_permalink($row->credit_artist),
      'role'         => $row->credit_role,
      'role_group'   => $row->credit_role_group,
      'thumbnail_id' => get_post_thumbnail_id($row->credit_artist),
    );
  }

  return $organized;
}

/**
 * Get artist credits with formatted production data.
 *
 * @param int $artist_id
 * @return array
 */
function theatrum_credits_get_artist_productions_with_dates($artist_id) {
  $credits     = theatrum_credits_get_artist_productions($artist_id);
  $productions = array();

  foreach ($credits as $row) {
    $productions[] = array(
      'credit_id'        => (int) $row->credit_ID,
      'production_id'    => (int) $row->credit_production,
      'production_title' => get_the_title($row->credit_production),
      'production_link'  => get_permalink($row->credit_production),
      'opening_date'     => $row->credit_date,
      'role'             => $row->credit_role,
      'role_group'       => $row->credit_role_group,
    );
  }

  return $productions;
}

/**
 * Count distinct productions an artist has credits in.
 *
 * @param int $artist_id
 * @return int
 */
function theatrum_credits_count_artist_productions($artist_id) {
  global $wpdb;

  $key    = theatrum_credits_cache_key('count_artist_productions', array($artist_id));
  $cached = wp_cache_get($key, THEATRUM_CREDITS_CACHE_GROUP);

  if (false !== $cached) {
    return (int) $cached;
  }

  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- cached above/below on $key.
$count = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(DISTINCT credit_production) FROM %i WHERE credit_artist = %d",
        THEATRUM_CREDITS_TABLE,
        $artist_id
    )
);

  wp_cache_set($key, $count, THEATRUM_CREDITS_CACHE_GROUP);

  return $count;
}

/**
 * Count credits for a production, optionally filtered by role group.
 *
 * @param int    $production_id
 * @param string $role_group
 * @return int
 */
function theatrum_credits_count_production_credits($production_id, $role_group = '') {
return theatrum_credits_get_production_credits(
    $production_id,
    array(
    'role_group' => $role_group,
    'count_only' => true,
    )
);
}

/**
 * Backwards compat: syncs a directly-saved credit post to ct_credits via _ct_credit_id postmeta (written during migration).
 */
add_action(
    'save_post_credit',
    function ($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
      return;
    }
    if ( ! current_user_can('edit_post', $post_id)) {
      return;
    }

    global $wpdb;

    $production_id = (int) get_post_meta($post_id, 'production', true);
    $artist_id     = (int) get_post_meta($post_id, 'artist', true);
    $role_group    = get_post_meta($post_id, 'role_group', true) ?: '';
    $role          = get_post_meta($post_id, 'role', true) ?: '';

    if ( ! $production_id || ! $artist_id) {
      return;
    }

    $production_post = get_post($production_id);
    $artist_post     = get_post($artist_id);
    if ( ! $production_post || ! $artist_post) {
      return;
    }

    $credit_title = $production_post->post_title . ' / ' . $artist_post->post_title;
    $credit_date  = get_field('opening', $production_id) ?: '';
    $ct_credit_id = (int) get_post_meta($post_id, '_ct_credit_id', true);

    if ($ct_credit_id) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- must read current rows: this runs immediately before a write, or backs the admin list table.
    $wpdb->update(
        THEATRUM_CREDITS_TABLE,
        array(
          'credit_title'      => $credit_title,
          'credit_name'       => sanitize_title($credit_title),
          'credit_artist'     => $artist_id,
          'credit_production' => $production_id,
          'credit_role'       => $role,
          'credit_role_group' => $role_group,
          'credit_date'       => $credit_date,
        ),
        array('credit_ID' => $ct_credit_id),
        array('%s', '%s', '%d', '%d', '%s', '%s', '%s'),
        array('%d')
    );
    } else {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- must read current rows: this runs immediately before a write, or backs the admin list table.
    $max_order = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT MAX(credit_order) FROM %i WHERE credit_production = %d",
            THEATRUM_CREDITS_TABLE,
            $production_id
        )
    );

    $wpdb->insert(
        THEATRUM_CREDITS_TABLE,
        array(
        'credit_title'      => $credit_title,
        'credit_name'       => sanitize_title($credit_title),
        'credit_artist'     => $artist_id,
        'credit_production' => $production_id,
        'credit_role'       => $role,
        'credit_role_group' => $role_group,
        'credit_date'       => $credit_date,
        'credit_order'      => $max_order + 1,
        ),
        array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d')
    );

    if ($wpdb->insert_id) {
      update_post_meta($post_id, '_ct_credit_id', (int) $wpdb->insert_id);
    }
    }

    theatrum_credits_cache_invalidate();
    },
    20
);
