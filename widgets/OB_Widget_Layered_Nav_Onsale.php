<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Layered Navigation Widget extended to filter onsale products
 *
 * @author   Eduardo Silvi
 * @category Widgets
 * @package  CategoriesLayeredNavForWoocommerce/Widgets
 * @version  1.0
 * @extends  WC_Widget_Layered_Nav
 */
class OB_Widget_Layered_Nav_Onsale extends WC_Widget_Layered_Nav {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->widget_cssclass    = 'woocommerce widget_layered_nav';
		$this->widget_description = __( 'Shows a selector in a widget which lets you filter for on sale products.', 'woocommerce' );
		$this->widget_id          = 'woocommerce_layered_onsale_nav';
		$this->widget_name        = __( 'Onsale Layered Nav', 'woocommerce' );
		WC_Widget::__construct();
	}


	/**
	 * Init settings adding the product category taxonomy
	 *
	 * @return void
	 */
	public function init_settings() {
		$attribute_array      = array();
//		$category_taxonomy    = get_taxonomies( array( 'name' => OB_PRODUCTS_CATEGORY_TAXONOMY ), 'objects' );
		$category_taxonomies  = get_object_taxonomies(['product'], 'objects');

		foreach ( $category_taxonomies as $category_taxonomy ) {
			$attribute_array[ $category_taxonomy->name ] = $category_taxonomy->label;
		}

		$this->settings = array(
			'title'        => array(
				'type'  => 'text',
				'std'   => __( 'Outlet', 'woocommerce' ),
				'label' => __( 'Title', 'woocommerce' )
			),
		);
	}

	/**
	 * Widget Display Function.
	 *
	 * Added ability to hide the widget if its filter attribute is a category
	 * and the current page is category archive
	 * Changed the way it constructs the filters so that it uses the value
	 * instead of recreating the attribute id.
	 *
	 * @param array $args
	 * @param array $instance
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		global $_chosen_cat_attributes;

		if ( ! is_post_type_archive( 'product' ) && ! is_tax( get_object_taxonomies( 'product' ) ) ) {
			return;
		}

		$current_term = is_tax() ? get_queried_object()->term_id : '';
		$current_tax  = is_tax() ? get_queried_object()->taxonomy : '';

		// Skip Display if we are browsing a product category, no I want it
		/*if ( is_product_category() && $taxonomy == OB_PRODUCTS_CATEGORY_TAXONOMY ) {
			return;
		}*/


		ob_start();

		$this->widget_start( $args, $instance );

		$found = $this->layered_nav_onsale_content();

		$this->widget_end( $args );


		// Force found when option is selected - do not force found on taxonomy attributes
		if ( ! is_tax() && is_array( $_chosen_cat_attributes ) && array_key_exists( $taxonomy, $_chosen_cat_attributes ) ) {
			$found = true;
		}

		if ( ! $found ) {
			ob_end_clean();
		} else {
			echo ob_get_clean();
		}
	}


	protected function get_page_base_url( $taxonomy ) {
		global $_chosen_cat_attributes;

		if ( defined( 'SHOP_IS_ON_FRONT' ) ) {
			$link = home_url();
		} elseif ( is_post_type_archive( 'product' ) || is_page( wc_get_page_id( 'shop' ) ) ) {
			$link = get_post_type_archive_link( 'product' );
		} elseif ( is_product_category() ) {
			$link = get_term_link( get_query_var( 'product_cat' ), 'product_cat' );
		} elseif ( is_product_tag() ) {
			$link = get_term_link( get_query_var( 'product_tag' ), 'product_tag' );
		} else {
			$queried_object = get_queried_object();
			$link = get_term_link( $queried_object->slug, $queried_object->taxonomy );
		}

		// Min/Max
		if ( isset( $_GET['min_price'] ) ) {
			$link = add_query_arg( 'min_price', wc_clean( $_GET['min_price'] ), $link );
		}

		if ( isset( $_GET['max_price'] ) ) {
			$link = add_query_arg( 'max_price', wc_clean( $_GET['max_price'] ), $link );
		}

		// Orderby
		if ( isset( $_GET['orderby'] ) ) {
			$link = add_query_arg( 'orderby', wc_clean( $_GET['orderby'] ), $link );
		}

		/**
		 * Search Arg.
		 * To support quote characters, first they are decoded from &quot; entities, then URL encoded.
		 */
		if ( get_search_query() ) {
			$link = add_query_arg( 's', rawurlencode( htmlspecialchars_decode( get_search_query() ) ), $link );
		}

		// Post Type Arg
		if ( isset( $_GET['post_type'] ) ) {
			$link = add_query_arg( 'post_type', wc_clean( $_GET['post_type'] ), $link );
		}

		// Min Rating Arg
		if ( isset( $_GET['min_rating'] ) ) {
			$link = add_query_arg( 'min_rating', wc_clean( $_GET['min_rating'] ), $link );
		}

		// All current filters
		if ( $_chosen_attributes = array_merge(WC_Query::get_layered_nav_chosen_attributes(), $_chosen_cat_attributes) ) {
			foreach ( $_chosen_attributes as $name => $data ) {
				if ( $name === $taxonomy ) {
					continue;
				}
				$filter_name = sanitize_title( str_replace( 'pa_', '', $name ) );
				if ( ! empty( $data['terms'] ) ) {
					$link = add_query_arg( 'filter_' . $filter_name, implode( ',', $data['terms'] ), $link );
				}
				if ( 'or' == $data['query_type'] ) {
					$link = add_query_arg( 'query_type_' . $filter_name, 'or', $link );
				}
			}
		}

		return $link;
	}

	protected function layered_nav_onsale_content( ) {
		global $_chosen_cat_attributes;

		// List display
		echo '<ul>';

		$filter_active = isset($_chosen_cat_attributes['onsale']) && $_chosen_cat_attributes['onsale'];

		global $wp_query;
		$found_posts = $wp_query->posts;
		$found_posts_id = wp_list_pluck($found_posts, 'ID');
		$count = count(array_intersect(wc_get_product_ids_on_sale(), $found_posts_id));
		$found              = false;

		// Only show options with count > 0
		if ( 0 < $count ) {
			$found = true;
		}

		$link = $this->get_page_base_url( '' );

		if ( empty( $filter_active ) ) {
			$link = add_query_arg( 'filter_onsale', 1, $link );
		}

		echo '<li class="wc-layered-nav-term ' . ( $filter_active ? 'chosen ' : '' ) . '">';

		echo ( $count > 0 || $filter_active ) ? '<a href="' . esc_url( apply_filters( 'woocommerce_layered_nav_link', $link ) ) . '">' : '<a class="disabled">';

		echo "Solo prodotti scontati";

		echo ( $count > 0 || $filter_active ) ? '</a> ' : '</a> ';

		echo apply_filters( 'woocommerce_layered_nav_onsale_count', '<span class="count">(' . absint( $count ) . ')</span>', $count );

		echo '</li>';

		echo '</ul>';

		return $found;
	}
}