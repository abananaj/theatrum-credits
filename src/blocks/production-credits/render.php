<?php

/**
 * Production Credits Block - Server-side render callback
 *
 * Displays the artists credited for the current production.
 * Filters by role_group based on the `roleGroup` attribute:
 *   - "all"     → all credits (default)
 *   - "team"    → all credits excluding actor and producer
 *   - "cast"    → actor only
 *   - "partner" → producer only
 */

$post_id    = get_the_ID();
$role_group = isset($attributes['roleGroup']) ? $attributes['roleGroup'] : 'all';

if (! $post_id) {
  return;
}

$team_exclude = array('actor', 'producer');

if ('cast' === $role_group) {
  $credits = theatrum_credits_get_production_credits($post_id, array('role_group' => 'actor'));
} elseif ('partner' === $role_group) {
  $credits = theatrum_credits_get_production_credits($post_id, array('role_group' => 'producer'));
} else {
  $credits = theatrum_credits_get_production_credits($post_id);
  if ('team' === $role_group) {
    $credits = array_filter($credits, function ($row) use ($team_exclude) {
      return ! in_array($row->credit_role_group, $team_exclude, true);
    });
  }
}

if (empty($credits)) {
  return;
}

$allowed_justify = array('flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly');
$allowed_align    = array('flex-start', 'center', 'flex-end', 'stretch');

$justify_content = isset($attributes['justifyContent']) && in_array($attributes['justifyContent'], $allowed_justify, true)
  ? $attributes['justifyContent']
  : 'flex-start';
$align_items      = isset($attributes['alignItems']) && in_array($attributes['alignItems'], $allowed_align, true)
  ? $attributes['alignItems']
  : 'flex-start';

$css_length_pattern = '/^\d*\.?\d+(px|em|rem|%|vh|vw|ch)$/';
$item_width         = isset($attributes['itemWidth']) && preg_match($css_length_pattern, $attributes['itemWidth'])
  ? $attributes['itemWidth']
  : '160px';

$flex_style = sprintf('justify-content: %s; align-items: %s; --credit-width: %s;', $justify_content, $align_items, $item_width);

$html = '<div ' . wp_kses_data(get_block_wrapper_attributes()) . '>';
$html .= '<ul class="production-credits-ul" style="' . esc_attr($flex_style) . '">';

foreach ($credits as $row) {
  $artist_id    = (int) $row->credit_artist;
  $artist_title = get_the_title($artist_id);
  $artist_url   = get_permalink($artist_id);
  $display_role = $row->credit_role ?: $row->credit_role_group;

  $html .= '<li class="credit">';
  $html .= '<a href="' . esc_url($artist_url) . '" class="credit-link">';

  $thumbnail_url = get_the_post_thumbnail_url($artist_id);
  if ($thumbnail_url) {
    $html .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($artist_title) . '" class="artist-headshot"/>';
  }

  $html .= '<p class="artist">' . esc_html($artist_title) . '</p>';

  if (! empty($display_role)) {
    $html .= '<p class="role">' . esc_html($display_role) . '</p>';
  }

  $html .= '</a>';
  $html .= '</li>';
}

$html .= '</ul>';
$html .= '</div>';

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is assembled above from wp_kses_data()/esc_url()/esc_attr()/esc_html() output.
echo $html;
