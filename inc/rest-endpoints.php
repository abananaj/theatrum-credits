<?php

if (! defined('ABSPATH')) {
  exit;
}

define('THEATRUM_CREDITS_VALID_ROLE_GROUPS', array(
  'actor', 'creative_team', 'producer',
));

function theatrum_credits_editor_permission_check()
{
  return current_user_can('edit_posts');
}

/* -----------------------------------------------------------------------
 * Helpers
 * -------------------------------------------------------------------- */

function theatrum_credits_get_row($credit_id)
{
  global $wpdb;
  return $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM %i WHERE credit_ID = %d",
    THEATRUM_CREDITS_TABLE,
    $credit_id
  ));
}

function theatrum_credits_verify_ownership($row)
{
  if (! $row) {
    return new WP_Error('not_found', 'Credit not found.', array('status' => 404));
  }
  if (! current_user_can('edit_post', (int) $row->credit_production)) {
    return new WP_Error('forbidden', 'You cannot edit this credit.', array('status' => 403));
  }
  return true;
}

function theatrum_credits_format_row_for_editor($row)
{
  return array(
    'id'               => (int) $row->credit_ID,
    'artist_id'        => (int) $row->credit_artist,
    'artist_title'     => get_the_title($row->credit_artist),
    'artist_url'       => get_permalink($row->credit_artist),
    'artist_thumbnail' => get_the_post_thumbnail_url($row->credit_artist) ?: '',
    'role'             => $row->credit_role,
    'role_group'       => $row->credit_role_group,
    'order'            => (int) $row->credit_order,
  );
}

/* -----------------------------------------------------------------------
 * Producer meta sync — mirrors producer credits onto the production post
 * as postmeta (role => post ID, or an array of post IDs when more than
 * one producer shares the same role), kept in sync on create/update/delete.
 *
 * Only these 4 production-scoped roles are synced. Season-level roles
 * (Season Producers, Associate/Executive Season Producers, OTR Season
 * Producer, etc.) are entered per-production in ct_credits but display via
 * the season term's meta, not the production post — they must never be
 * written here. Role text is matched case/punctuation-insensitively so
 * plural and near-duplicate spellings collapse onto the same canonical key.
 * -------------------------------------------------------------------- */

define('THEATRUM_CREDITS_PRODUCER_META_MAP', array(
  'executive producer'  => 'executive_producer',
  'executive producers' => 'executive_producer',
  'associate producer'  => 'associate_producer',
  'associate producers' => 'associate_producer',
  'assoc producers'     => 'associate_producer',
  'corporate sponsor'   => 'corporate_sponsor',
  'supporting producer' => 'supporting_producer',
));

function theatrum_credits_producer_meta_key($role, $role_group)
{
  $role = trim((string) $role);
  $role = strtolower($role);
  $role = preg_replace('/[^a-z0-9]+/', ' ', $role);
  $role = trim($role);

  return isset(THEATRUM_CREDITS_PRODUCER_META_MAP[$role]) ? THEATRUM_CREDITS_PRODUCER_META_MAP[$role] : false;
}

function theatrum_credits_add_producer_meta($production_id, $role, $role_group, $artist_id)
{
  $key = theatrum_credits_producer_meta_key($role, $role_group);
  if (! $key) {
    return;
  }
  $existing = get_post_meta($production_id, $key, true);

  $ids = $existing === '' ? array() : (is_array($existing) ? $existing : array($existing));
  $ids = array_map('intval', $ids);

  if (! in_array((int) $artist_id, $ids, true)) {
    $ids[] = (int) $artist_id;
  }

  update_post_meta($production_id, $key, count($ids) === 1 ? $ids[0] : array_values($ids));
}

function theatrum_credits_remove_producer_meta($production_id, $role, $role_group, $artist_id)
{
  $key = theatrum_credits_producer_meta_key($role, $role_group);
  if (! $key) {
    return;
  }
  $existing = get_post_meta($production_id, $key, true);

  if ($existing === '') {
    return;
  }

  $ids = is_array($existing) ? $existing : array($existing);
  $ids = array_values(array_diff(array_map('intval', $ids), array((int) $artist_id)));

  if (empty($ids)) {
    delete_post_meta($production_id, $key);
  } else {
    update_post_meta($production_id, $key, count($ids) === 1 ? $ids[0] : $ids);
  }
}

/* -----------------------------------------------------------------------
 * GET + POST /production-credits/{post_id}
 * -------------------------------------------------------------------- */

