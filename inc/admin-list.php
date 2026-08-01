<?php

if (! class_exists('WP_List_Table')) {
  require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Theatrum_Credits_List_Table extends WP_List_Table
{
  public function __construct()
  {
    parent::__construct(array(
      'singular' => 'credit',
      'plural'   => 'credits',
      'ajax'     => false,
    ));
  }

  public function get_columns()
  {
    return array(
      'credit_production' => 'Production',
      'credit_artist'     => 'Artist',
      'credit_role_group' => 'Role Group',
      'credit_role'       => 'Role',
      'credit_order'      => 'Order',
    );
  }

  public function get_sortable_columns()
  {
    return array(
      'credit_production' => array('credit_production', false),
      'credit_role_group' => array('credit_role_group', false),
      'credit_order'      => array('credit_order', false),
    );
  }

  public function prepare_items()
  {
    global $wpdb;

    $per_page     = 50;
    $current_page = $this->get_pagenum();
    $offset       = ($current_page - 1) * $per_page;

    $allowed_orderby = array('credit_production', 'credit_role_group', 'credit_order');
    $orderby = in_array($_GET['orderby'] ?? '', $allowed_orderby, true) ? $_GET['orderby'] : 'credit_production';
    $order   = (($_GET['order'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

    $table  = THEATRUM_CREDITS_TABLE;
    $search = sanitize_text_field($_GET['s'] ?? '');

    if ($search) {
      $like  = '%' . $wpdb->esc_like($search) . '%';
      $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE credit_title LIKE %s OR credit_role LIKE %s",
        $like,
        $like
      ));
      $this->items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE credit_title LIKE %s OR credit_role LIKE %s ORDER BY $orderby $order LIMIT %d OFFSET %d",
        $like,
        $like,
        $per_page,
        $offset
      ));
    } else {
      $total       = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
      $this->items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY $orderby $order LIMIT %d OFFSET %d",
        $per_page,
        $offset
      ));
    }

    $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

    $this->set_pagination_args(array(
      'total_items' => $total,
      'per_page'    => $per_page,
      'total_pages' => (int) ceil($total / $per_page),
    ));
  }

  public function column_default($item, $column_name)
  {
    switch ($column_name) {
      case 'credit_production':
        $title = get_the_title($item->credit_production);
        $link  = get_edit_post_link($item->credit_production);
        return $link ? '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>' : esc_html($title);

      case 'credit_artist':
        $title = get_the_title($item->credit_artist);
        $link  = get_edit_post_link($item->credit_artist);
        return $link ? '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>' : esc_html($title);

      case 'credit_role_group':
        return esc_html($item->credit_role_group);

      case 'credit_role':
        return esc_html($item->credit_role);

      case 'credit_order':
        return (int) $item->credit_order;
    }
    return '';
  }

  public function no_items()
  {
    echo 'No credits found.';
  }
}

function theatrum_credits_admin_page()
{
  $table = new Theatrum_Credits_List_Table();
  $table->prepare_items();
  ?>
  <div class="wrap">
    <h1 class="wp-heading-inline">Production Credits</h1>
    <hr class="wp-header-end">
    <form method="get">
      <input type="hidden" name="post_type" value="production">
      <input type="hidden" name="page" value="theatrum-credits-list">
      <?php $table->search_box('Search credits', 'credits-search'); ?>
      <?php $table->display(); ?>
    </form>
  </div>
  <?php
}

add_action('admin_menu', function () {
  add_submenu_page(
    'edit.php?post_type=production',
    'Production Credits',
    'Credits',
    'edit_posts',
    'theatrum-credits-list',
    'theatrum_credits_admin_page'
  );
});
