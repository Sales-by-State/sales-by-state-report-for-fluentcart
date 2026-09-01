<?php
/**
 * Keeps the report table in step with FluentCart orders.
 *
 * @package SalesByStateReportForFluentCart
 */

namespace SBSFC\Data;

use FluentCart\App\Helpers\Helper;
use SBSFC\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one row per order.
 */
class Sync {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'fluent_cart/order_created', array( $this, 'on_event' ), 20, 1 );
		add_action( 'fluent_cart/order_updated', array( $this, 'on_event' ), 20, 1 );
		add_action( 'fluent_cart/order_status_updated', array( $this, 'on_event' ), 20, 1 );
		add_action( 'fluent_cart/order_status_changed', array( $this, 'on_event' ), 20, 1 );
		add_action( 'fluent_cart/order_paid', array( $this, 'on_event' ), 20, 1 );
		add_action( 'fluent_cart/order_refunded', array( $this, 'on_event' ), 20, 1 );
		add_action( 'fluent_cart/order_canceled', array( $this, 'on_event' ), 20, 1 );
		add_action( 'fluent_cart/order_deleted', array( $this, 'on_delete' ), 20, 1 );
		add_action( 'fluent_cart/order_before_delete', array( $this, 'on_delete' ), 20, 1 );
	}

	/**
	 * Handle a FluentCart order event payload.
	 *
	 * @param mixed $payload Event array or object.
	 * @return void
	 */
	public function on_event( $payload ) {
		$order_id = $this->order_id_from( $payload );

		if ( $order_id ) {
			$this->upsert( $order_id );
		}
	}

	/**
	 * Remove the row when an order is deleted.
	 *
	 * @param mixed $payload Event array or object.
	 * @return void
	 */
	public function on_delete( $payload ) {
		$order_id = $this->order_id_from( $payload );

		if ( $order_id ) {
			$this->delete( $order_id );
		}
	}

	/**
	 * Insert or update the row for one order.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function upsert( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return false;
		}

		$row = self::build_row( $order_id );

		if ( ! $row ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->replace(
			Schema::table(),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f' )
		);
	}

	/**
	 * Remove the row for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function delete( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Schema::table(), array( 'order_id' => (int) $order_id ), array( '%d' ) );
	}

	/**
	 * Build the row for an order from FluentCart tables.
	 *
	 * FluentCart stores money in cents. The report table stores decimals.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string,mixed>|false
	 */
	public static function build_row( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.id, o.status, o.currency, o.total_amount, o.tax_total, o.shipping_total,
				        o.created_at, o.completed_at,
				        billing.country AS billing_country,
				        billing.state AS billing_state,
				        shipping.country AS shipping_country,
				        shipping.state AS shipping_state
				 FROM {$wpdb->prefix}fct_orders o
				 LEFT JOIN {$wpdb->prefix}fct_order_addresses billing
				        ON billing.order_id = o.id AND billing.type = %s
				 LEFT JOIN {$wpdb->prefix}fct_order_addresses shipping
				        ON shipping.order_id = o.id AND shipping.type = %s
				 WHERE o.id = %d",
				'billing',
				'shipping',
				$order_id
			)
		);

		if ( ! $order ) {
			return false;
		}

		$billing_country  = strtoupper( substr( (string) $order->billing_country, 0, 2 ) );
		$billing_state    = (string) $order->billing_state;
		$shipping_country = strtoupper( substr( (string) $order->shipping_country, 0, 2 ) );
		$shipping_state   = (string) $order->shipping_state;

		if ( '' === $shipping_country ) {
			$shipping_country = $billing_country;
			$shipping_state   = $billing_state;
		}

		$total    = self::from_cents( $order->total_amount );
		$tax      = self::from_cents( $order->tax_total );
		$shipping = self::from_cents( $order->shipping_total );

		$created = self::normalize_datetime( $order->created_at );
		$paid    = self::normalize_datetime( $order->completed_at );

		return array(
			'order_id'         => (int) $order->id,
			'status'           => substr( sanitize_key( (string) $order->status ), 0, 20 ),
			'date_created'     => $created ? $created : '0000-00-00 00:00:00',
			'date_paid'        => $paid ? $paid : null,
			'billing_country'  => $billing_country,
			'billing_state'    => substr( $billing_state, 0, 50 ),
			'shipping_country' => $shipping_country,
			'shipping_state'   => substr( $shipping_state, 0, 50 ),
			'currency'         => strtoupper( substr( (string) $order->currency, 0, 3 ) ),
			'total_sales'      => $total,
			'tax_total'        => $tax,
			'shipping_total'   => $shipping,
			'net_total'        => $total - $tax - $shipping,
		);
	}

	/**
	 * Convert FluentCart cents to a decimal amount.
	 *
	 * @param mixed $cents Amount in cents.
	 * @return float
	 */
	private static function from_cents( $cents ) {
		if ( class_exists( Helper::class ) && method_exists( Helper::class, 'toDecimalWithoutComma' ) ) {
			return (float) Helper::toDecimalWithoutComma( $cents );
		}

		return round( (float) $cents / 100, 2 );
	}

	/**
	 * Normalise a datetime string.
	 *
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	private static function normalize_datetime( $value ) {
		$value = (string) $value;

		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		$ts = strtotime( $value );

		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}

	/**
	 * Pull an order ID out of a FluentCart event payload.
	 *
	 * @param mixed $payload Event payload.
	 * @return int
	 */
	private function order_id_from( $payload ) {
		if ( is_object( $payload ) && isset( $payload->order ) ) {
			$payload = $payload->order;
		}

		if ( is_array( $payload ) && isset( $payload['order'] ) ) {
			$payload = $payload['order'];
		}

		if ( is_object( $payload ) && isset( $payload->id ) ) {
			return (int) $payload->id;
		}

		if ( is_array( $payload ) && isset( $payload['id'] ) ) {
			return (int) $payload['id'];
		}

		if ( is_numeric( $payload ) ) {
			return (int) $payload;
		}

		return 0;
	}
}