add_action('rest_api_init', function () {
  // Public by design — only returns already-public data (titles, permalinks,
  // thumbnails). If this callback is ever extended, keep it that way; add a
  // capability check before returning anything non-public.
  register_rest_route('theatrum/v1', '/production-credits/(?P<post_id>\d+)', array(
    array(
      'methods'             => 'GET',
      'callback'            => 'theatrum_credits_get_production_credits_callback',
      'permission_callback' => '__return_true',
    ),
    array(
      'methods'             => 'POST',
      'callback'            => 'theatrum_credits_create_credit_callback',
      'permission_callback' => 'theatrum_credits_editor_permission_check',
    ),
  ));
});

function theatrum_credits_get_production_credits_callback($request)
{
  $production_id = intval($request['post_id']);
  $rows          = theatrum_credits_get_production_credits($production_id);
  $output        = array_map('theatrum_credits_format_row_for_editor', $rows);

  return new WP_REST_Response(array('credits' => $output), 200);
}

function theatrum_credits_create_credit_callback($request)
{
  global $wpdb;

  $production_id = intval($request['post_id']);
  $artist_id     = intval($request->get_param('artist'));
  $role_group    = sanitize_text_field($request->get_param('role_group'));
  $role          = sanitize_text_field($request->get_param('role') ?: '');

  if (! in_array($role_group, THEATRUM_CREDITS_VALID_ROLE_GROUPS, true)) {
    return new WP_Error('invalid_role_group', 'Invalid role group.', array('status' => 400));
  }

  $production_post = get_post($production_id);
  $artist_post     = get_post($artist_id);

  if (! $production_post || ! $artist_post) {
    return new WP_Error('invalid_ids', 'Production or artist not found.', array('status' => 400));
  }

  if (! current_user_can('edit_post', $production_id)) {
    return new WP_Error('forbidden', 'You cannot add credits to this production.', array('status' => 403));
  }

  $max_order = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT MAX(credit_order) FROM %i WHERE credit_production = %d",
    THEATRUM_CREDITS_TABLE,
    $production_id
  ));

  $credit_title = $production_post->post_title . ' / ' . $artist_post->post_title;

  $inserted = $wpdb->insert(
    THEATRUM_CREDITS_TABLE,
    array(
      'credit_title'      => $credit_title,
      'credit_name'       => sanitize_title($credit_title),
      'credit_artist'     => $artist_id,
      'credit_production' => $production_id,
      'credit_role'       => $role,
      'credit_role_group' => $role_group,
      'credit_date'       => get_field('opening', $production_id) ?: '',
      'credit_order'      => $max_order + 1,
    ),
    array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d')
  );

  if (! $inserted) {
    return new WP_Error('insert_failed', 'Failed to create credit.', array('status' => 500));
  }

  if ($role_group === 'producer') {
    theatrum_credits_add_producer_meta($production_id, $role, $role_group, $artist_id);
  }

  $new_row = theatrum_credits_get_row((int) $wpdb->insert_id);

  return new WP_REST_Response(theatrum_credits_format_row_for_editor($new_row), 201);
}

/* -----------------------------------------------------------------------
 * POST /production-credits/{post_id}/reorder
 * -------------------------------------------------------------------- */

add_action('rest_api_init', function () {
  register_rest_route('theatrum/v1', '/production-credits/(?P<post_id>\d+)/reorder', array(
    'methods'             => 'POST',
    'callback'            => 'theatrum_credits_reorder_callback',
    'permission_callback' => 'theatrum_credits_editor_permission_check',
  ));
});

function theatrum_credits_reorder_callback($request)
{
  global $wpdb;

  $production_id = intval($request['post_id']);
  $order         = $request->get_param('order');

  if (! current_user_can('edit_post', $production_id)) {
    return new WP_Error('forbidden', 'You cannot reorder credits for this production.', array('status' => 403));
  }

  if (! is_array($order) || empty($order)) {
    return new WP_Error('invalid_order', 'order must be a non-empty array.', array('status' => 400));
  }

  $ids          = array_map('intval', $order);
  $table        = THEATRUM_CREDITS_TABLE;
  $placeholders = implode(',', array_fill(0, count($ids), '%d'));

  // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is built entirely from array_fill()'s literal '%d', never from $ids' values; the sniff can't statically count a runtime-built placeholder list. Documented WordPress pattern for a dynamic-length IN() clause.
  $valid_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT credit_ID FROM %i WHERE credit_production = %d AND credit_ID IN ($placeholders)",
    array_merge(array($table, $production_id), $ids)
  ));
  // phpcs:enable

  if (count($valid_ids) !== count($ids)) {
    return new WP_Error('invalid_ids', 'One or more IDs do not belong to this production.', array('status' => 403));
  }

  foreach ($ids as $position => $credit_id) {
    $wpdb->update(
      $table,
      array('credit_order' => $position),
      array('credit_ID'    => $credit_id),
      array('%d'),
      array('%d')
    );
  }

  return new WP_REST_Response(array('success' => true), 200);
}

/* -----------------------------------------------------------------------
 * PUT + DELETE /credit/{credit_id}
 * -------------------------------------------------------------------- */

