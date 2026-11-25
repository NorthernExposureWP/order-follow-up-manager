<?php

declare(strict_types=1);

namespace NorthernExposure\OrderFollowUpManager;

/**
 * Initializes the plugin after WooCommerce is available.
 */
final class Plugin
{
	private OrderFollowUpMetadata $metadata;
	private OrderFollowUpAdmin $admin;
	private OrdersListIntegration $orders_list;

	public function initialize(): void
	{
		$this->metadata    = new OrderFollowUpMetadata();
		$this->admin       = new OrderFollowUpAdmin($this->metadata);
		$this->orders_list = new OrdersListIntegration($this->metadata);
		$this->admin->initialize();
		$this->orders_list->initialize();
	}

	public function metadata(): OrderFollowUpMetadata
	{
		return $this->metadata;
	}
}
