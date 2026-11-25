<?php

declare(strict_types=1);

namespace NorthernExposure\OrderFollowUpManager;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Prepares Orders List integrations for the active order storage mode.
 */
final class OrdersListIntegration
{
	private const string COLUMN_KEY = 'nem_follow_up_required';

	public function __construct(private OrderFollowUpMetadata $metadata)
	{
	}

	public function initialize(): void
	{
		$is_hpos = OrderUtil::custom_orders_table_usage_is_enabled();

		if ($is_hpos) {
			$this->initializeHpos();
			return;
		}

		$this->initializeLegacy();
	}

	private function initializeHpos(): void
	{
		add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'addFollowUpColumn']);
		add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'renderHposColumn'], 10, 2);
	}

	private function initializeLegacy(): void
	{
		add_filter('manage_edit-shop_order_columns', [$this, 'addFollowUpColumn']);
		add_action('manage_shop_order_posts_custom_column', [$this, 'renderLegacyColumn'], 10, 2);
	}

	/**
	 * @param array<string, mixed> $columns
	 * @return array<string, mixed>
	 */
	public function addFollowUpColumn(array $columns): array
	{
		if (array_key_exists(self::COLUMN_KEY, $columns)) {
			return $columns;
		}

		$insert_after = array_search('order_status', array_keys($columns), true);
		if (false === $insert_after) {
			$columns[self::COLUMN_KEY] = __('Follow-up', 'order-follow-up-manager');
			return $columns;
		}

		return array_slice($columns, 0, $insert_after + 1, true)
			+ [self::COLUMN_KEY => __('Follow-up', 'order-follow-up-manager')]
			+ array_slice($columns, $insert_after + 1, null, true);
	}

	public function renderHposColumn(string $column, mixed $order): void
	{
		if (self::COLUMN_KEY !== $column || !$order instanceof \WC_Order || 'shop_order' !== $order->get_type()) {
			return;
		}

		$this->renderRequiredValue($order);
	}

	public function renderLegacyColumn(string $column, int $post_id): void
	{
		if (self::COLUMN_KEY !== $column) {
			return;
		}

		$order = wc_get_order($post_id);
		if (!$order instanceof \WC_Order || 'shop_order' !== $order->get_type()) {
			return;
		}

		$this->renderRequiredValue($order);
	}

	private function renderRequiredValue(\WC_Order $order): void
	{
		if ($this->metadata->isRequired($order)) {
			echo esc_html__('Required', 'order-follow-up-manager');
		}
	}
}
