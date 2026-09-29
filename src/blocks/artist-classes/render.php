<?php

/**
 * Artist Classes Block — server-side render. Lists class posts whose ACF taught_by includes the artist, newest first.
 */

$artist_id = isset($block->context['postId']) ? (int) $block->context['postId'] : get_the_ID();

if ( ! $artist_id) {
  return;
}

$classes = get_posts(
    array(
      'post_type'      => 'class',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- taught_by is a serialized ACF post_object array.
      'meta_query'     => array(
        array(
          'key'     => 'taught_by',
          'value'   => '"' . $artist_id . '"',
          'compare' => 'LIKE',
        ),
      ),
    )
);

if (empty($classes)) {
  return;
}

// date_start is stored Ymd, so a string sort is chronological.
usort(
    $classes,
    static function ($a, $b) {
      return strcmp((string) get_post_meta($b->ID, 'date_start', true), (string) get_post_meta($a->ID, 'date_start', true));
    }
);

$items = array();

foreach ($classes as $class) {
  $date_start = (string) get_post_meta($class->ID, 'date_start', true);
  $year       = preg_match('/^\d{4}/', $date_start) ? substr($date_start, 0, 4) : '';
  $sessions   = get_the_terms($class->ID, 'session');
  $programs   = get_the_terms($class->ID, 'program');
  $session    = is_array($sessions) ? implode(', ', wp_list_pluck($sessions, 'name')) : '';
  $program    = is_array($programs) ? implode(', ', wp_list_pluck($programs, 'name')) : '';
  $when       = trim($session . ' ' . $year);

  $item = '<li class="class"><a href="' . esc_url(get_permalink($class)) . '"><span class="title">' . esc_html(get_the_title($class)) . '</span></a>';

  $parts = array();
  if ('' !== $program) { $parts[] = '<span class="program">' . esc_html($program) . '</span>';
  }
  if ('' !== $when) {    $parts[] = '<span class="date">' . esc_html($when) . '</span>';
  }
  if ( ! empty($parts)) { $item .= '<p>' . implode('<br>', $parts) . '</p>';
  }

  $item   .= '</li>';
  $items[] = $item;
}

echo '<div ' . wp_kses_data(get_block_wrapper_attributes()) . '>';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $items entries are assembled above from esc_url()/esc_html() output.
echo '<ul class="artist-classes-ul">' . implode('', $items) . '</ul>';
echo '</div>';
