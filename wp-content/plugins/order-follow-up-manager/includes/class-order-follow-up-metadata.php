<?php

declare(strict_types=1);

namespace NorthernExposure\OrderFollowUpManager;

use InvalidArgumentException;
use WC_Order;

/**
 * Reads and writes the independent follow-up values on a WooCommerce order.
 */
final class OrderFollowUpMetadata
{
	private const string REQUIRED_META_KEY = '_nem_follow_up_required';
	private const string DETAILS_META_KEY = '_nem_follow_up_details';

	public function isRequired(WC_Order $order): bool
	{
		$this->assertStandardOrder($order);

		return 'yes' === $order->get_meta(self::REQUIRED_META_KEY, true);
	}

	public function setRequired(WC_Order $order): void
	{
		$this->assertStandardOrder($order);
		$order->update_meta_data(self::REQUIRED_META_KEY, 'yes');
		$order->save();
	}

	public function setNotRequired(WC_Order $order): void
	{
		$this->assertStandardOrder($order);
		$order->update_meta_data(self::REQUIRED_META_KEY, 'no');
		$order->save();
	}

	public function getDetails(WC_Order $order): string
	{
		$this->assertStandardOrder($order);

		$details = $order->get_meta(self::DETAILS_META_KEY, true);

		return is_string($details) ? $details : '';
	}

	public function saveDetails(WC_Order $order, string $details): void
	{
		$this->assertStandardOrder($order);
		$order->update_meta_data(self::DETAILS_META_KEY, $details);
		$order->save();
	}

	public function clearDetails(WC_Order $order): void
	{
		$this->assertStandardOrder($order);
		$order->delete_meta_data(self::DETAILS_META_KEY);
		$order->save();
	}

	private function assertStandardOrder(WC_Order $order): void
	{
		if ('shop_order' !== $order->get_type()) {
			throw new InvalidArgumentException('Follow-up metadata requires a standard WooCommerce order.');
		}
	}
}
