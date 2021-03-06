<?php

/**
 * Extends Layered Navigation Widget
 *
 * @author   Eduardo Silvi
 * @category Widgets
 * @package  CategoriesLayeredNavForWoocommerce/Widgets
 * @version  1.0.0
 * @extends  WC_Widget_Layered_Nav
 */
class OB_Widget_Layered_Nav_Filters extends WC_Widget_Layered_Nav_Filters {

    /**
     * Output widget.
     *
     * @see WP_Widget
     *
     * @param array $args
     * @param array $instance
     */
    public function widget( $args, $instance ) {
        global $_chosen_cat_attributes;
        if ( ! is_post_type_archive( 'product' ) && ! is_tax( get_object_taxonomies( 'product' ) ) ) {
            return;
        }

        $_chosen_attributes = array_merge($_chosen_cat_attributes, WC_Query::get_layered_nav_chosen_attributes());
        $min_price          = isset( $_GET['min_price'] ) ? wc_clean( $_GET['min_price'] )   : 0;
        $max_price          = isset( $_GET['max_price'] ) ? wc_clean( $_GET['max_price'] )   : 0;
        $min_rating         = isset( $_GET['min_rating'] ) ? absint( $_GET['min_rating'] ) : 0;
        $onsale             = isset( $_GET['filter_onsale']) && $_GET['filter_onsale'] == 1;

        if ( 0 < count( $_chosen_attributes ) || 0 < $min_price || 0 < $max_price || 0 < $min_rating || !$onsale ) {

            $this->widget_start( $args, $instance );

            echo '<ul>';

            // Attributes
            if ( ! empty( $_chosen_attributes ) ) {
                foreach ( $_chosen_attributes as $taxonomy => $data ) {
                    foreach ( $data['terms'] as $term_slug ) {
                        if ( ! $term = get_term_by( 'slug', $term_slug, $taxonomy ) ) {
                            continue;
                        }

                        //todo: salvati un elenco di query args da riutilizzare in giro..
                        $filter_name    = 'filter_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) );
                        $current_filter = isset( $_GET[ $filter_name ] ) ? explode( ',', wc_clean( $_GET[ $filter_name ] ) ) : array();
                        $current_filter = array_map( 'sanitize_title', $current_filter );
                        $new_filter      = array_diff( $current_filter, array( $term_slug ) );

                        $link = remove_query_arg( array( 'add-to-cart', $filter_name ) );

                        if ( sizeof( $new_filter ) > 0 ) {
                            $link = add_query_arg( $filter_name, implode( ',', $new_filter ), $link );
                        }

                        echo '<li class="chosen"><a title="' . esc_attr__( 'Remove filter', 'woocommerce' ) . '" href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></li>';
                    }
                }
            }

            if ( $min_price ) {
                $link = remove_query_arg( 'min_price' );
                echo '<li class="chosen"><a title="' . esc_attr__( 'Remove filter', 'woocommerce' ) . '" href="' . esc_url( $link ) . '">' . __( 'Min', 'woocommerce' ) . ' ' . wc_price( $min_price ) . '</a></li>';
            }

            if ( $max_price ) {
                $link = remove_query_arg( 'max_price' );
                echo '<li class="chosen"><a title="' . esc_attr__( 'Remove filter', 'woocommerce' ) . '" href="' . esc_url( $link ) . '">' . __( 'Max', 'woocommerce' ) . ' ' . wc_price( $max_price ) . '</a></li>';
            }

	        if ( $min_rating ) {
		        $link = remove_query_arg( 'min_rating' );
		        echo '<li class="chosen"><a title="' . esc_attr__( 'Remove filter', 'woocommerce' ) . '" href="' . esc_url( $link ) . '">' . sprintf( __( 'Rated %s and above', 'woocommerce' ), $min_rating ) . '</a></li>';
	        }

	        if ( $onsale ) {
		        $link = remove_query_arg( 'onsale' );
		        echo '<li class="chosen"><a title="' . esc_attr__( 'Remove filter', 'woocommerce' ) . '" href="' . esc_url( $link ) . '">In sconto</a></li>';
	        }

            echo '</ul>';

            $reset_link = '/';
            $query_obj = get_queried_object();
            if (is_a($query_obj, 'WP_Term')) {
                $reset_link = get_term_link($query_obj);
            }

            echo "<a href='$reset_link'>".__('Reset filters', 'woocommerce').'</a>';

            $this->widget_end( $args );
        }
    }
}