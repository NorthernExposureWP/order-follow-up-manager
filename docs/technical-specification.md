# Order Follow-up Manager — Technical Specification

## 1. Purpose

This document defines the technical requirements and implementation constraints for Order Follow-up Manager.

The plugin must implement the functionality described in `docs/requirements.md`.

The specification intentionally defines required behaviour and technical boundaries rather than prescribing every implementation detail. Concrete WordPress and WooCommerce APIs, hooks, and integration points should be investigated during development with reference to the official documentation and the project's installed versions.

## 2. Platform and Compatibility

The plugin must:

- Support current WordPress and WooCommerce versions.
- Support a reasonable range of earlier versions where all required APIs are available and tested.
- Support both WooCommerce order storage implementations:
  - High-Performance Order Storage (HPOS).
  - Classic WordPress post-based order storage.
- Work without changes to its business logic when switching between HPOS and classic storage.

Minimum supported WordPress, WooCommerce, and PHP versions must be established during technical investigation and documented before release.

Compatibility must be verified through testing, not merely declared in the plugin header.

## 3. Order Data Access and Persistence

### 3.1. WooCommerce CRUD API

All order data access and modification must use the official WooCommerce Order CRUD API.

The implementation must not directly access order data through:

- `wp_posts` or `wp_postmeta` database tables.
- WordPress post metadata functions such as `get_post_meta()` and `update_post_meta()`.
- Direct SQL queries against WooCommerce order storage.

Order metadata must be managed through the appropriate WooCommerce order object methods.

### 3.2. Follow-up Data

Follow-up consists of two independent values:

- A boolean-like flag indicating whether follow-up is required.
- An optional text description.

The implementation must preserve their independence.

The metadata naming convention must avoid conflicts with WooCommerce and third-party extensions.

The plugin must store only the current values. No history or audit log is required.

### 3.3. Order Querying

Order retrieval and filtering must use supported WooCommerce order query mechanisms.

The implementation must work with both HPOS and classic storage.

Any storage-specific differences in query capabilities must be investigated before implementation. A solution that works only with one storage implementation is not acceptable.

Direct SQL queries against order storage are not permitted.

## 4. Administrative Interface

### 4.1. Order Edit Screen

Follow-up management must be integrated into the standard WooCommerce order editing interface.

The implementation must support both the classic and HPOS order editing screens.

Authorized staff must be able to:

- View the current Follow-up flag.
- Enable or disable the flag.
- View, edit, or clear Follow-up Details.

The interface must remain simple and consistent with the existing WordPress and WooCommerce administration UI.

### 4.2. Orders List

The plugin must extend the standard WooCommerce Orders List.

Required functionality:

- A Follow-up column.
- A filter for orders requiring follow-up.
- All orders as the default filter state.

Both classic and HPOS Orders List implementations must be supported.

The list must not provide inline editing or display Follow-up Details.

The implementation must use existing WordPress and WooCommerce administrative interfaces rather than creating a separate order management screen.

## 5. Security and Data Validation

All administrative operations must follow WordPress and WooCommerce security practices.

Requirements:

- Access must be restricted to authorized store staff.
- Administrators and Shop Managers must have access, subject to their permissions for the corresponding order.
- Other roles must not gain access to Follow-up functionality.
- Capability checks must be used instead of relying exclusively on role-name checks.
- State-changing requests must be protected against CSRF using appropriate nonce verification.
- Submitted values must be validated and sanitized.
- Output must be escaped appropriately.
- Order identifiers and order objects must be validated before processing operations.

Follow-up data must remain strictly internal.

It must not be exposed through customer-facing pages, My Account, WooCommerce email notifications, or other public interfaces.

## 6. Architecture and Code Quality

The implementation must follow the existing project conventions and instructions in `AGENTS.md`.

Architectural requirements:

- Keep the implementation lightweight and proportionate to the plugin's scope.
- Separate administrative integrations from Follow-up data handling where this improves clarity and maintainability.
- Avoid unnecessary abstraction layers, interfaces, factories, repositories, and service containers.
- Do not introduce DTOs unless their use is justified by actual architectural requirements.
- Avoid unnecessary third-party dependencies.
- Do not introduce a settings page or configuration options without a functional requirement.

The plugin must not depend at runtime on the root-level development environment, Docker configuration, or root-level Composer dependencies.

All runtime dependencies, if any are introduced, must be included within the distributable plugin.

## 7. Testing and Verification

The implementation must include automated tests using the project's established testing environment.

Testing must cover:

- Follow-up flag persistence.
- Follow-up Details persistence.
- Independence of the flag and description.
- Enabling, disabling, and re-enabling Follow-up.
- Order filtering.
- Access control.
- Input validation and output escaping where applicable.
- Correct behaviour when an order is moved to the Trash and restored.
- Correct behaviour when an order is permanently deleted.

Compatibility testing must cover both HPOS and classic order storage.

The implementation must also pass the project's existing quality checks, including PHPUnit, PHPStan, and PHP-CS-Fixer where applicable.

Tests must verify actual behaviour rather than merely confirming that methods or hooks exist.

## 8. Implementation Constraints

The first version must not introduce functionality outside the approved business requirements.

In particular, the following are out of scope:

- Automated customer emails or notifications.
- Multiple independent follow-up tasks per order.
- Follow-up history or audit logging.
- Bulk actions.
- Inline editing in the Orders List.
- Search by Follow-up Details text.
- Plugin settings page.
- Third-party custom order types.

Any proposed deviation from this specification or the approved business requirements must be presented for review before implementation.

## 9. Technical Investigation

Before implementing functionality that depends on specific WooCommerce integration points, the AI coding agent must:

1. Inspect the project's installed WordPress and WooCommerce versions.
2. Identify the relevant official APIs and hooks.
3. Verify their availability and compatibility with the supported storage implementations.
4. Explain any relevant differences between classic and HPOS interfaces.
5. Report potential compatibility or security concerns.

When a technical decision affects architecture or compatibility, the agent must present its proposed approach before making substantial changes.

Official WooCommerce documentation should be preferred over assumptions based on examples or outdated tutorials.