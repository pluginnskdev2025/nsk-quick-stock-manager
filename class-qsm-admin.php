<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NSK_QSM_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_post_nsk_save_stock', array( $this, 'handle_save_stock' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'NSK Quick Stock Manager', 'nsk-quick-stock-manager' ),
            __( 'NSK Stock Manager', 'nsk-quick-stock-manager' ),
            'manage_woocommerce',
            'nsk-quick-stock',
            array( $this, 'render_main_page' ),
            'dashicons-clipboard',
            56
        );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_nsk-quick-stock' !== $hook ) {
            return;
        }

        $inline_js = "
        jQuery(document).ready(function($) {
            $('.nsk-toggle-variations').on('click', function(e) {
                e.preventDefault();
                var productId = $(this).data('id');
                var targetRow = $('.var-row-group-' + productId);
                var icon = $('.toggle-icon-' + productId);
                
                targetRow.toggle();
                if (targetRow.is(':visible')) {
                    icon.text('▼');
                } else {
                    icon.text('▶');
                }
            });

            $('#nsk-expand-all').on('click', function() {
                $('[class^=\"var-row-group-\"]').show();
                $('.nsk-toggle-variations').each(function() {
                    var pid = $(this).data('id');
                    $('.toggle-icon-' + pid).text('▼');
                });
            });

            $('#nsk-collapse-all').on('click', function() {
                $('[class^=\"var-row-group-\"]').hide();
                $('.nsk-toggle-variations').each(function() {
                    var pid = $(this).data('id');
                    $('.toggle-icon-' + pid).text('▶');
                });
            });

            $('.nsk-edit-btn').on('click', function() {
                var stock = $(this).data('stock');
                $('#nsk-product-id').val($(this).data('id'));
                $('#nsk-modal-title').text('Edit: ' + $(this).data('name'));
                $('#nsk-sku').val($(this).data('sku'));
                $('#nsk-regular-price').val($(this).data('regular'));
                $('#nsk-sale-price').val($(this).data('sale'));
                $('#nsk-current-stock').val(stock);
                $('#nsk-current-stock-text').text(stock);
                $('#nsk-adjust-qty').val(0);
                $('#nsk-note').val('');
                $('#nsk-edit-modal').css('display', 'flex');
            });
            $('#nsk-modal-close').on('click', function() {
                $('#nsk-edit-modal').hide();
            });
        });
        ";

        wp_register_script( 'nsk-qsm-admin', '', array( 'jquery' ), '1.0.0', true );
        wp_enqueue_script( 'nsk-qsm-admin' );
        wp_add_inline_script( 'nsk-qsm-admin', $inline_js );
    }

    public function render_main_page() {
        if ( isset( $_GET['updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['updated'] ) ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Stock and price updated successfully!', 'nsk-quick-stock-manager' ) . '</p></div>';
        }

        $search_query = isset( $_GET['s_sku'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s_sku'] ) ) ) : '';
        $keyword = mb_strtolower( $search_query );
        ?>
        <div class="wrap" style="font-family: sans-serif;">
            <h1 style="color: #d35400; font-weight: 700;"><?php echo esc_html__( '📦 NSK Quick Stock Manager', 'nsk-quick-stock-manager' ); ?></h1>
            <p style="color: #555;"><?php echo esc_html__( 'Fast WooCommerce stock & price management designed for efficiency.', 'nsk-quick-stock-manager' ); ?></p>
            
            <div style="display: flex; gap: 20px; margin-top: 20px; flex-wrap: wrap; align-items: flex-start;">
                <div style="flex: 2; min-width: 500px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0; font-size: 18px; color: #333;"><?php echo esc_html__( 'Product Inventory', 'nsk-quick-stock-manager' ); ?></h2>
                        
                        <form method="GET" action="" style="display: flex; gap: 5px;">
                            <input type="hidden" name="page" value="nsk-quick-stock">
                            <input type="text" name="s_sku" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php echo esc_attr__( 'Search SKU or Attribute (e.g. XL)...', 'nsk-quick-stock-manager' ); ?>" style="padding: 4px 8px; font-size: 13px; width: 220px;">
                            <button type="submit" class="button button-small" style="background: #d35400; color: #fff; border-color: #d35400;"><?php echo esc_html__( 'Search', 'nsk-quick-stock-manager' ); ?></button>
                            <?php if ( ! empty( $search_query ) ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=nsk-quick-stock' ) ); ?>" class="button button-small"><?php echo esc_html__( 'Reset', 'nsk-quick-stock-manager' ); ?></a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                        <button type="button" id="nsk-expand-all" class="button button-small" style="margin-right: 5px;"><?php echo esc_html__( 'Expand All', 'nsk-quick-stock-manager' ); ?></button>
                        <button type="button" id="nsk-collapse-all" class="button button-small"><?php echo esc_html__( 'Collapse All', 'nsk-quick-stock-manager' ); ?></button>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 5px;">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php echo esc_html__( 'Image', 'nsk-quick-stock-manager' ); ?></th>
                                <th><?php echo esc_html__( 'Product Name', 'nsk-quick-stock-manager' ); ?></th>
                                <th style="width: 90px;"><?php echo esc_html__( 'SKU', 'nsk-quick-stock-manager' ); ?></th>
                                <th style="width: 80px;"><?php echo esc_html__( 'Regular', 'nsk-quick-stock-manager' ); ?></th>
                                <th style="width: 80px;"><?php echo esc_html__( 'Sale', 'nsk-quick-stock-manager' ); ?></th>
                                <th style="width: 70px;"><?php echo esc_html__( 'Stock', 'nsk-quick-stock-manager' ); ?></th>
                                <th style="width: 70px; text-align: center;"><?php echo esc_html__( 'Action', 'nsk-quick-stock-manager' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $products = wc_get_products( array( 'post_type' => 'product', 'posts_per_page' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
                            
                            $displayed_count = 0;
                            if ( ! empty( $products ) ) {
                                foreach ( $products as $product ) {
                                    $product_id   = $product->get_id();
                                    $product_name = $product->get_name();
                                    $image_url    = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                                    if ( ! $image_url ) { $image_url = wc_placeholder_img_src(); }

                                    if ( $product->is_type( 'variable' ) ) {
                                        $variations = $product->get_children();
                                        if ( empty( $variations ) ) continue;

                                        $matching_variations = array();
                                        foreach ( $variations as $var_id ) {
                                            $var_prod = wc_get_product( $var_id );
                                            if ( ! $var_prod ) continue;

                                            $var_sku   = $var_prod->get_sku();
                                            $var_name  = $var_prod->get_name();

                                            if ( empty( $keyword ) ) {
                                                $matching_variations[] = $var_id;
                                            } else {
                                                if ( stripos( $var_sku, $keyword ) !== false || stripos( $var_name, $keyword ) !== false || stripos( $product_name, $keyword ) !== false ) {
                                                    $matching_variations[] = $var_id;
                                                }
                                            }
                                        }

                                        if ( empty( $matching_variations ) && stripos( $product_name, $keyword ) === false && stripos( $product->get_sku(), $keyword ) === false ) {
                                            continue;
                                        }

                                        $vars_to_show = empty( $keyword ) ? $variations : $matching_variations;
                                        if ( empty( $vars_to_show ) ) continue;

                                        $displayed_count++;
                                        $row_display_style = ! empty( $keyword ) ? 'display: table-row; background-color: #fafafa;' : 'display: none; background-color: #fafafa;';
                                        $icon_symbol = ! empty( $keyword ) ? '▼' : '▶';
                                        ?>
                                        <tr style="background-color: #fdfefe; border-top: 2px solid #e2e8f0;">
                                            <td><img src="<?php echo esc_url( $image_url ); ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"></td>
                                            <td>
                                                <a href="#" class="nsk-toggle-variations" data-id="<?php echo esc_attr( $product_id ); ?>" style="color: #d35400; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                    <span class="toggle-icon-<?php echo esc_attr( $product_id ); ?>" style="font-size: 10px; background: #d35400; color: #fff; width: 16px; height: 16px; display: inline-flex; align-items: center; justify-content: center; border-radius: 3px;"><?php echo esc_html( $icon_symbol ); ?></span>
                                                    <?php echo esc_html( $product_name ); ?> 
                                                </a>
                                                <span style="font-size: 11px; background: #edf2f7; color: #4a5568; padding: 2px 6px; border-radius: 4px; margin-left: 5px;"><?php echo esc_html__( 'Variable Product', 'nsk-quick-stock-manager' ); ?></span>
                                            </td>
                                            <td colspan="5" style="color: #718096; font-style: italic; font-size: 12px;"><?php echo esc_html__( 'Click name to expand/collapse variations', 'nsk-quick-stock-manager' ); ?></td>
                                        </tr>
                                        <?php
                                        foreach ( $vars_to_show as $var_id ) {
                                            $variation = wc_get_product( $var_id );
                                            if ( ! $variation ) continue;

                                            $var_sku   = $variation->get_sku();
                                            $var_reg   = $variation->get_regular_price();
                                            $var_sale  = $variation->get_sale_price();
                                            $var_stock = $variation->get_stock_quantity();
                                            $var_name  = $variation->get_name();
                                            $var_image = wp_get_attachment_image_url( $variation->get_image_id(), 'thumbnail' );
                                            if ( ! $var_image ) { $var_image = $image_url; }

                                            $var_log_url = admin_url( 'admin.php?page=nsk-quick-stock-logs&product_id=' . $var_id );
                                            ?>
                                            <tr class="var-row-group-<?php echo esc_attr( $product_id ); ?>" style="<?php echo esc_attr( $row_display_style ); ?>">
                                                <td style="padding-left: 20px;"><img src="<?php echo esc_url( $var_image ); ?>" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px;"></td>
                                                <td>
                                                    <a href="<?php echo esc_url( $var_log_url ); ?>" style="color: #2d3748; text-decoration: none; font-size: 13px;">
                                                        ↳ <?php echo esc_html( $var_name ); ?>
                                                    </a>
                                                </td>
                                                <td style="font-size: 13px;"><?php echo esc_html( $var_sku ? $var_sku : '-' ); ?></td>
                                                <td style="font-size: 13px;"><?php echo esc_html( $var_reg ? get_woocommerce_currency_symbol() . $var_reg : '-' ); ?></td>
                                                <td style="font-size: 13px;"><?php echo esc_html( $var_sale ? get_woocommerce_currency_symbol() . $var_sale : '-' ); ?></td>
                                                <td style="font-size: 13px; font-weight: bold; color: #2b6cb0;"><?php echo esc_html( null !== $var_stock ? $var_stock : 'N/A' ); ?></td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="button button-small nsk-edit-btn" 
                                                        style="color: #d35400; border-color: #d35400;"
                                                        data-id="<?php echo esc_attr( $var_id ); ?>"
                                                        data-name="<?php echo esc_attr( $var_name ); ?>"
                                                        data-sku="<?php echo esc_attr( $var_sku ); ?>"
                                                        data-regular="<?php echo esc_attr( $var_reg ); ?>"
                                                        data-sale="<?php echo esc_attr( $var_sale ); ?>"
                                                        data-stock="<?php echo esc_attr( null !== $var_stock ? $var_stock : 0 ); ?>">
                                                        <?php echo esc_html__( 'Edit', 'nsk-quick-stock-manager' ); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    } else {
                                        $sku = $product->get_sku();
                                        
                                        if ( ! empty( $keyword ) && stripos( $sku, $keyword ) === false && stripos( $product_name, $keyword ) === false ) {
                                            continue;
                                        }

                                        $displayed_count++;
                                        $regular_price = $product->get_regular_price();
                                        $sale_price    = $product->get_sale_price();
                                        $stock_qty     = $product->get_stock_quantity();
                                        $log_url       = admin_url( 'admin.php?page=nsk-quick-stock-logs&product_id=' . $product_id );
                                        ?>
                                        <tr>
                                            <td><img src="<?php echo esc_url( $image_url ); ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"></td>
                                            <td>
                                                <a href="<?php echo esc_url( $log_url ); ?>" style="color: #d35400; text-decoration: none; font-weight: bold;" title="<?php echo esc_attr__( 'Click to view product logs', 'nsk-quick-stock-manager' ); ?>">
                                                    <?php echo esc_html( $product_name ); ?>
                                                </a>
                                            </td>
                                            <td><?php echo esc_html( $sku ? $sku : '-' ); ?></td>
                                            <td><?php echo esc_html( $regular_price ? get_woocommerce_currency_symbol() . $regular_price : '-' ); ?></td>
                                            <td><?php echo esc_html( $sale_price ? get_woocommerce_currency_symbol() . $sale_price : '-' ); ?></td>
                                            <td><?php echo esc_html( null !== $stock_qty ? $stock_qty : 'N/A' ); ?></td>
                                            <td style="text-align: center;">
                                                <button type="button" class="button button-small nsk-edit-btn" 
                                                    style="color: #d35400; border-color: #d35400;"
                                                    data-id="<?php echo esc_attr( $product_id ); ?>"
                                                    data-name="<?php echo esc_attr( $product_name ); ?>"
                                                    data-sku="<?php echo esc_attr( $sku ); ?>"
                                                    data-regular="<?php echo esc_attr( $regular_price ); ?>"
                                                    data-sale="<?php echo esc_attr( $sale_price ); ?>"
                                                    data-stock="<?php echo esc_attr( null !== $stock_qty ? $stock_qty : 0 ); ?>">
                                                    <?php echo esc_html__( 'Edit', 'nsk-quick-stock-manager' ); ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                            }

                            if ( 0 === $displayed_count ) {
                                echo '<tr><td colspan="7" style="text-align: center; padding: 20px;">' . esc_html__( 'No products found matching your search.', 'nsk-quick-stock-manager' ) . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div style="flex: 1; min-width: 250px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 4px solid #d35400;">
                    <h3 style="margin-top: 0; color: #333; font-size: 16px;"><?php echo esc_html__( '💡 About This Plugin', 'nsk-quick-stock-manager' ); ?></h3>
                    <p style="font-size: 13px; color: #666; line-height: 1.5;"><?php echo esc_html__( 'Designed specifically for websites that need a lightweight, no-bloat inventory solution without slowing down your store. Built for simplicity and speed.', 'nsk-quick-stock-manager' ); ?></p>
                    
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
                    
                    <p style="font-size: 13px; margin-bottom: 5px; color: #333;"><strong><?php echo esc_html__( 'Have Feedback or Suggestions?', 'nsk-quick-stock-manager' ); ?></strong></p>
                    <p style="font-size: 13px; color: #666; line-height: 1.5; margin-bottom: 15px;"><?php echo esc_html__( 'We’d love to hear your thoughts or feature requests to help us improve. Feel free to share your feedback!', 'nsk-quick-stock-manager' ); ?></p>
                    
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
                    
                    <p style="font-size: 13px; margin-bottom: 5px; color: #333;"><strong><?php echo esc_html__( 'Need Custom Development?', 'nsk-quick-stock-manager' ); ?></strong></p>
                    <p style="font-size: 13px; color: #666; line-height: 1.4; margin-bottom: 15px;"><?php echo esc_html__( 'Looking for custom WooCommerce features or specific business automations? Get in touch with us.', 'nsk-quick-stock-manager' ); ?></p>
                    <a href="https://nskdev.com/" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #d35400; border-color: #d35400; width: 100%; text-align: center; box-sizing: border-box; display: block; text-decoration: none; line-height: 28px;"><?php echo esc_html__( 'Contact NSK DEV', 'nsk-quick-stock-manager' ); ?></a>
                </div>
            </div>
        </div>

        <div id="nsk-edit-modal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center;">
            <div style="background: #fff; padding: 30px; border-radius: 8px; width: 400px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">
                <h3 id="nsk-modal-title" style="margin-top: 0; color: #d35400;"><?php echo esc_html__( 'Edit Product', 'nsk-quick-stock-manager' ); ?></h3>
                
                <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="nsk_save_stock">
                    <input type="hidden" id="nsk-product-id" name="product_id">
                    <?php wp_nonce_field( 'nsk_stock_action', 'nsk_stock_nonce' ); ?>

                    <p>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html__( 'SKU', 'nsk-quick-stock-manager' ); ?></label>
                        <input type="text" id="nsk-sku" name="sku" style="width: 100%; padding: 8px;">
                    </p>
                    <p>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html__( 'Regular Price', 'nsk-quick-stock-manager' ); ?></label>
                        <input type="text" id="nsk-regular-price" name="regular_price" style="width: 100%; padding: 8px;">
                    </p>
                    <p>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html__( 'Sale Price', 'nsk-quick-stock-manager' ); ?></label>
                        <input type="text" id="nsk-sale-price" name="sale_price" style="width: 100%; padding: 8px;">
                    </p>
                    
                    <div style="background: #f9f9f9; padding: 12px; border-radius: 6px; border: 1px solid #ddd; margin-bottom: 15px;">
                        <p style="margin-top: 0; margin-bottom: 8px;">
                            <label style="font-weight: bold; font-size: 12px; color: #555;"><?php echo esc_html__( 'Current Stock:', 'nsk-quick-stock-manager' ); ?> <span id="nsk-current-stock-text" style="color: #d35400; font-size: 14px;">0</span></label>
                            <input type="hidden" id="nsk-current-stock" name="current_stock">
                        </p>
                        <p style="margin-bottom: 0;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px;"><?php echo esc_html__( 'Stock Adjustment (+ / -)', 'nsk-quick-stock-manager' ); ?></label>
                            <input type="number" id="nsk-adjust-qty" name="adjust_qty" value="0" style="width: 100%; padding: 6px;" placeholder="<?php echo esc_attr__( 'e.g. +10 or -5', 'nsk-quick-stock-manager' ); ?>">
                            <span style="font-size: 11px; color: #666; display: block; margin-top: 3px;"><?php echo esc_html__( 'Enter positive to add, negative to reduce stock.', 'nsk-quick-stock-manager' ); ?></span>
                        </p>
                    </div>

                    <p>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html__( 'Reason / Note (Required)', 'nsk-quick-stock-manager' ); ?></label>
                        <textarea id="nsk-note" name="note" rows="3" placeholder="<?php echo esc_attr__( 'Enter reason for updating inventory...', 'nsk-quick-stock-manager' ); ?>" style="width: 100%; padding: 8px;" required></textarea>
                    </p>

                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" id="nsk-modal-close" class="button" style="margin-right: 10px;"><?php echo esc_html__( 'Cancel', 'nsk-quick-stock-manager' ); ?></button>
                        <button type="submit" class="button button-primary" style="background: #d35400; border-color: #d35400;"><?php echo esc_html__( 'Save Changes', 'nsk-quick-stock-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_save_stock() {
        if ( ! isset( $_POST['nsk_stock_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsk_stock_nonce'] ) ), 'nsk_stock_action' ) ) {
            wp_die( esc_html__( 'Security check failed', 'nsk-quick-stock-manager' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Permission denied', 'nsk-quick-stock-manager' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'nsk_stock_logs';
        $current_user = wp_get_current_user();
        $user_name = $current_user->user_login;

        $product_id    = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $product       = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_die( esc_html__( 'Product not found', 'nsk-quick-stock-manager' ) );
        }

        $product_name  = $product->get_name();
        $current_sku   = $product->get_sku(); 
        
        $old_sku       = $current_sku;
        $new_sku       = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
        if ( $old_sku !== $new_sku ) {
            $product->set_sku( $new_sku );
            $wpdb->insert( $table_name, array(
                'product_id'   => $product_id,
                'product_name' => $product_name,
                'sku'          => $new_sku ? $new_sku : $old_sku,
                'action_type'  => 'Update SKU',
                'old_value'    => $old_sku ? $old_sku : '-',
                'new_value'    => $new_sku ? $new_sku : '-',
                'user_name'    => $user_name,
                'note'         => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
            ));
            $current_sku = $new_sku;
        }

        $old_reg       = $product->get_regular_price();
        $new_reg       = isset( $_POST['regular_price'] ) ? sanitize_text_field( wp_unslash( $_POST['regular_price'] ) ) : '';
        if ( $old_reg !== $new_reg ) {
            $product->set_regular_price( $new_reg );
            $wpdb->insert( $table_name, array(
                'product_id'   => $product_id,
                'product_name' => $product_name,
                'sku'          => $current_sku,
                'action_type'  => 'Update Regular Price',
                'old_value'    => $old_reg,
                'new_value'    => $new_reg,
                'user_name'    => $user_name,
                'note'         => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
            ));
        }

        $old_sale      = $product->get_sale_price();
        $new_sale      = isset( $_POST['sale_price'] ) ? sanitize_text_field( wp_unslash( $_POST['sale_price'] ) ) : '';
        if ( $old_sale !== $new_sale ) {
            $product->set_sale_price( $new_sale );
            $wpdb->insert( $table_name, array(
                'product_id'   => $product_id,
                'product_name' => $product_name,
                'sku'          => $current_sku,
                'action_type'  => 'Update Sale Price',
                'old_value'    => $old_sale,
                'new_value'    => $new_sale,
                'user_name'    => $user_name,
                'note'         => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
            ));
        }

        $current_stock = isset( $_POST['current_stock'] ) ? intval( $_POST['current_stock'] ) : 0;
        $adjust_qty    = isset( $_POST['adjust_qty'] ) ? intval( $_POST['adjust_qty'] ) : 0;
        if ( 0 !== $adjust_qty ) {
            $new_stock = $current_stock + $adjust_qty;
            if ( $new_stock < 0 ) { $new_stock = 0; }
            
            $product->set_manage_stock( true );
            $product->set_stock_quantity( $new_stock );

            $wpdb->insert( $table_name, array(
                'product_id'   => $product_id,
                'product_name' => $product_name,
                'sku'          => $current_sku,
                'action_type'  => $adjust_qty > 0 ? 'Stock In (+)' : 'Stock Out (-)',
                'old_value'    => $current_stock,
                'new_value'    => $new_stock,
                'user_name'    => $user_name,
                'note'         => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
            ));
        }

        $product->save();

        wp_safe_redirect( admin_url( 'admin.php?page=nsk-quick-stock&updated=true' ) );
        exit;
    }
}