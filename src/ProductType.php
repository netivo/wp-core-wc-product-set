<?php
/**
 * Created by Netivo for Netivo Modules
 * User: manveru
 * Date: 10.12.2025
 * Time: 12:50
 *
 */

namespace Netivo\Module\WooCommerce\Set;

use Netivo\WooCommerce\Product\Type;

if ( ! defined( 'ABSPATH' ) ) {
    header( 'HTTP/1.0 403 Forbidden' );
    exit;
}

class ProductType extends Type {

    public static function register(): void {
        add_filter( 'woocommerce_product_class', [ self::class, 'product_class' ], 10, 4 );
        add_action( 'woocommerce_package_add_to_cart', [ self::class, 'add_to_cart' ] );
        add_filter( 'product_type_selector', [ self::class, 'add_to_select' ] );
        add_action( 'woocommerce_check_cart_items', [ self::class, 'validate_cart' ] );
        add_action( 'woocommerce_checkout_create_order', [ self::class, 'save_order' ] );
        add_filter( 'woocommerce_quantity_input_step', [ self::class, 'quantity_input_step' ], 10, 2 );

        if ( is_admin() ) {
            add_filter( 'woocommerce_product_data_tabs', [ self::class, 'modify_pricing_tab' ] );
            add_filter( 'woocommerce_hidden_order_itemmeta', [ self::class, 'hide_order_item_meta' ] );

            add_action( 'woocommerce_product_options_general_product_data', [ self::class, 'display_price_options' ] );
            add_action( 'save_post', [ self::class, 'do_save' ] );

            add_action( 'admin_footer', [ self::class, 'admin_footer_js' ] );
        }
    }

    /**
     * Sets the class name for product type.
     *
     * @param string $class_name Generated class name for type.
     * @param string $product_type Product type.
     * @param string $variation Is product a variation.
     * @param string $product_id Product id.
     *
     * @return string
     */
    public static function product_class( string $class_name, string $product_type, string $variation, string $product_id ): string {
        if ( $product_type === 'set' ) {
            return self::class;
        }

        return $class_name;
    }

    /**
     * Add to cart view for package product type.
     */
    public static function add_to_cart(): void {
        wc_get_template( 'single-product/add-to-cart/simple.php' );
    }

    /**
     * Add product type to select.
     *
     * @param array $types Types of the products.
     *
     * @return array
     */
    public static function add_to_select( array $types ): array {
        $types['set'] = __( 'Komplet', 'netivo' );

        return $types;
    }

    /**
     * Validates the contents of the cart to ensure compliance with specific product packaging rules.
     *
     * Checks if products of type 'set' are added to the cart in quantities that are multiples
     * of the predefined number of items in a box. If the validation fails, an error notice
     * is added to inform the user.
     *
     * @return bool Returns true if the cart contents pass validation, otherwise false.
     */
    public static function validate_cart(): bool {
        foreach ( WC()->cart->cart_contents as $cart_content_product ) {
            $product  = $cart_content_product['data'];
            $quantity = $cart_content_product['quantity'];
            if ( $product->get_type() === 'set' ) {
                $items = $product->get_meta( '_items_in_box' );
                if ( ! empty( $items ) ) {
                    $items = (int) $items;
                    if ( $quantity % $items !== 0 ) {
                        wc_add_notice( sprintf( __( '<strong>Produkt %s sprzedajemy tylko w kompletach po %d</strong>', 'netivo' ), $product->get_name(), $items ), 'error' );

                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Saves pickup place when shipping method is local pickup.
     *
     * @param \WC_Order $order Order to save.
     */
    public static function save_order( \WC_Order $order ): void {
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_type() == 'line_item' ) {
                $pr = $item->get_product();
                if ( $pr->get_type() == 'package' ) {
                    $item->add_meta_data( '_items_in_box', $pr->get_meta( '_items_in_box' ) );
                    $item->save_meta_data();
                }
            }
        }
    }

    /**
     * Add classes to special tabs.
     *
     * @param array $tabs Current tabs in product data metabox.
     *
     * @return array
     */
    public static function modify_pricing_tab( array $tabs ): array {
        $tabs['inventory']['class'][] = 'show_if_set';

        return $tabs;
    }

    /**
     * Hides items from meta.
     *
     * @param array $items Current items.
     *
     * @return array
     */
    public static function hide_order_item_meta( array $items ): array {
        $items[] = '_items_in_box';

        return $items;
    }

    /**
     * Add script to enable pricing in product type.
     */
    public static function admin_footer_js(): void {
        if ( 'product' != get_post_type() ) :
            return;
        endif;

        ?>
        <script type='text/javascript'>
          jQuery( '.options_group.show_if_simple' ).addClass( 'show_if_set' );
          jQuery( '.form-field._manage_stock_field' ).addClass( 'show_if_set' );
        </script><?php

    }

    /**
     * Add meters settings to price tab in general product data.
     */
    public static function display_price_options(): void {
        global $post, $thepostid, $product_object;

        wp_nonce_field( 'save_product_set', 'product_set_nonce' );

        ?>
        <div class="options_group show_if_set">
            <?php
            woocommerce_wp_text_input(
                    array(
                            'id'        => '_items_in_box',
                            'value'     => $product_object->get_meta( '_items_in_box' ),
                            'data_type' => 'decimal',
                            'label'     => __( 'Ilość sztuk w opakowaniu', 'netivo' ),
                    )
            );
            ?>
        </div>
        <?php
    }


    /**
     * Save meters data.
     *
     * @param int $post_id Saved post id.
     *
     * @return int
     */
    public static function do_save( int $post_id ): int {
        if ( ! isset( $_POST['product_set_nonce'] ) ) {
            return $post_id;
        }
        if ( ! wp_verify_nonce( $_POST['product_set_nonce'], 'save_product_set' ) ) {
            return $post_id;
        }
        if ( ! in_array( $_POST['post_type'], [ 'product' ] ) ) {
            return $post_id;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }
        if ( ! empty( $_POST['_items_in_box'] ) ) {
            update_post_meta( $post_id, '_items_in_box', $_POST['_items_in_box'] );
        } else {
            delete_post_meta( $post_id, '_items_in_box' );
        }

        return $post_id;
    }

    public static function quantity_input_step( $step, $product ): int {
        if ( $product->get_type() === 'set' ) {
            $items = $product->get_meta( '_items_in_box' );
            if ( ! empty( $items ) ) {
                return wc_stock_amount( $items );
            }
        }

        return $step;
    }
}