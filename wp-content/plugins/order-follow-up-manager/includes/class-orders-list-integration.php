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
	private const string FILTER_KEY = 'nem_follow_up_required';
	private const string REQUIRED_META_KEY = '_nem_follow_up_required';

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
		add_filter('woocommerce_order_list_table_prepare_items_query_args', [$this, 'filterHposQueryArgs']);
		add_action('woocommerce_order_list_table_restrict_manage_orders', [$this, 'renderHposFilter'], 10, 2);
		add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'addFollowUpColumn']);
		add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'renderHposColumn'], 10, 2);
	}

	private function initializeLegacy(): void
	{
		add_action('pre_get_posts', [$this, 'filterLegacyQuery']);
		add_action('restrict_manage_posts', [$this, 'renderLegacyFilter'], 10, 2);
		add_filter('manage_edit-shop_order_columns', [$this, 'addFollowUpColumn']);
		add_action('manage_shop_order_posts_custom_column', [$this, 'renderLegacyColumn'], 10, 2);
	}

	public function renderHposFilter(string $order_type, string $which): void
	{
		if ('shop_order' !== $order_type || 'top' !== $which) {
			return;
		}

		$this->renderFollowUpFilter();
	}

	public function renderLegacyFilter(string $post_type, string $which): void
	{
		$screen = get_current_screen();
		if ('shop_order' !== $post_type || 'top' !== $which || null === $screen || 'edit-shop_order' !== $screen->id) {
			return;
		}

		$this->renderFollowUpFilter();
	}

	private function renderFollowUpFilter(): void
	{
		$selected_value = isset($_GET[self::FILTER_KEY]) && is_string($_GET[self::FILTER_KEY]) && 'yes' === $_GET[self::FILTER_KEY]
			? 'yes'
			: '';

		?>
		<select name="<?php echo esc_attr(self::FILTER_KEY); ?>">
			<option value="" <?php selected($selected_value, ''); ?>><?php echo esc_html__('All orders', 'order-follow-up-manager'); ?></option>
			<option value="yes" <?php selected($selected_value, 'yes'); ?>><?php echo esc_html__('Follow-up required', 'order-follow-up-manager'); ?></option>
		</select>
		<?php
	}

	/**
	 * @param array<string, mixed> $query_args
	 * @return array<string, mixed>
	 */
	public function filterHposQueryArgs(array $query_args): array
	{
		if (!$this->isFollowUpFilterActive()) {
			return $query_args;
		}

		$query_args['meta_query'] = $this->addFollowUpMetaCondition($query_args['meta_query'] ?? null);

		return $query_args;
	}

	public function filterLegacyQuery(\WP_Query $query): void
	{
		if (!is_admin() || !$query->is_main_query() || 'shop_order' !== $query->get('post_type') || !$this->isFollowUpFilterActive()) {
			return;
		}

		$query->set('meta_query', $this->addFollowUpMetaCondition($query->get('meta_query')));
	}

	private function isFollowUpFilterActive(): bool
	{
		return isset($_GET[self::FILTER_KEY]) && is_string($_GET[self::FILTER_KEY]) && 'yes' === $_GET[self::FILTER_KEY];
	}

	/**
	 * @param mixed $existing_meta_query
	 * @return array<string|int, mixed>
	 */
	private function addFollowUpMetaCondition(mixed $existing_meta_query): array
	{
		$follow_up_clause = [
			'key' => self::REQUIRED_META_KEY,
			'value' => 'yes',
			'compare' => '=',
		];

		if (!is_array($existing_meta_query) || [] === $existing_meta_query) {
			return [$follow_up_clause];
		}

		return [
			'relation' => 'AND',
			$existing_meta_query,
			$follow_up_clause,
		];
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
