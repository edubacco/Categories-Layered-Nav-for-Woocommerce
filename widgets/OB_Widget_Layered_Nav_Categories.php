<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Layered Navigation Widget extended to include Categories
 *
 * @author   Oscar Bazaldua
 * @category Widgets
 * @package  CategoriesLayeredNavForWoocommerce/Widgets
 * @version  1.0
 * @extends  WC_Widget_Layered_Nav
 */
class OB_Widget_Layered_Nav_Categories extends WC_Widget_Layered_Nav {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->widget_cssclass    = 'woocommerce widget_layered_nav';
		$this->widget_description = __( 'Shows a custom attribute in a widget which lets you narrow down the list of products when viewing product categories.', 'woocommerce' );
		$this->widget_id          = 'woocommerce_layered_cat_nav';
		$this->widget_name        = __( 'Categories Layered Nav', 'woocommerce' );
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
				'std'   => __( 'Filter by', 'woocommerce' ),
				'label' => __( 'Title', 'woocommerce' )
			),
			'attribute'    => array(
				'type'    => 'select',
				'std'     => '',
				'label'   => __( 'Attribute', 'woocommerce' ),
				'options' => $attribute_array
			),
			'query_type'   => array(
				'type'    => 'select',
				'std'     => 'and',
				'label'   => __( 'Query type', 'woocommerce' ),
				'options' => array(
					'and' => __( 'AND', 'woocommerce' ),
					'or'  => __( 'OR', 'woocommerce' )
				)
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
		$taxonomy     = isset( $instance['attribute'] ) ? $instance['attribute'] : $this->settings['attribute']['std']; // Changed this to use the attribute as is, since we set it now as the taxonomy name (not as label)
		$query_type   = isset( $instance['query_type'] ) ? $instance['query_type'] : $this->settings['query_type']['std'];

		// Skip Display if we are browsing a product category
		if ( is_product_category() && $taxonomy == OB_PRODUCTS_CATEGORY_TAXONOMY ) {
			return;
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$get_terms_args = array( 'hide_empty' => '1' );

		$orderby = wc_attribute_orderby( $taxonomy );

		switch ( $orderby ) {
			case 'name' :
				$get_terms_args['orderby']    = 'name';
				$get_terms_args['menu_order'] = false;
				break;
			case 'id' :
				$get_terms_args['orderby']    = 'id';
				$get_terms_args['order']      = 'ASC';
				$get_terms_args['menu_order'] = false;
				break;
			case 'menu_order' :
				$get_terms_args['menu_order'] = 'ASC';
				break;
		}

		$terms = get_terms( $taxonomy, $get_terms_args );

		if ( 0 < count( $terms ) ) {

			ob_start();

			$this->widget_start( $args, $instance );

			$found = $this->layered_nav_list($terms, $taxonomy, $query_type);

			$this->widget_end( $args );

			if ( ! $found ) {
				ob_end_clean();
			} else {
				echo ob_get_clean();
			}
		}
	}


	protected function layered_nav_list( $terms, $taxonomy, $query_type ) {
		global $_chosen_cat_attributes;

		// List display
		echo '<ul>';

		$term_counts        = $this->get_filtered_term_product_counts( wp_list_pluck( $terms, 'term_id' ), $taxonomy, $query_type );
		//$_chosen_cat_attributes = WC_Query::get_layered_nav_chosen_attributes();
		$found              = false;

		foreach ( $terms as $term ) {
			$current_values    = isset( $_chosen_cat_attributes[ $taxonomy ]['terms'] ) ? $_chosen_cat_attributes[ $taxonomy ]['terms'] : array();
			$option_is_set     = in_array( $term->slug, $current_values );
			$count             = isset( $term_counts[ $term->term_id ] ) ? $term_counts[ $term->term_id ] : 0;

			// skip the term for the current archive
			if ( $this->get_current_term_id() === $term->term_id ) {
				continue;
			}

			// Only show options with count > 0
			if ( 0 < $count ) {
				$found = true;
			} elseif ( 'and' === $query_type && 0 === $count && ! $option_is_set ) {
				continue;
			}

			$filter_name    = 'filter_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) );
			$current_filter = isset( $_GET[ $filter_name ] ) ? explode( ',', wc_clean( $_GET[ $filter_name ] ) ) : array();
			$current_filter = array_map( 'sanitize_title', $current_filter );

			if ( ! in_array( $term->slug, $current_filter ) ) {
				$current_filter[] = $term->slug;
			}

			$link = $this->get_page_base_url( $taxonomy );

			// Add current filters to URL.
			foreach ( $current_filter as $key => $value ) {
				// Exclude query arg for current term archive term
				if ( $value === $this->get_current_term_slug() ) {
					unset( $current_filter[ $key ] );
				}

				// Exclude self so filter can be unset on click.
				if ( $option_is_set && $value === $term->slug ) {
					unset( $current_filter[ $key ] );
				}
			}

			if ( ! empty( $current_filter ) ) {
				$link = add_query_arg( $filter_name, implode( ',', $current_filter ), $link );

				// Add Query type Arg to URL
				if ( $query_type === 'or' && ! ( 1 === sizeof( $current_filter ) && $option_is_set ) ) {
					$link = add_query_arg( 'query_type_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) ), 'or', $link );
				}
			}

			echo '<li class="wc-layered-nav-term ' . ( $option_is_set ? 'chosen' : '' ) . '">';

			echo ( $count > 0 || $option_is_set ) ? '<a href="' . esc_url( apply_filters( 'woocommerce_layered_nav_link', $link ) ) . '">' : '<span>';

			echo esc_html( $term->name );

			echo ( $count > 0 || $option_is_set ) ? '</a> ' : '</span> ';

			echo apply_filters( 'woocommerce_layered_nav_count', '<span class="count">(' . absint( $count ) . ')</span>', $count, $term );

			echo '</li>';
		}

		echo '</ul>';

		return $found;
	}
}