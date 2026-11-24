<?php

declare(strict_types=1);

use NorthernExposure\OrderFollowUpManager\OrderFollowUpMetadata;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/wp-content/plugins/order-follow-up-manager/includes/class-order-follow-up-metadata.php';

final class OrderFollowUpMetadataTest extends TestCase
{
	public function test_missing_flag_defaults_to_not_required(): void
	{
		$order = $this->createStandardOrderMock();
		$order->expects(self::once())->method('get_meta')->with('_nem_follow_up_required', true)->willReturn('');

		self::assertFalse((new OrderFollowUpMetadata())->isRequired($order));
	}

	public function test_reads_yes_and_no_flag_values(): void
	{
		$metadata = new OrderFollowUpMetadata();
		$yes_order = $this->createStandardOrderStub();
		$yes_order->method('get_meta')->willReturn('yes');
		$no_order = $this->createStandardOrderStub();
		$no_order->method('get_meta')->willReturn('no');

		self::assertTrue($metadata->isRequired($yes_order));
		self::assertFalse($metadata->isRequired($no_order));
	}

	public function test_sets_required_flag_and_saves_order(): void
	{
		$order = $this->createStandardOrderMock();
		$order->expects(self::once())->method('update_meta_data')->with('_nem_follow_up_required', 'yes');
		$order->expects(self::once())->method('save');

		(new OrderFollowUpMetadata())->setRequired($order);
	}

	public function test_sets_not_required_flag_and_saves_order(): void
	{
		$order = $this->createStandardOrderMock();
		$order->expects(self::once())->method('update_meta_data')->with('_nem_follow_up_required', 'no');
		$order->expects(self::once())->method('save');

		(new OrderFollowUpMetadata())->setNotRequired($order);
	}

	public function test_reads_and_saves_details(): void
	{
		$metadata = new OrderFollowUpMetadata();
		$read_order = $this->createStandardOrderStub();
		$read_order->method('get_meta')->willReturn('Call next week');
		$save_order = $this->createStandardOrderMock();
		$save_order->expects(self::once())->method('update_meta_data')->with('_nem_follow_up_details', 'Call next week');
		$save_order->expects(self::once())->method('save');

		self::assertSame('Call next week', $metadata->getDetails($read_order));
		$metadata->saveDetails($save_order, 'Call next week');
	}

	public function test_clears_details_without_changing_flag(): void
	{
		$order = $this->createStandardOrderMock();
		$order->expects(self::once())->method('delete_meta_data')->with('_nem_follow_up_details');
		$order->expects(self::once())->method('save');
		$order->expects(self::never())->method('update_meta_data');

		(new OrderFollowUpMetadata())->clearDetails($order);
	}

	public function test_changing_flag_does_not_clear_details(): void
	{
		$order = $this->createStandardOrderMock();
		$order->expects(self::once())->method('update_meta_data')->with('_nem_follow_up_required', 'yes');
		$order->expects(self::never())->method('delete_meta_data');

		(new OrderFollowUpMetadata())->setRequired($order);
	}

	private function createStandardOrderMock(): \WC_Order
	{
		$order = $this->createMock(\WC_Order::class);
		$order->method('get_type')->willReturn('shop_order');

		return $order;
	}

	private function createStandardOrderStub(): \WC_Order
	{
		$order = $this->createStub(\WC_Order::class);
		$order->method('get_type')->willReturn('shop_order');

		return $order;
	}
}
