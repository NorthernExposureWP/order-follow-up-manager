<?php

declare(strict_types=1);

namespace NorthernExposure\OrderFollowUpManager;

/**
 * Initializes the plugin after WooCommerce is available.
 */
final class Plugin
{
	private OrderFollowUpMetadata $metadata;

	public function initialize(): void
	{
		$this->metadata = new OrderFollowUpMetadata();
	}

	public function metadata(): OrderFollowUpMetadata
	{
		return $this->metadata;
	}
}
