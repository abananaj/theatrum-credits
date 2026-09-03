<?php

/**
 * Artist Credits Block — server-side render. Lists productions an artist is credited in, newest first.
 * credit_date is a Unix timestamp string (ACF "opening" field, 'U' format); year below is derived from it.
 */

$post_id = get_the_ID();

if (! $post_id) {
  return;
}

$credits = theatrum_credits_get_artist_productions($post_id);

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
  : '240px';

$flex_style = sprintf('justify-content: %s; align-items: %s; --credit-width: %s;', $justify_content, $align_items, $item_width);

$items = array();

foreach ($credits as $row) {
  $production_id    = (int) $row->credit_production;
  $production_title = get_the_title($production_id);
  $production_url   = get_permalink($production_id);
  $display_role     = $row->credit_role ?: $row->credit_role_group;
  $year             = $row->credit_date
    ? gmdate('Y', is_numeric($row->credit_date) ? (int) $row->credit_date : strtotime($row->credit_date))
    : '';

  $item = '<li class="credit"><a href="' . esc_url($production_url) . '"><span class="title">' . esc_html($production_title) . '</span></a>';

  $parts = array();
  if (! empty($display_role)) $parts[] = '<span class="role">' . esc_html($display_role) . ',</span><br>';
  if (! empty($year))         $parts[] = '<span class="date">' . esc_html($year) . '</span>';
  if (! empty($parts))        $item   .= '<p>' . implode('', $parts) . '</p>';

  $item   .= '</li>';
  $items[] = $item;
}

echo '<div ' . wp_kses_data(get_block_wrapper_attributes()) . '>';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $items entries are assembled above from esc_url()/esc_html() output.
echo '<ul class="artist-credits-ul" style="' . esc_attr($flex_style) . '">' . implode('', $items) . '</ul>';
echo '</div>';
