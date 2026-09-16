<?php
/**
 * Plugin Name: NSK Quick Stock Manager
 * Plugin URI: https://nskdev.com/nsk-quick-stock-manager/
 * Description: Fast WooCommerce stock and price manager with built-in audit log functionality. Developed by NSK DEV.
 * Version: 1.0.0
 * Author: NSK DEV
 * Author URI: https://nskdev.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nsk-quick-stock-manager
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// กำหนดค่าคงที่สำหรับเส้นทางปลั๊กอิน
define( 'NSK_QSM_PATH', plugin_dir_path( __FILE__ ) );
define( 'NSK_QSM_URL', plugin_dir_url( __FILE__ ) );

// ประกาศรองรับ WooCommerce HPOS (High-Performance Order Storage) มาตรฐานสูงสุด
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// โหลดระบบฐานข้อมูล
require_once NSK_QSM_PATH . 'includes/class-qsm-db.php';
register_activation_hook( __FILE__, array( 'NSK_QSM_DB', 'create_table' ) );

// โหลดระบบหลังบ้าน
if ( is_admin() ) {
    require_once NSK_QSM_PATH . 'admin/class-qsm-admin.php';
    require_once NSK_QSM_PATH . 'admin/class-qsm-logger.php';
    
    new NSK_QSM_Admin();
    new NSK_QSM_Logger();
}