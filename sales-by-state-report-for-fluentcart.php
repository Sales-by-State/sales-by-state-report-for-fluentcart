<?php
/**
 * Plugin Name:          Sales by State Report for FluentCart
 * Plugin URI:           https://salesbystate.com/
 * Description:          See a yearly breakdown of FluentCart sales by state / county / province for a given country, filterable by order status.
 * Version:              1.0.0
 * Author:               Rodolfo Melogli
 * Author URI:           https://businessbloomer.com/
 * Developer:            Rodolfo Melogli
 * Developer URI:        https://businessbloomer.com/
 * Text Domain:          sales-by-state-report-for-fluentcart
 * Domain Path:          /languages
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     fluent-cart
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SalesByStateReportForFluentCart
 * @copyright 2026 Rodolfo Melogli
 */

defined( 'ABSPATH' ) || exit;

define( 'SBSFC_VERSION', '1.0.0' );
define( 'SBSFC_FILE', __FILE__ );
define( 'SBSFC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBSFC_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'SBSFC\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SBSFC_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'FLUENTCART_PLUGIN_PATH' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Sales by State Report for FluentCart requires FluentCart to be installed and active.', 'sales-by-state-report-for-fluentcart' )
					);
				}
			);

			return;
		}

		SBSFC\Plugin::instance()->init();
	},
	20
);

register_activation_hook(
	SBSFC_FILE,
	function () {
		require_once SBSFC_DIR . 'src/Install/Schema.php';
		SBSFC\Install\Schema::install();
	}
);

register_deactivation_hook(
	SBSFC_FILE,
	function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sbsfc_backfill_batch', array(), 'sales-by-state-report-for-fluentcart' );
		}
	}
);
