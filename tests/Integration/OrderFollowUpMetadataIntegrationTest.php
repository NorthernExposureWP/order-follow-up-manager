<?php

declare(strict_types=1);

use NorthernExposure\OrderFollowUpManager\OrderFollowUpMetadata;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/wp-content/plugins/order-follow-up-manager/includes/class-order-follow-up-metadata.php';

final class OrderFollowUpMetadataIntegrationTest extends TestCase
{
	/** @var list<int> */
	private array $order_ids = [];

	protected function tearDown(): void
	{
		foreach ($this->order_ids as $order_id) {
			$order = wc_get_order($order_id);
			if ($order instanceof \WC_Order) {
				$order->delete(true);
			}
		}

		$this->order_ids = [];
		parent::tearDown();
	}

	public function test_order_metadata_persists_independently_after_reload(): void
	{
		$order = new \WC_Order();
		$order->set_created_via('order-follow-up-manager-test');
		$order->set_status('pending');
		$order->save();
		$this->order_ids[] = $order->get_id();

		$metadata = new OrderFollowUpMetadata();
		$metadata->setRequired($order);
		$metadata->saveDetails($order, 'Call customer tomorrow');

		$reloaded_order = wc_get_order($order->get_id());
		self::assertInstanceOf(\WC_Order::class, $reloaded_order);
		self::assertTrue($metadata->isRequired($reloaded_order));
		self::assertSame('Call customer tomorrow', $metadata->getDetails($reloaded_order));

		$metadata->clearDetails($reloaded_order);
		$reloaded_order = wc_get_order($order->get_id());
		self::assertInstanceOf(\WC_Order::class, $reloaded_order);
		self::assertTrue($metadata->isRequired($reloaded_order));
		self::assertSame('', $metadata->getDetails($reloaded_order));

		$metadata->saveDetails($reloaded_order, 'Details remain when flag changes');
		$metadata->setNotRequired($reloaded_order);
		$reloaded_order = wc_get_order($order->get_id());
		self::assertInstanceOf(\WC_Order::class, $reloaded_order);
		self::assertFalse($metadata->isRequired($reloaded_order));
		self::assertSame('Details remain when flag changes', $metadata->getDetails($reloaded_order));
	}
}
