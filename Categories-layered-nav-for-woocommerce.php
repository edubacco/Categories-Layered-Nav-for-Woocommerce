<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name: Categories Layered Navigation For Woocommerce
 * Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: This plug in adds a Widget that lets you add filters for taxonomies like Product Categories to the Layered Nav in Woocommerce. Requires Woocommerce.
 * Version: 1.0
 * Author: oscb
 * Author URI: http://oscarbazaldua.com
 * License: GPL2
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 *
 * @author oscb (Oscar Bazaldua)
 * @requires Woocommerce
 */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	// Woocommerce is active

	// Define Constants
	define( 'OB_PRODUCTS_CATEGORY_TAXONOMY', 'product_cat' );

	/**
	 * ob_add_categories_filter function
	 *
	 * Sets the global $chosen_attributes to include the Product Categories
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
	 * ob_replace_layered_nav_widget
	 *
	 * Adds the Widget Class after the Woocommmerce plugins are loaded so it
	 * can extend the Layered Nav Plugin
	 *
	 * @return void
	 */
	function ob_add_layered_nav_widget() {
		include_once( 'widgets/OB_Widget_Layered_Nav_Categories.php' );
		register_widget( 'OB_Widget_Layered_Nav_Categories' );
	}

	function add_tax_query_for_layered_cat_filters($tax_query, $wc_query) {
		global $_chosen_cat_attributes;

		if (!$_chosen_cat_attributes) ob_add_categories_filter([]);

		// Layered nav filters on terms
		if ( $_chosen_cat_attributes ) {
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

	// Set Actions and Filters
	add_action( 'widgets_init', 'ob_add_layered_nav_widget', 11 );

	//add_filter( 'loop_shop_post_in', 'ob_add_categories_filter', 5, 1 );

	add_filter( 'woocommerce_product_query_tax_query', 'add_tax_query_for_layered_cat_filters', 10, 2);

}

//todo: il widget che mostra i layered attivi non mostra il layered scelto attualmente. sistema questa cosa o trova una
// soluzione alternativa a sti cazzo di layered filter