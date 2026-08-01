<?php

/**
 * Production Credits Model
 *
 * All queries run directly against the ct_credits custom table.
 * Functions return arrays of stdClass row objects (not WP_Query).
 */

/**
 * Get all credits for a production.
 *
 * @param int   $production_id
 * @param array $args  role_group (string), count_only (bool)
 * @return array|int   Array of row objects, or int when count_only is true
 */
function get_production_credits($production_id, $args = array())
{
  global $wpdb;

  $defaults = array(
    'role_group' => '',
    'count_only' => false,
  );
  $args = wp_parse_args($args, $defaults);

  $table = THEATRUM_CREDITS_TABLE;

  if (! empty($args['role_group'])) {
    $sql = $wpdb->prepare(
      "SELECT * FROM $table WHERE credit_production = %d AND credit_role_group = %s ORDER BY credit_order ASC",
      $production_id,
      $args['role_group']
    );
  } else {
    $sql = $wpdb->prepare(
      "SELECT * FROM $table WHERE credit_production = %d ORDER BY credit_order ASC",
      $production_id
    );
  }

  if ($args['count_only']) {
    if (! empty($args['role_group'])) {
      return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE credit_production = %d AND credit_role_group = %s",
        $production_id,
        $args['role_group']
      ));
    }
    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $table WHERE credit_production = %d",
      $production_id
    ));
  }

  return $wpdb->get_results($sql);
}

/**
 * Get all credits for an artist, ordered by date descending.
 *
 * @param int   $artist_id
 * @param array $args  role_group (string)
 * @return array  Array of row objects
 */
function get_artist_productions($artist_id, $args = array())
{
  global $wpdb;

  $defaults = array(
    'role_group' => '',
  );
  $args = wp_parse_args($args, $defaults);

  $table = THEATRUM_CREDITS_TABLE;

  if (! empty($args['role_group'])) {
    return $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $table WHERE credit_artist = %d AND credit_role_group = %s ORDER BY credit_date DESC, credit_order ASC",
      $artist_id,
      $args['role_group']
    ));
  }

  return $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table WHERE credit_artist = %d ORDER BY credit_date DESC, credit_order ASC",
    $artist_id
  ));
}

/**
 * Get production credits organized by role group.
 *
 * @param int $production_id
 * @return array  Keyed by role_group string
 */
function get_credits_by_group($production_id)
{
  $credits    = get_production_credits($production_id);
  $organized  = array();

  foreach ($credits as $row) {
    $group = $row->credit_role_group;

    if (! isset($organized[$group])) {
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
function get_artist_productions_with_dates($artist_id)
{
  $credits     = get_artist_productions($artist_id);
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
function count_artist_productions($artist_id)
{
  global $wpdb;

  return (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT credit_production) FROM " . THEATRUM_CREDITS_TABLE . " WHERE credit_artist = %d",
    $artist_id
  ));
}

/**
 * Count credits for a production, optionally filtered by role group.
 *
 * @param int    $production_id
 * @param string $role_group
 * @return int
 */
function count_production_credits($production_id, $role_group = '')
{
  return get_production_credits($production_id, array(
    'role_group' => $role_group,
    'count_only' => true,
  ));
}

/**
 * Backwards compat: when a credit post is saved directly, sync it to ct_credits.
 * Uses _ct_credit_id postmeta (written during migration) to find the correct row.
 */
add_action('save_post_credit', function ($post_id) {
  if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
    return;
  }

  global $wpdb;

  $production_id = (int) get_post_meta($post_id, 'production', true);
  $artist_id     = (int) get_post_meta($post_id, 'artist', true);
  $role_group    = get_post_meta($post_id, 'role_group', true) ?: '';
  $role          = get_post_meta($post_id, 'role', true) ?: '';

  if (! $production_id || ! $artist_id) {
    return;
  }

  $production_post = get_post($production_id);
  $artist_post     = get_post($artist_id);
  if (! $production_post || ! $artist_post) {
    return;
  }

  $credit_title = $production_post->post_title . ' / ' . $artist_post->post_title;
  $credit_date  = get_field('opening', $production_id) ?: '';
  $ct_credit_id = (int) get_post_meta($post_id, '_ct_credit_id', true);

  if ($ct_credit_id) {
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
    $max_order = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT MAX(credit_order) FROM " . THEATRUM_CREDITS_TABLE . " WHERE credit_production = %d",
      $production_id
    ));

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
}, 20);
