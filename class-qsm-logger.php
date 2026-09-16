<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NSK_QSM_Logger {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ) );
        add_action( 'admin_init', array( $this, 'check_and_update_table' ) );
    }

    public function check_and_update_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nsk_stock_logs';
        $row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'sku' ) );
        if ( empty( $row ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN sku VARCHAR(100) DEFAULT '' AFTER product_name" );
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'nsk-quick-stock',
            __( 'Audit Logs', 'nsk-quick-stock-manager' ),
            __( 'Audit Logs', 'nsk-quick-stock-manager' ),
            'manage_woocommerce',
            'nsk-quick-stock-logs',
            array( $this, 'render_logs_page' )
        );
    }

    public function render_logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nsk_stock_logs';

        $filter_product = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
        $filter_start   = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
        $filter_end     = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
        $search_keyword = isset( $_GET['s_keyword'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s_keyword'] ) ) ) : '';

        if ( $filter_product > 0 && ! empty( $search_keyword ) ) {
            $like = '%' . $wpdb->esc_like( $search_keyword ) . '%';
            $logs = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table_name}` WHERE product_id = %d AND (sku LIKE %s OR product_name LIKE %s OR note LIKE %s OR action_type LIKE %s OR user_name LIKE %s) ORDER BY time DESC LIMIT 50",
                $filter_product,
                $like,
                $like,
                $like,
                $like,
                $like
            ) );
        } elseif ( $filter_product > 0 ) {
            $logs = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table_name}` WHERE product_id = %d ORDER BY time DESC LIMIT 50",
                $filter_product
            ) );
        } elseif ( ! empty( $search_keyword ) ) {
            $like = '%' . $wpdb->esc_like( $search_keyword ) . '%';
            $logs = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table_name}` WHERE (sku LIKE %s OR product_name LIKE %s OR note LIKE %s OR action_type LIKE %s OR user_name LIKE %s) ORDER BY time DESC LIMIT 50",
                $like,
                $like,
                $like,
                $like,
                $like
            ) );
        } elseif ( ! empty( $filter_start ) && ! empty( $filter_end ) ) {
            $logs = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table_name}` WHERE time >= %s AND time <= %s ORDER BY time DESC LIMIT 50",
                $filter_start . ' 00:00:00',
                $filter_end . ' 23:59:59'
            ) );
        } else {
            $logs = $wpdb->get_results( "SELECT * FROM `{$table_name}` ORDER BY time DESC LIMIT 50" );
        }

        $all_products = wc_get_products( array( 'post_type' => 'product', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
        ?>
        <div class="wrap" style="font-family: sans-serif;">
            <h1 style="color: #d35400; font-weight: 700;"><?php echo esc_html__( '📋 NSK Stock Audit Logs', 'nsk-quick-stock-manager' ); ?></h1>
            <p style="color: #555;"><?php echo esc_html__( 'History of all inventory and price modifications.', 'nsk-quick-stock-manager' ); ?></p>

            <form method="GET" action="" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 15px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="nsk-quick-stock-logs">
                
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html__( 'Filter by Product:', 'nsk-quick-stock-manager' ); ?></label>
                    <select name="product_id" style="min-width: 280px; padding: 5px;">
                        <option value="0"><?php echo esc_html__( '-- All Products --', 'nsk-quick-stock-manager' ); ?></option>
                        <?php 
                        foreach ( $all_products as $p ) {
                            if ( $p->is_type( 'variable' ) ) {
                                echo '<optgroup label="' . esc_attr( $p->get_name() ) . '">';
                                $variations = $p->get_children();
                                foreach ( $variations as $var_id ) {
                                    $var_prod = wc_get_product( $var_id );
                                    if ( $var_prod ) {
                                        $var_sku = $var_prod->get_sku();
                                        $sku_text = $var_sku ? ' (SKU: ' . $var_sku . ')' : ' (SKU: -)';
                                        printf(
                                            '<option value="%d" %s>%s</option>',
                                            intval( $var_id ),
                                            selected( $filter_product, $var_id, false ),
                                            esc_html( '↳ ' . $var_prod->get_name() . $sku_text )
                                        );
                                    }
                                }
                                echo '</optgroup>';
                            } else {
                                $p_sku = $p->get_sku();
                                $sku_text = $p_sku ? ' (SKU: ' . $p_sku . ')' : ' (SKU: -)';
                                printf(
                                    '<option value="%d" %s>%s</option>',
                                    intval( $p->get_id() ),
                                    selected( $filter_product, $p->get_id(), false ),
                                    esc_html( $p->get_name() . $sku_text )
                                );
                            }
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html__( 'Search Keyword:', 'nsk-quick-stock-manager' ); ?></label>
                    <input type="text" name="s_keyword" value="<?php echo esc_attr( $search_keyword ); ?>" placeholder="<?php echo esc_attr__( 'Search SKU, note, action...', 'nsk-quick-stock-manager' ); ?>" style="padding: 4px 8px; width: 180px;">
                </div>

                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html__( 'Start Date:', 'nsk-quick-stock-manager' ); ?></label>
                    <input type="date" name="start_date" value="<?php echo esc_attr( $filter_start ); ?>" style="padding: 4px;">
                </div>

                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html__( 'End Date:', 'nsk-quick-stock-manager' ); ?></label>
                    <input type="date" name="end_date" value="<?php echo esc_attr( $filter_end ); ?>" style="padding: 4px;">
                </div>

                <div>
                    <button type="submit" class="button button-primary" style="background: #d35400; border-color: #d35400;"><?php echo esc_html__( 'Filter Logs', 'nsk-quick-stock-manager' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=nsk-quick-stock-logs' ) ); ?>" class="button" style="margin-left: 5px;"><?php echo esc_html__( 'Reset', 'nsk-quick-stock-manager' ); ?></a>
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 140px;"><?php echo esc_html__( 'Date / Time', 'nsk-quick-stock-manager' ); ?></th>
                        <th><?php echo esc_html__( 'Product Name', 'nsk-quick-stock-manager' ); ?></th>
                        <th style="width: 110px;"><?php echo esc_html__( 'SKU', 'nsk-quick-stock-manager' ); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__( 'Action', 'nsk-quick-stock-manager' ); ?></th>
                        <th style="width: 90px;"><?php echo esc_html__( 'Old Value', 'nsk-quick-stock-manager' ); ?></th>
                        <th style="width: 90px;"><?php echo esc_html__( 'New Value', 'nsk-quick-stock-manager' ); ?></th>
                        <th style="width: 90px;"><?php echo esc_html__( 'User', 'nsk-quick-stock-manager' ); ?></th>
                        <th><?php echo esc_html__( 'Reason / Note', 'nsk-quick-stock-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( ! empty( $logs ) ) {
                        foreach ( $logs as $log ) {
                            $action_display = $log->action_type;
                            if ( strpos( $log->action_type, 'Stock In' ) !== false || strpos( $log->action_type, 'Stock Out' ) !== false ) {
                                $diff = intval( $log->new_value ) - intval( $log->old_value );
                                if ( $diff > 0 ) {
                                    $action_display = 'Stock In (+' . $diff . ')';
                                    $badge_color = '#27ae60';
                                    $bg_color = '#e8f8f5';
                                } else {
                                    $action_display = 'Stock Out (' . $diff . ')';
                                    $badge_color = '#c0392b';
                                    $bg_color = '#fdedec';
                                }
                            } else {
                                $badge_color = '#555';
                                $bg_color = '#eee';
                            }

                            $log_sku = ! empty( $log->sku ) ? $log->sku : '';
                            if ( empty( $log_sku ) ) {
                                $log_product = wc_get_product( $log->product_id );
                                $log_sku = $log_product ? $log_product->get_sku() : '-';
                            }

                            $filter_product_url = admin_url( 'admin.php?page=nsk-quick-stock-logs&product_id=' . intval( $log->product_id ) );
                            ?>
                            <tr>
                                <td><?php echo esc_html( $log->time ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( $filter_product_url ); ?>" style="color: #d35400; text-decoration: none; font-weight: bold;" title="<?php echo esc_attr__( 'Filter logs by this product', 'nsk-quick-stock-manager' ); ?>">
                                        <?php echo esc_html( $log->product_name ); ?> 🔍
                                    </a>
                                </td>
                                <td><code style="background: #edf2f7; padding: 2px 6px; border-radius: 3px; font-size: 12px;"><?php echo esc_html( $log_sku ? $log_sku : '-' ); ?></code></td>
                                <td>
                                    <span style="background: <?php echo esc_attr( $bg_color ); ?>; color: <?php echo esc_attr( $badge_color ); ?>; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block;">
                                        <?php echo esc_html( $action_display ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $log->old_value ); ?></td>
                                <td><?php echo esc_html( $log->new_value ); ?></td>
                                <td><?php echo esc_html( $log->user_name ); ?></td>
                                <td><?php echo esc_html( $log->note ); ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="8" style="text-align: center; padding: 20px;">' . esc_html__( 'No audit logs found for the selected criteria.', 'nsk-quick-stock-manager' ) . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}