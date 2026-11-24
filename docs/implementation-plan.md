# Order Follow-up Manager — Implementation Plan

## 1. Purpose and scope

This plan describes the implementation sequence for the Order Follow-up Manager defined by `docs/technical-specification.md`. The implementation will provide one internal follow-up flag and an optional independent details field per WooCommerce order, with controls on both supported order edit screens and a filterable Follow-up column in both Orders List implementations.

The plan does not add customer notifications, history, multiple tasks, bulk actions, inline editing, details search, settings, or support for third-party order types. The referenced `docs/requirements.md` is not present in this checkout; the technical specification is therefore the available source for functional scope until that requirements document is supplied.

## 2. Discovery and compatibility baseline

- The active WooCommerce plugin in `wp-content/plugins/woocommerce` reports version **11.1.2**.
- The untracked `woocommerce.10.8.1/woocommerce` directory contains the audited WooCommerce **10.8.1** source snapshot. The WooCommerce 10.8.1 API audit is complete and informs this plan; the snapshot is reference material and must not be modified or included in the implementation.
- Minimum supported versions are **PHP 8.4**, **WordPress 6.9**, and **WooCommerce 10.8**. Verify the implementation against these baselines and the project's active environment.
- The public entry point `public/index.php` only loads the WordPress bootstrap. The feature belongs in a plugin under `wp-content/plugins/`, not in WordPress Core, WooCommerce, or the front controller.
- Use the plugin's PSR-4 namespace `NorthernExposure\OrderFollowUpManager`. Its small main plugin file is the bootstrap and loads the required class files with `require_once`; the plugin must not depend on the project-level Composer autoloader or add Composer configuration/dependencies.

Record the active WordPress, PHP, PHPUnit, and test-environment versions from project configuration/runtime as implementation context. The minimum supported versions above are already agreed and are not an open decision.

## 3. Proposed implementation shape

Use a small, project-owned plugin with a bootstrap file that loads the needed class files with `require_once`. Put classes under the `NorthernExposure\OrderFollowUpManager` PSR-4 namespace and follow the project's coding conventions. Keep the design proportionate: no service container, repository layer, DTOs, settings page, Composer autoload dependency, or additional third-party dependency.

Store the two values as order metadata using these keys:

- Follow-up flag: `_nem_follow_up_required`
- Follow-up details: `_nem_follow_up_details`

Read, write, and query orders through WooCommerce CRUD objects and supported order query mechanisms only. Do not use post metadata functions, direct order-table SQL, or storage-specific business logic. Saving either value must preserve the other; clearing details must not implicitly disable follow-up, and toggling the flag must not clear details.

Use the audited WooCommerce extension points for:

- Adding the controls on the order edit screen with `woocommerce_admin_order_data_after_order_details`.
- Saving the controls with `woocommerce_process_shop_order_meta`. Its first argument is the order ID in both modes. In HPOS, its second argument is a `WC_Order`; in legacy mode, its second argument is a `WP_Post`. Use the common order ID with `wc_get_order()` to obtain the order object before reading or updating order metadata. Since `wc_get_order()` may return a `WC_Order`, a `WC_Order_Refund`, or `false`, validate that it returned an appropriate order object before using its CRUD methods.
- Adding and rendering a Follow-up column with the mode-specific hooks:
  - HPOS: `manage_woocommerce_page_wc-orders_columns` and `manage_woocommerce_page_wc-orders_custom_column`.
  - Legacy: `manage_edit-shop_order_columns` and `manage_shop_order_posts_custom_column`.
- Adding a Follow-up-only list filter while keeping the default view unfiltered, using `woocommerce_order_list_table_restrict_manage_orders` for HPOS and `restrict_manage_posts` for legacy.
- Applying the filter through the supported order query path in both storage modes: `woocommerce_order_list_table_prepare_items_query_args` for HPOS and `pre_get_posts` for legacy. In the legacy `pre_get_posts` callback, proceed only when `is_admin()` is true, `$query->is_main_query()` is true, and `$query->get( 'post_type' ) === 'shop_order'`.

