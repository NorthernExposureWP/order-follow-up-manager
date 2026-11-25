<?php

declare(strict_types=1);

use NorthernExposure\OrderFollowUpManager\OrderFollowUpAdmin;
use NorthernExposure\OrderFollowUpManager\OrderFollowUpMetadata;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/wp-content/plugins/order-follow-up-manager/includes/class-order-follow-up-metadata.php';
require_once dirname(__DIR__, 2) . '/wp-content/plugins/order-follow-up-manager/includes/class-order-follow-up-admin.php';

final class OrderFollowUpAdminIntegrationTest extends TestCase
{
	/** @var list<int> */
	private array $order_ids = [];
	private int $previous_user_id = 0;
	private array $original_post = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->previous_user_id = get_current_user_id();
		$this->original_post    = $_POST;
		wp_set_current_user(1);
	}

	protected function tearDown(): void
	{
		$_POST = $this->original_post;
		wp_set_current_user($this->previous_user_id);
		foreach ($this->order_ids as $order_id) {
			$order = wc_get_order($order_id);
			if ($order instanceof \WC_Order) {
				$order->delete(true);
			}
		}
		parent::tearDown();
	}

	public function test_rendering_reflects_stored_flag_and_details(): void
	{
		$order = $this->createOrder();
		$metadata = new OrderFollowUpMetadata();
		$metadata->setRequired($order);
		$metadata->saveDetails($order, 'Call next week');
		ob_start();
		(new OrderFollowUpAdmin($metadata))->renderFields($order);
		$html = (string) ob_get_clean();
		self::assertStringContainsString('Follow-up required', $html);
		self::assertStringContainsString("value=\"yes\"  checked='checked'", $html);
		self::assertStringContainsString('>Call next week</textarea>', $html);

		$order = wc_get_order($order->get_id());
		$metadata->setNotRequired($order);
		ob_start();
		(new OrderFollowUpAdmin($metadata))->renderFields($order);
		$html = (string) ob_get_clean();
		self::assertStringNotContainsString("value=\"yes\"  checked='checked'", $html);
	}

	public function test_save_updates_independent_values_and_clears_details(): void
	{
		$order = $this->createOrder();
		$metadata = new OrderFollowUpMetadata();
		$admin = new OrderFollowUpAdmin($metadata);
		$this->postFields("First\nline");
		$admin->saveFields($order->get_id());
		$saved = wc_get_order($order->get_id());
		self::assertTrue($metadata->isRequired($saved));
		self::assertSame("First\nline", $metadata->getDetails($saved));

		$this->postFields('');
		$admin->saveFields($order->get_id());
		$saved = wc_get_order($order->get_id());
		self::assertTrue($metadata->isRequired($saved));
		self::assertSame('', $metadata->getDetails($saved));

		$this->postFields('Details remain', false);
		$admin->saveFields($order->get_id());
		$saved = wc_get_order($order->get_id());
		self::assertFalse($metadata->isRequired($saved));
		self::assertSame('Details remain', $metadata->getDetails($saved));
	}

	public function test_invalid_or_missing_nonce_does_not_save(): void
	{
		$order = $this->createOrder();
		$metadata = new OrderFollowUpMetadata();
		$admin = new OrderFollowUpAdmin($metadata);
		$this->postFields('Should not save');
		$_POST['nem_order_follow_up_nonce'] = 'invalid';
		$admin->saveFields($order->get_id());
		$this->postFields('Should not save');
		unset($_POST['nem_order_follow_up_nonce']);
		$admin->saveFields($order->get_id());
		$saved = wc_get_order($order->get_id());
		self::assertFalse($metadata->isRequired($saved));
		self::assertSame('', $metadata->getDetails($saved));
	}

	public function test_user_without_order_edit_permission_cannot_save(): void
	{
		$order = $this->createOrder();
		$user_id = wp_insert_user(['user_login' => 'follow_up_no_edit_' . wp_generate_password(8, false), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
		self::assertIsInt($user_id);
		wp_set_current_user($user_id);
		$this->postFields('Unauthorized');
		(new OrderFollowUpAdmin(new OrderFollowUpMetadata()))->saveFields($order->get_id());
		$saved = wc_get_order($order->get_id());
		self::assertFalse((new OrderFollowUpMetadata())->isRequired($saved));
		self::assertSame('', (new OrderFollowUpMetadata())->getDetails($saved));
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user($user_id);
	}

	public function test_invalid_orders_and_refunds_are_rejected(): void
	{
		$order = $this->createOrder();
		$order->set_total(10);
		$order->save();
		$refund = wc_create_refund(['amount' => 1, 'order_id' => $order->get_id(), 'reason' => 'test']);
		self::assertInstanceOf(\WC_Order_Refund::class, $refund);
		$this->postFields('Do not save');
		$admin = new OrderFollowUpAdmin(new OrderFollowUpMetadata());
		$admin->saveFields(999999999);
		$admin->saveFields($refund->get_id());
		self::assertFalse((new OrderFollowUpMetadata())->isRequired(wc_get_order($order->get_id())));
	}

	private function createOrder(): \WC_Order
	{
		$order = new \WC_Order();
		$order->set_created_via('order-follow-up-manager-test');
		$order->set_status('pending');
		$order->set_total(10);
		$order->save();
		$this->order_ids[] = $order->get_id();
		return $order;
	}

	private function postFields(string $details, bool $required = true): void
	{
		$_POST['nem_order_follow_up_submitted'] = '1';
		$_POST['nem_order_follow_up_nonce'] = wp_create_nonce('nem_save_order_follow_up');
		$_POST['nem_follow_up_details'] = $details;
		if ($required) {
			$_POST['nem_follow_up_required'] = 'yes';
		} else {
			unset($_POST['nem_follow_up_required']);
		}
	}
}
