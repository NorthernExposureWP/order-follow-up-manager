<?php

declare(strict_types=1);

namespace NorthernExposure\OrderFollowUpManager;

use WC_Order;

/**
 * Adds follow-up controls to the WooCommerce order edit screen.
 */
final class OrderFollowUpAdmin
{
	private const string NONCE_ACTION = 'nem_save_order_follow_up';
	private const string NONCE_NAME = 'nem_order_follow_up_nonce';
	private const string SUBMITTED_NAME = 'nem_order_follow_up_submitted';

	public function __construct(private OrderFollowUpMetadata $metadata)
	{
	}

	public function initialize(): void
	{
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
		add_action('woocommerce_admin_order_data_after_order_details', [$this, 'renderFields']);
		add_action('woocommerce_process_shop_order_meta', [$this, 'saveFields'], 60, 1);
	}

	public function enqueueAdminStyles(): void
	{
		$screen = get_current_screen();
		if (!$screen instanceof \WP_Screen) {
			return;
		}

		$is_legacy_order_editor = 'shop_order' === $screen->post_type;
		$is_hpos_orders_screen  = str_contains($screen->id, 'wc-orders');

		if (!$is_legacy_order_editor && !$is_hpos_orders_screen) {
			return;
		}

		$style_path = dirname(__DIR__) . '/assets/css/admin.css';
		wp_enqueue_style(
			'order-follow-up-manager-admin',
			plugins_url('../assets/css/admin.css', __FILE__),
			[],
			(string) filemtime($style_path)
		);
	}

	public function renderFields(WC_Order $order): void
	{
		if ('shop_order' !== $order->get_type() || !$this->canEditOrder($order->get_id())) {
			return;
		}

		?>
		<div class="form-field form-field-wide nem-follow-up-checkbox">
			<?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
			<input type="hidden" name="<?php echo esc_attr(self::SUBMITTED_NAME); ?>" value="1">
			<label for="nem_follow_up_required">
				<input type="checkbox" id="nem_follow_up_required" name="nem_follow_up_required" value="yes" <?php checked($this->metadata->isRequired($order)); ?>>
				<?php esc_html_e('Follow-up required', 'order-follow-up-manager'); ?>
			</label>
		</div>
		<p class="form-field form-field-wide">
			<label for="nem_follow_up_details"><?php esc_html_e('Follow-up details', 'order-follow-up-manager'); ?></label>
			<textarea id="nem_follow_up_details" name="nem_follow_up_details" rows="4" class="input-text wide-input"><?php echo esc_textarea($this->metadata->getDetails($order)); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Save submitted controls after WooCommerce's core order-save callbacks.
	 * The second hook argument differs between HPOS and legacy order storage and is intentionally unused.
	 */
	public function saveFields(int $order_id): void
	{
		if (!isset($_POST[self::SUBMITTED_NAME], $_POST[self::NONCE_NAME], $_POST['nem_follow_up_details'])) {
			return;
		}

		$submitted = wp_unslash($_POST[self::SUBMITTED_NAME]);
		$nonce     = wp_unslash($_POST[self::NONCE_NAME]);
		$details   = wp_unslash($_POST['nem_follow_up_details']);

		if ('1' !== $submitted || !is_string($nonce) || !wp_verify_nonce($nonce, self::NONCE_ACTION) || !is_string($details)) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order || 'shop_order' !== $order->get_type() || !$this->canEditOrder($order_id)) {
			return;
		}

		if (isset($_POST['nem_follow_up_required'])) {
			$required = wp_unslash($_POST['nem_follow_up_required']);
			if (!is_string($required) || 'yes' !== $required) {
				return;
			}
			$this->metadata->setRequired($order);
		} else {
			$this->metadata->setNotRequired($order);
		}

		$details = sanitize_textarea_field($details);
		if ('' === $details) {
			$this->metadata->clearDetails($order);
		} else {
			$this->metadata->saveDetails($order, $details);
		}
	}

	private function canEditOrder(int $order_id): bool
	{
		$post_type = get_post_type_object('shop_order');

		return null !== $post_type && current_user_can($post_type->cap->edit_post, $order_id);
	}
}