The WooCommerce 10.8.1 API audit and hook selections are complete. Apply those decisions and check compatibility with the active 11.1.2 environment during implementation. Avoid relying on WooCommerce internal APIs unless the public extension points cannot satisfy a specified requirement and the trade-off is documented for review.

Administrative rendering and saving must use order-specific capability checks, nonce verification for state changes, validation and sanitization of submitted values, and context-appropriate escaping. Grant no new capabilities. Keep the data out of customer-facing pages, My Account, emails, and public API responses.

## 4. Work sequence

### Phase 1 — Confirm requirements and API choices

1. Use the agreed requirements and completed WooCommerce 10.8.1 audit decisions; resolve any mismatch with `docs/technical-specification.md` before coding.
2. Record active runtime versions for context and verify the agreed PHP 8.4, WordPress 6.9, and WooCommerce 10.8 minimums.
3. Apply `woocommerce_admin_order_data_after_order_details` and `woocommerce_process_shop_order_meta`. Treat the save hook's first argument as the common order ID; its second argument is `WC_Order` under HPOS and `WP_Post` in legacy mode. Use `wc_get_order()` with the order ID to get the order object for CRUD metadata operations, and account for its possible `WC_Order`, `WC_Order_Refund`, and `false` return values. Use the HPOS and legacy Orders List hooks specified above; restrict the legacy `pre_get_posts` callback to admin, the main query, and `shop_order` post type.
4. Confirm how the project test bootstrap loads WordPress and WooCommerce, and how HPOS can be switched for integration coverage.

### Phase 2 — Add plugin and metadata behavior

1. Create the minimal plugin bootstrap in the project-owned plugin area, using `NorthernExposure\OrderFollowUpManager` for PSR-4 class names and `require_once` in the main file to load required classes. Do not configure or rely on Composer autoloading.
2. Implement metadata reads and writes via WooCommerce order objects using `_nem_follow_up_required` and `_nem_follow_up_details`, with independent flag/details behavior.
3. Add focused unit coverage for validation and independence where suitable; add integration coverage for CRUD persistence and order lifecycle behavior.

### Phase 3 — Add order edit controls

1. Render the flag and optional details field on the classic and HPOS order edit screens using the confirmed extension points.
2. Save values only after verifying the request nonce, current user's capability for the specific order, a valid order object, and accepted input types.
3. Escape all rendered values and labels. Verify that clearing details and changing the flag have the independent behavior required by the specification.

### Phase 4 — Add Orders List column and filter

1. Add a Follow-up column to both list implementations, displaying only whether follow-up is required; do not display details or offer inline editing.
2. Add a filter with an all-orders default and a Follow-up-required option.
3. Apply filtering through supported WooCommerce order queries, validating the filter value and checking correct results under HPOS and classic storage.

### Phase 5 — Verify security, lifecycle, compatibility, and quality

1. Verify unauthorized users cannot access or change the feature and that an order-specific edit capability is respected.
2. Verify the metadata is absent from customer-facing output, My Account, email notifications, and public API responses.
3. Verify flag/details persistence and independence; enabling, disabling, and re-enabling; filtering; trash and restore; and permanent deletion behavior.
4. Run integration coverage with HPOS enabled and disabled, plus the applicable project PHPUnit and PHPStan checks documented in `docs/TESTING.md` and `docs/CODE_QUALITY.md`. PHP-CS-Fixer is not used in this project.
5. Declare WooCommerce feature compatibility only after the relevant runtime checks pass. Report exact checks and any unverified compatibility range.

## 5. Acceptance criteria

- Authorized store staff can view and independently update the follow-up flag and details on both order edit screens.
- The standard Orders List has a Follow-up column and a working Follow-up-required filter in both classic and HPOS modes; all orders remain the default view.
- All order access and persistence use WooCommerce CRUD/query APIs and work without business-logic changes when storage mode changes.
- Requests are capability-checked, nonce-protected, validated, and safely escaped; follow-up data remains internal.
- Specified lifecycle cases and both storage modes are covered by behavior-focused tests.
- No out-of-scope functionality or unrelated project-file changes are introduced.
