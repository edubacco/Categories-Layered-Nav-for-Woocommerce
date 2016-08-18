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
	 * ob_add_categories_filter function
	 *
	 * Sets a global $chosen_cat_attributes to include the Product Categories
	 *
	 * @param array $filtered_posts
	 *
	 * @return array $filtered_posts
	 */
	function ob_add_categories_filter( $filtered_posts ) {
		global $_chosen_cat_attributes;

		$taxonomies = get_object_taxonomies(['product']);

		foreach ($taxonomies as $taxonomy_name) {

			$taxonomy        = wc_sanitize_taxonomy_name( $taxonomy_name );
			$name            = 'filter_' . $taxonomy_name;
			$query_type_name = 'query_type_' . $taxonomy_name;

			if ( ! empty( $_GET[ $name ] ) && taxonomy_exists( $taxonomy ) ) {

				$_chosen_cat_attributes[ $taxonomy ]['terms'] = explode( ',', $_GET[ $name ] );

				if ( empty( $_GET[ $query_type_name ] ) || ! in_array( strtolower( $_GET[ $query_type_name ] ), array(
						'and',
						'or'
					) )
				) {
					$_chosen_cat_attributes[ $taxonomy ]['query_type'] = apply_filters( 'woocommerce_layered_nav_default_query_type', 'and' );
				} else {
					$_chosen_cat_attributes[ $taxonomy ]['query_type'] = strtolower( $_GET[ $query_type_name ] );
				}

			}

		}

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

		include_once('widgets/OB_Widget_Layered_Nav_Filters.php');
		unregister_widget('WC_Widget_Layered_Nav');
		register_widget( 'OB_Widget_Layered_Nav_Filters' );

	}

	function add_tax_query_for_layered_cat_filters($tax_query, $wc_query) {
		global $_chosen_cat_attributes;

		if (!$_chosen_cat_attributes) ob_add_categories_filter([]);

		// Layered nav filters on terms
		if (0 && $_chosen_cat_attributes ) {
			foreach ( $_chosen_cat_attributes as $taxonomy => $data ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $data['terms'],
					'operator' => 'and' === $data['query_type'] ? 'AND' : 'IN',
					'include_children' => false,
				);
			}
		}

		return $tax_query;
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
		global $_chosen_cat_attributes;
		if ($_chosen_cat_attributes) {
			$products_ids =  [];
			$q['post_type'] = 'product';
			foreach ($_chosen_cat_attributes as $taxonomy => $data) {
				$q['tax_query'][] = [
					'taxonomy' => $taxonomy,
					'field' => 'slug',
					'terms' => $data['terms'],
					'operator' => 'and' === $data['query_type'] ? 'AND' : 'IN',
					'include_children' => false,
				];
				$current_cat = get_queried_object();
				if ($current_cat)
					$q[$current_cat->taxonomy] = $current_cat->slug;

				$products = get_posts($q);
				$products_ids = array_merge($products_ids, wp_list_pluck($products, 'ID'));
			}
			$ids = array_merge($products_ids, $ids);
		}
		return array_unique($ids);
	}

	// Set Actions and Filters
	add_action( 'widgets_init', 'ob_layered_nav_widget', 11 );

	add_filter( 'loop_shop_post_in', 'loop_shop_post_in_layered_cat_filters', 5, 1 );

	add_filter( 'woocommerce_product_query_tax_query', 'add_tax_query_for_layered_cat_filters', 10, 2);

}