add_action('rest_api_init', function () {
  register_rest_route('theatrum/v1', '/credit/(?P<credit_id>\d+)', array(
    array(
      'methods'             => 'PUT',
      'callback'            => 'theatrum_credits_update_credit_callback',
      'permission_callback' => 'theatrum_credits_editor_permission_check',
    ),
    array(
      'methods'             => 'DELETE',
      'callback'            => 'theatrum_credits_delete_credit_callback',
      'permission_callback' => 'theatrum_credits_editor_permission_check',
    ),
  ));
});

function theatrum_credits_update_credit_callback($request)
{
  global $wpdb;

  $credit_id = intval($request['credit_id']);
  $row       = theatrum_credits_get_row($credit_id);
  $check     = theatrum_credits_verify_ownership($row);

  if (is_wp_error($check)) return $check;

  $role_group = sanitize_text_field($request->get_param('role_group') ?: $row->credit_role_group);
  $role       = sanitize_text_field($request->get_param('role') ?? $row->credit_role);
  $artist_id  = intval($request->get_param('artist') ?: $row->credit_artist);

  if (! in_array($role_group, THEATRUM_CREDITS_VALID_ROLE_GROUPS, true)) {
    return new WP_Error('invalid_role_group', 'Invalid role group.', array('status' => 400));
  }

  $artist_post     = get_post($artist_id);
  $production_post = get_post((int) $row->credit_production);

  if (! $production_post || ! $artist_post) {
    return new WP_Error('invalid_ids', 'Production or artist not found.', array('status' => 400));
  }

  $credit_title    = $production_post->post_title . ' / ' . $artist_post->post_title;

  $wpdb->update(
    THEATRUM_CREDITS_TABLE,
    array(
      'credit_title'      => $credit_title,
      'credit_name'       => sanitize_title($credit_title),
      'credit_artist'     => $artist_id,
      'credit_role'       => $role,
      'credit_role_group' => $role_group,
    ),
    array('credit_ID' => $credit_id),
    array('%s', '%s', '%d', '%s', '%s'),
    array('%d')
  );

  if ($row->credit_role_group === 'producer') {
    theatrum_credits_remove_producer_meta((int) $row->credit_production, $row->credit_role, $row->credit_role_group, (int) $row->credit_artist);
  }
  if ($role_group === 'producer') {
    theatrum_credits_add_producer_meta((int) $row->credit_production, $role, $role_group, $artist_id);
  }

  return new WP_REST_Response(theatrum_credits_format_row_for_editor(theatrum_credits_get_row($credit_id)), 200);
}

function theatrum_credits_delete_credit_callback($request)
{
  global $wpdb;

  $credit_id = intval($request['credit_id']);
  $row       = theatrum_credits_get_row($credit_id);
  $check     = theatrum_credits_verify_ownership($row);

  if (is_wp_error($check)) return $check;

  $deleted = $wpdb->delete(
    THEATRUM_CREDITS_TABLE,
    array('credit_ID' => $credit_id),
    array('%d')
  );

  if (! $deleted) {
    return new WP_Error('delete_failed', 'Failed to delete credit.', array('status' => 500));
  }

  if ($row->credit_role_group === 'producer') {
    theatrum_credits_remove_producer_meta((int) $row->credit_production, $row->credit_role, $row->credit_role_group, (int) $row->credit_artist);
  }

  return new WP_REST_Response(array('deleted' => true, 'id' => $credit_id), 200);
}

/* -----------------------------------------------------------------------
 * GET /artist-credits/{post_id}
 * -------------------------------------------------------------------- */

add_action('rest_api_init', function () {
  // Public by design — only returns already-public data (titles, permalinks).
  // If this callback is ever extended, keep it that way; add a capability
  // check before returning anything non-public.
  register_rest_route('theatrum/v1', '/artist-credits/(?P<post_id>\d+)', array(
    'methods'             => 'GET',
    'callback'            => 'theatrum_credits_get_artist_credits_callback',
    'permission_callback' => '__return_true',
  ));
});

function theatrum_credits_get_artist_credits_callback($request)
{
  $artist_id = intval($request['post_id']);
  $rows      = theatrum_credits_get_artist_productions($artist_id);
  $output    = array();

  foreach ($rows as $row) {
    $year     = $row->credit_date
      ? date('Y', is_numeric($row->credit_date) ? (int) $row->credit_date : strtotime($row->credit_date))
      : '';
    $output[] = array(
      'id'               => (int) $row->credit_ID,
      'production_title' => get_the_title($row->credit_production),
      'production_url'   => get_permalink($row->credit_production),
      'role'             => $row->credit_role ?: $row->credit_role_group,
      'date'             => $year,
    );
  }

  return new WP_REST_Response(array('credits' => $output), 200);
}
