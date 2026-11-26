<?php

declare(strict_types=1);

use NorthernExposure\OrderFollowUpManager\OrderFollowUpMetadata;
use NorthernExposure\OrderFollowUpManager\OrdersListIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/wp-content/plugins/order-follow-up-manager/includes/class-order-follow-up-metadata.php';
require_once dirname(__DIR__, 2) . '/wp-content/plugins/order-follow-up-manager/includes/class-orders-list-integration.php';

final class OrdersListIntegrationTest extends TestCase
{
	private mixed $original_follow_up_filter;
	private bool $had_follow_up_filter;

	protected function setUp(): void
	{
		$this->had_follow_up_filter = array_key_exists('nem_follow_up_required', $_GET);
		$this->original_follow_up_filter = $_GET['nem_follow_up_required'] ?? null;
	}

	protected function tearDown(): void
	{
		if ($this->had_follow_up_filter) {
			$_GET['nem_follow_up_required'] = $this->original_follow_up_filter;
		} else {
			unset($_GET['nem_follow_up_required']);
		}
	}

	public function test_adds_follow_up_column_after_order_status_and_preserves_other_columns(): void
	{
		$columns = [
			'cb' => '<input type="checkbox">',
			'order_number' => 'Order',
			'order_status' => 'Status',
			'order_total' => 'Total',
		];

		$result = (new OrdersListIntegration(new OrderFollowUpMetadata()))->addFollowUpColumn($columns);

		self::assertSame(['cb', 'order_number', 'order_status', 'nem_follow_up_required', 'order_total'], array_keys($result));
		self::assertSame('Follow-up', $result['nem_follow_up_required']);
		self::assertSame($columns['cb'], $result['cb']);
		self::assertSame($columns['order_number'], $result['order_number']);
		self::assertSame($columns['order_status'], $result['order_status']);
		self::assertSame($columns['order_total'], $result['order_total']);
	}

	public function test_appends_follow_up_column_when_order_status_is_absent(): void
	{
		$columns = ['order_number' => 'Order', 'order_total' => 'Total'];

		$result = (new OrdersListIntegration(new OrderFollowUpMetadata()))->addFollowUpColumn($columns);

		self::assertSame(['order_number', 'order_total', 'nem_follow_up_required'], array_keys($result));
	}

	public function test_hpos_column_renders_only_for_required_metadata(): void
	{
		$integration = new OrdersListIntegration(new OrderFollowUpMetadata());
		foreach (['yes' => 'Required', 'no' => '', '' => '', 'invalid' => ''] as $value => $expected) {
			$order = $this->createOrder($value);
			ob_start();
			$integration->renderHposColumn('nem_follow_up_required', $order);
			$output = (string) ob_get_clean();
			self::assertSame($expected, $output);
		}
	}

	public function test_legacy_column_safely_ignores_invalid_order_id(): void
	{
		ob_start();
		(new OrdersListIntegration(new OrderFollowUpMetadata()))->renderLegacyColumn('nem_follow_up_required', PHP_INT_MAX);
		$output = (string) ob_get_clean();

		self::assertSame('', $output);
	}

	public function test_hpos_query_args_are_unchanged_when_filter_is_inactive(): void
	{
		$args = ['status' => ['wc-processing'], 'meta_query' => [['key' => 'existing', 'value' => 'value']]];

		self::assertSame($args, (new OrdersListIntegration(new OrderFollowUpMetadata()))->filterHposQueryArgs($args));
	}

	public function test_hpos_query_args_add_follow_up_clause_with_no_existing_meta_query(): void
	{
		$_GET['nem_follow_up_required'] = 'yes';
		$result = (new OrdersListIntegration(new OrderFollowUpMetadata()))->filterHposQueryArgs(['status' => ['wc-processing']]);

		self::assertSame([['key' => '_nem_follow_up_required', 'value' => 'yes', 'compare' => '=']], $result['meta_query']);
	}

	#[DataProvider('inactiveFilterValues')]
	public function test_hpos_filter_requires_exact_scalar_yes(mixed $value, bool $parameter_exists): void
	{
		if ($parameter_exists) {
			$_GET['nem_follow_up_required'] = $value;
		} else {
			unset($_GET['nem_follow_up_required']);
		}

		$args = ['meta_query' => [['key' => 'existing', 'value' => 'value']]];
		self::assertSame($args, (new OrdersListIntegration(new OrderFollowUpMetadata()))->filterHposQueryArgs($args));
	}

	public static function inactiveFilterValues(): array
	{
		return [
			'missing' => [null, false],
			'empty string' => ['', true],
			'no' => ['no', true],
			'uppercase' => ['YES', true],
			'boolean' => [true, true],
			'integer' => [1, true],
			'array' => [['yes'], true],
		];
	}

