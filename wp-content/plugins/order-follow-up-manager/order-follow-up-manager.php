<?php
/**
 * Plugin Name: Order Follow-up Manager
 * Description: Stores follow-up information on WooCommerce orders.
 * Version: 0.1.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Requires at least: 6.9
 * Requires PHP: 8.4
 * Requires Plugins: woocommerce
 * WC requires at least: 10.8
 * Text Domain: order-follow-up-manager
 *
 * @package NorthernExposure\OrderFollowUpManager
 */

/**
 * Order Follow-up Manager is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * Order Follow-up Manager is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Order Follow-up Manager. If not, see
 * <https://www.gnu.org/licenses/old-licenses/gpl-2.0.html>.
 */

declare(strict_types=1);

namespace NorthernExposure\OrderFollowUpManager;

defined('ABSPATH') || exit;

add_action(
	'before_woocommerce_init',
	static function (): void {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true); // @phpstan-ignore class.notFound
	}
);

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
