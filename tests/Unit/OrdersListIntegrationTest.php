<?php

declare(strict_types=1);

use NorthernExposure\OrderFollowUpManager\OrderFollowUpMetadata;
use NorthernExposure\OrderFollowUpManager\OrdersListIntegration;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/wp-content/plugins/order-follow-up-manager/includes/class-order-follow-up-metadata.php';
require_once dirname(__DIR__, 2) . '/wp-content/plugins/order-follow-up-manager/includes/class-orders-list-integration.php';

final class OrdersListIntegrationTest extends TestCase
{
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

	private function createOrder(mixed $metadata_value): \WC_Order
	{
		$order = $this->createMock(\WC_Order::class);
		$order->method('get_type')->willReturn('shop_order');
		$order->expects(self::once())->method('get_meta')->with('_nem_follow_up_required', true)->willReturn($metadata_value);

		return $order;
	}
}
