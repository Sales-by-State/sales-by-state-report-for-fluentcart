<?php
/**
 * The report page and its assets.
 *
 * @package SalesByStateReportForFluentCart
 */

namespace SBSFC\Admin;

use SBSFC\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the report under FluentCart.
 */
class Page {

	/**
	 * Menu slug.
	 */
	const SLUG = 'sbsfc-sales-by-state';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_head', array( $this, 'print_css' ) );
	}

	/**
	 * Whether this screen is showing.
	 *
	 * @param string $hook Optional enqueue hook.
	 * @return bool
	 */
	private function is_screen( $hook = '' ) {
		if ( isset( $_GET['page'] ) && self::SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return is_string( $hook ) && false !== strpos( $hook, self::SLUG );
	}

	/**
	 * Add the report under FluentCart.
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			'fluent-cart',
			__( 'Sales by State', 'sales-by-state-report-for-fluentcart' ),
			__( 'Sales by State', 'sales-by-state-report-for-fluentcart' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register script and style handles.
	 *
	 * @return void
	 */
	private function register_assets() {
		wp_register_style(
			'sbsfc-report',
			SBSFC_URL . 'assets/css/report.css',
			array( 'wp-components' ),
			SBSFC_VERSION
		);

		wp_register_script(
			'sbsfc-report',
			SBSFC_URL . 'assets/js/report.js',
			array(
				'wp-hooks',
				'wp-element',
				'wp-i18n',
				'wp-api-fetch',
				'wp-url',
				'wp-components',
			),
			SBSFC_VERSION,
			true
		);

		wp_set_script_translations( 'sbsfc-report', 'sales-by-state-report-for-fluentcart', SBSFC_DIR . 'languages' );
		wp_localize_script( 'sbsfc-report', 'sbsfcConfig', $this->config() );
	}

	/**
	 * Enqueue the report bundle.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ! $this->is_screen( $hook ) ) {
			return;
		}

		$this->register_assets();
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'sbsfc-report' );
		wp_enqueue_script( 'sbsfc-report' );
	}

	/**
	 * Print styles in the head if the enqueue hook did not run.
	 *
	 * @return void
	 */
	public function print_css() {
		if ( ! $this->is_screen() ) {
			return;
		}

		$this->register_assets();
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'sbsfc-report' );
		wp_print_styles( array( 'wp-components', 'sbsfc-report' ) );
	}

	/**
	 * Render the root element for the standalone page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->register_assets();
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'sbsfc-report' );
		wp_enqueue_script( 'sbsfc-report' );

		printf(
			'<div class="wrap sbsfc-wrap">
				<div class="sbsfc-page-header"><h1 class="sbsfc-page-header__title">%s</h1></div>
				<div id="sbsfc-root"></div>
			</div>',
			esc_html__( 'Sales by State', 'sales-by-state-report-for-fluentcart' )
		);

		wp_print_scripts( array( 'sbsfc-report' ) );
	}

	/**
	 * Data the bundle needs to draw its controls.
	 *
	 * @return array
	 */
	private function config() {
		$measures = array();

		foreach ( Filters::measures() as $key => $measure ) {
			$measures[] = array(
				'key'   => $key,
				'label' => $measure['label'],
				'type'  => $measure['type'],
			);
		}

		$statuses = array();

		foreach ( Filters::order_statuses() as $key => $label ) {
			$statuses[] = array(
				'value' => $key,
				'label' => $label,
			);
		}

		$years = array();

		foreach ( Filters::years() as $year ) {
			$years[] = array(
				'value' => (string) $year,
				'label' => (string) $year,
			);
		}

		$countries = array();

		foreach ( Filters::countries_with_states() as $code => $label ) {
			$countries[] = array(
				'value' => $code,
				'label' => $label,
			);
		}

		return array(
			'measures'        => $measures,
			'statuses'        => $statuses,
			'years'           => $years,
			'countries'       => $countries,
			'defaultCountry'  => Filters::default_country(),
			'defaultYear'     => (string) Filters::default_year(),
			'defaultStatuses' => Filters::default_statuses(),
			'perPageOptions'  => array( 10, 25, 50, 100 ),
			'title'           => __( 'Sales by State', 'sales-by-state-report-for-fluentcart' ),
			'canBuild'        => current_user_can( 'manage_options' ),
			'mode'            => 'standalone',
		);
	}
}