	#[DataProvider('existingMetaQueries')]
	public function test_hpos_query_preserves_existing_meta_query_as_nested_and_group(array $existing): void
	{
		$_GET['nem_follow_up_required'] = 'yes';
		$result = (new OrdersListIntegration(new OrderFollowUpMetadata()))->filterHposQueryArgs(['meta_query' => $existing]);

		self::assertSame('AND', $result['meta_query']['relation']);
		self::assertSame($existing, $result['meta_query'][0]);
		self::assertSame(['key' => '_nem_follow_up_required', 'value' => 'yes', 'compare' => '='], $result['meta_query'][1]);
	}

	public static function existingMetaQueries(): array
	{
		$one = ['key' => 'first', 'value' => 'one'];
		$two = ['key' => 'second', 'value' => 'two'];
		return [
			'one condition' => [[$one]],
			'multiple AND conditions' => [['relation' => 'AND', $one, $two]],
			'OR group' => [['relation' => 'OR', $one, $two]],
			'nested group' => [['relation' => 'AND', $one, ['relation' => 'OR', $two]]],
		];
	}

	public function test_empty_or_malformed_meta_query_wraps_follow_up_clause_in_query_group(): void
	{
		$_GET['nem_follow_up_required'] = 'yes';
		$integration = new OrdersListIntegration(new OrderFollowUpMetadata());
		$expected = [['key' => '_nem_follow_up_required', 'value' => 'yes', 'compare' => '=']];

		foreach ([null, [], 'malformed'] as $existing) {
			self::assertSame($expected, $integration->filterHposQueryArgs(['meta_query' => $existing])['meta_query']);
		}
	}

	public function test_legacy_query_modifies_only_main_admin_orders_query(): void
	{
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		$_GET['nem_follow_up_required'] = 'yes';
		$integration = new OrdersListIntegration(new OrderFollowUpMetadata());
		set_current_screen('edit-shop_order');
		$main_query = new \WP_Query();
		$main_query->query_vars['post_type'] = 'shop_order';
		$main_query->query_vars['meta_query'] = [['key' => 'existing', 'value' => 'value']];
		$previous_query = $GLOBALS['wp_query'] ?? null;
		$previous_the_query = $GLOBALS['wp_the_query'] ?? null;
		$GLOBALS['wp_query'] = $main_query;
		$GLOBALS['wp_the_query'] = $main_query;

		try {
			$integration->filterLegacyQuery($main_query);
			self::assertSame('AND', $main_query->get('meta_query')['relation']);
			self::assertSame([['key' => 'existing', 'value' => 'value']], $main_query->get('meta_query')[0]);
			self::assertSame(['key' => '_nem_follow_up_required', 'value' => 'yes', 'compare' => '='], $main_query->get('meta_query')[1]);

			$secondary = new \WP_Query();
			$secondary->query_vars['post_type'] = 'shop_order';
			$before = $secondary->query_vars;
			$integration->filterLegacyQuery($secondary);
			self::assertSame($before, $secondary->query_vars);

			$main_query->query_vars['post_type'] = 'product';
			$before = $main_query->query_vars;
			$integration->filterLegacyQuery($main_query);
			self::assertSame($before, $main_query->query_vars);
		} finally {
			if (null === $previous_query) {
				unset($GLOBALS['wp_query']);
			} else {
				$GLOBALS['wp_query'] = $previous_query;
			}
			if (null === $previous_the_query) {
				unset($GLOBALS['wp_the_query']);
			} else {
				$GLOBALS['wp_the_query'] = $previous_the_query;
			}
			set_current_screen(null);
		}
	}

	public function test_legacy_query_is_unchanged_outside_admin_and_when_filter_inactive(): void
	{
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		unset($_GET['nem_follow_up_required']);
		$integration = new OrdersListIntegration(new OrderFollowUpMetadata());
		$query = new \WP_Query();
		$query->query_vars['post_type'] = 'shop_order';
		$query->query_vars['meta_query'] = [['key' => 'existing', 'value' => 'value']];
		$previous_query = $GLOBALS['wp_query'] ?? null;
		$previous_the_query = $GLOBALS['wp_the_query'] ?? null;
		$GLOBALS['wp_query'] = $query;
		$GLOBALS['wp_the_query'] = $query;

		try {
			set_current_screen(null);
			$before = $query->query_vars;
			$integration->filterLegacyQuery($query);
			self::assertSame($before, $query->query_vars);

			set_current_screen('edit-shop_order');
			$before = $query->query_vars;
			$integration->filterLegacyQuery($query);
			self::assertSame($before, $query->query_vars);
		} finally {
			if (null === $previous_query) {
				unset($GLOBALS['wp_query']);
			} else {
				$GLOBALS['wp_query'] = $previous_query;
			}
			if (null === $previous_the_query) {
				unset($GLOBALS['wp_the_query']);
			} else {
				$GLOBALS['wp_the_query'] = $previous_the_query;
			}
			set_current_screen(null);
		}
	}

	private function createOrder(mixed $metadata_value): \WC_Order
	{
		$order = $this->createMock(\WC_Order::class);
		$order->method('get_type')->willReturn('shop_order');
		$order->expects(self::once())->method('get_meta')->with('_nem_follow_up_required', true)->willReturn($metadata_value);

		return $order;
	}
}
