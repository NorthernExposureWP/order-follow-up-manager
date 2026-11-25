<?php
/**
 * Plugin Name: Order Follow-up Manager
 * Description: Stores follow-up information on WooCommerce orders.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 8.4
 * WC requires at least: 10.8
 * Text Domain: order-follow-up-manager
 *
 * @package NorthernExposure\OrderFollowUpManager
 */

declare(strict_types=1);

namespace NorthernExposure\OrderFollowUpManager;

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/class-order-follow-up-metadata.php';
require_once __DIR__ . '/includes/class-order-follow-up-admin.php';
require_once __DIR__ . '/includes/class-orders-list-integration.php';
require_once __DIR__ . '/includes/class-plugin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		if (class_exists('WooCommerce')) {
			(new Plugin())->initialize();
		}
	}
);
