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

	public function initialize(): void
	{
		$this->metadata = new OrderFollowUpMetadata();
		$this->admin    = new OrderFollowUpAdmin($this->metadata);
		$this->admin->initialize();
	}

	public function metadata(): OrderFollowUpMetadata
	{
		return $this->metadata;
	}
}
