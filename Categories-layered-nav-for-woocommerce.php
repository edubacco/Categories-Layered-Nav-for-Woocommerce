<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name: Categories Layered Navigation For Woocommerce
 * Plugin URI: https://github.com/edubacco/Categories-Layered-Nav-for-Woocommerce
 * Description: This plug in adds a Widget that lets you add filters for taxonomies like Product Categories to the Layered Nav in Woocommerce. Requires Woocommerce.
 * Version: 1.0
 * Author: edubacco (thanks to oscb)
 * License: GPL2
 * WC requires at least: 2.6.0
 * WC tested up to: 2.6.4
 *
 * @author edubacco (Eduardo Silvi)
 * @requires Woocommerce
 */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	// Woocommerce is active

	// Define Constants
	define( 'OB_PRODUCTS_CATEGORY_TAXONOMY', 'product_cat' );

	/**
	 * ob_set_global_chosen_cat_attributes function
	 *
	 * Sets a global $chosen_cat_attributes to include the Product Categories
	 *
	 * @param array $filtered_posts
	 *
	 * @return array $filtered_posts
	 */
	function ob_set_global_chosen_cat_attributes($filtered_posts ) {
		global $_chosen_cat_attributes, $wp_query;

		$taxonomies = get_object_taxonomies(['product']);
		$qv =& $wp_query->query_vars;

		foreach ($taxonomies as $taxonomy_name) {

			$taxonomy        = wc_sanitize_taxonomy_name( $taxonomy_name );
			$name            = 'filter_' . $taxonomy_name;
			$query_type_name = 'query_type_' . $taxonomy_name;

			if ( ! empty( $qv[ $name ] ) && taxonomy_exists( $taxonomy ) ) {

				$_chosen_cat_attributes[ $taxonomy ]['terms'] = explode( ',', $qv[ $name ] );

				if ( empty( $qv[ $query_type_name ] ) || ! in_array( strtolower( $qv[ $query_type_name ] ), array(
						'and',
						'or'
					) )
				) {
					$_chosen_cat_attributes[ $taxonomy ]['query_type'] = apply_filters( 'woocommerce_layered_nav_default_query_type', 'and' );
				} else {
					$_chosen_cat_attributes[ $taxonomy ]['query_type'] = strtolower( $qv[ $query_type_name ] );
				}

			}

		}

		if ( !empty( $qv['filter_onsale'] ) ) {
			$_chosen_cat_attributes['onsale'] = 1;
		}

		if (!$_chosen_cat_attributes) $_chosen_cat_attributes = array();

		return $filtered_posts;
	}

	/**
	 * ob_layered_nav_widget
	 *
	 * Adds the Widgets Classes after the Woocommmerce plugins are loaded so it
	 * can extend the Layered Nav Plugin
	 *
	 * @return void
	 */
	function ob_layered_nav_widget() {
		include_once( 'widgets/OB_Widget_Layered_Nav_Categories.php' );
		register_widget( 'OB_Widget_Layered_Nav_Categories' );

		include_once( 'widgets/OB_Widget_Layered_Nav_Onsale.php' );
		register_widget( 'OB_Widget_Layered_Nav_Onsale' );

		include_once('widgets/OB_Widget_Layered_Nav_Filters.php');
		unregister_widget('WC_Widget_Layered_Nav_Filters');
		register_widget( 'OB_Widget_Layered_Nav_Filters' );

	}

	/**
	 * loop_shop_post_in_layered_cat_filters
	 *
	 * filter products for chosen layered filter
	 *
	 * @param $ids array
	 * @return array
	 */
	function loop_shop_post_in_layered_cat_filters($ids) {
		if (!is_main_query())
			return;

		global $_chosen_cat_attributes;
		if ($_chosen_cat_attributes) {
			$products_ids =  [];
			$q['post_type'] = 'product';
			$q['posts_per_page'] = -1;
			foreach ($_chosen_cat_attributes as $taxonomy => $data) {
				$q['tax_query'][] = [
					'taxonomy' => $taxonomy,
					'field' => 'slug',
					'terms' => $data['terms'],
					'operator' => 'and' === $data['query_type'] ? 'AND' : 'IN',
					'include_children' => false,
				];
				$current_cat = get_queried_object();
				if ($current_cat) {
					$q['tax_query']['relation'] = 'AND';
					$q['tax_query'][] = [
						'taxonomy' => $current_cat->taxonomy,
						'field' => 'slug',
						'terms' => $current_cat->slug,
					];
				}

				$products = get_posts($q);
				$products_ids = array_merge($products_ids, wp_list_pluck($products, 'ID'));
			}
			$ids = array_merge($products_ids, $ids);
		}

		if (!empty($_chosen_cat_attributes['onsale']) && $_chosen_cat_attributes['onsale']) {
			$ids_onsale = wc_get_product_ids_on_sale();
			if (empty($ids) ){
				//we want just on sale product
				$ids = $ids_onsale;
			}
			if ( !empty($ids) ) {
				$ids = array_intersect($ids, $ids_onsale);
			}
		}

		return array_unique($ids);
	}

	function add_layered_filter_query_vars() {
		global $wp;
		$taxonomies = get_object_taxonomies(['product']);
		foreach ($taxonomies as $taxonomy_name) {
			$taxonomy_name = wc_sanitize_taxonomy_name($taxonomy_name);
			$name            = 'filter_' . $taxonomy_name;
			$query_type_name = 'query_type_' . $taxonomy_name;
			$wp->add_query_var($name);
			$wp->add_query_var($query_type_name);
		}

		//adding "onsale" filter
		$wp->add_query_var('filter_onsale');
	}

	// Set Actions and Filters
	add_action( 'init', 'add_layered_filter_query_vars');

	add_action( 'parse_query', 'ob_set_global_chosen_cat_attributes');

	add_filter( 'loop_shop_post_in', 'loop_shop_post_in_layered_cat_filters', 5, 1 );

	add_action( 'widgets_init', 'ob_layered_nav_widget', 11 );
}