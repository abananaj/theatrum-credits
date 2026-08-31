<?php

if (! defined('ABSPATH')) {
  exit;
}

/**
 * Register Credit Post Types
 */

function ct_register_credit()
{
	register_post_type('credit', array(
		'labels' => array(
			'name'          => 'Credits',
			'singular_name' => 'Production Credit',
			'menu_name'     => 'Credits',
			'all_items'     => 'Production Credits',
			'edit_item'     => 'Edit Production Credit',
			'view_item'     => 'View Production Credit',
			'view_items'    => 'View Production Credits',
			'add_new_item'  => 'Add New Production Credit',
			'add_new'       => 'Add New Production Credit',
			'new_item'      => 'New Production Credit',
		),
		'description'        => 'Production credits connecting artists & productions',
		'public'             => true,
		'show_in_menu'       => false,
		'show_in_nav_menus'  => false,
		'show_in_admin_bar'  => true,
		'show_in_rest'       => true,
		'menu_icon'          => 'dashicons-admin-post',
		'supports'           => array('title', 'custom-fields'),
		'taxonomies'         => array('season', 'series'),
		'has_archive'        => 'theatrum-credits',
		'rewrite'            => false,
		'delete_with_user'   => false,
		'hierarchical'       => false,
	));
}
