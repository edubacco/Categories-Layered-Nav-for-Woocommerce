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

			$found = false;

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
}