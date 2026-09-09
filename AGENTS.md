# Marchando Agent Guide

## Stack and Entry Points

- Laravel `13.x` on PHP `8.3`; server-rendered Blade UI with Tailwind CSS 4 and Vite. There is no React, Vue, Livewire, or API layer.
- Application web routes live in `routes/web.php`; the authenticated panel is served from `/app/{restaurant:slug}`.
- `DashboardController::modules()` is the single source for the future module navigation and placeholder pages.
- Tenant data uses the shared-schema `restaurants` plus `memberships` tables. Do not query a restaurant-scoped page without `restaurant.member` middleware.
- Restaurant configuration lives under `restaurant.settings`; the `restaurant` module remains reserved for future zones/tables work.
- Restaurant settings are stored on `restaurants`; recurring hours use `opening_hours` with `context` (`general`, `takeaway`, `delivery`), ISO weekday, and minute offsets. Overnight periods are represented by an end minute greater than 1440.
- Configuration is split into detail, channel, and hours forms at `restaurant.settings.*`; channel-specific hours are only persisted when that channel is enabled and not inheriting the general schedule.
- `RestaurantAvailability` evaluates recurring hours in each restaurant's IANA timezone; intervals are half-open and may cross midnight.
- Only `owner` memberships may update restaurant configuration; membership middleware still protects every tenant route.
- Carta lives under `restaurant.menu.*`; the `menu` placeholder route must stay excluded from the module loop once catalog routes exist.
- Catalog writes are owner-only; members may view. Categories/products are always queried through the bound restaurant and use soft deletion.
- Categories and products are soft-deleted; categories with products cannot be archived to avoid silent catalog cascades.
- Catalog money is stored as integer minor units (`price_minor`, `cost_minor`); `null` product VAT inherits `restaurants.default_vat`.
- Catalog currency is stored on `restaurants.currency` and defaults to `EUR`; display conversion must not be used for persistence.
- Formats use absolute integer minor-unit prices; products without formats keep `products.price_minor`, while products with formats calculate from the selected format.
- Modifier groups are reusable restaurant-owned resources with options, min/max selection rules, per-option supplements, and product associations. Changing a shared group affects every attached product; duplicate before specializing.
- Modifier group rules are canonical on the shared group; product associations only define product/order membership. Do not add hidden per-product overrides without an explicit product decision.
- Mission 4 modifier writes remain owner-only and must validate every product/group/option ID against the active restaurant.
- Restaurante now contains zones and dining tables under `restaurant.restaurant.*`; the root page is the member-visible operational floor plan and `/manage` is owner-only administration.
- Dining table QR tokens are random 64-character hex values, preserved across rename/move and replaced only by explicit regeneration; public resolution uses `/t/{token}` outside authenticated tenant middleware.
- QR SVGs are generated on demand with `endroid/qr-code` and printable zone sheets are HTML/CSS, not PDF files.
- Product/category channel flags are stored independently from restaurant channel activation; effective availability must also require the restaurant and category channel to be enabled.
- Product/category images use the explicit `public` disk under a restaurant-scoped path; run `php artisan storage:link` for local URLs.
- Authentication uses Laravel's session-based `web` guard. Login/logout are in `AuthenticatedSessionController`.
- Platform ownership lives in `users.is_platform_owner` and is read only through `User::isPlatformOwner()`; never compare emails in app code. A `Gate::before` rule plus the `platform.owner` middleware (`/admin/*`) and the owner bypass in `restaurant.member` middleware form the whole global authorization. Bootstrap of the initial owner happens once in migration `2026_09_16_100000_create_platform_ownership.php` via `PLATFORM_OWNER_EMAIL`.
- `users.is_active` blocks login via `Auth::attempt` credentials; `restaurants.is_active` blocks members (403) and public ordering (404) but never the platform owner. Deactivation never deletes history; there are no destroy routes for users or restaurants.
- Admin actions are recorded append-only in `platform_audits` via `PlatformAudit::record()`.
- Keep tenant Blade views multiline; single-line views with adjacent directives (`@endif@if`, nested `??`/`?->` inside `@if`/`@foreach`) have repeatedly failed to compile.

## Commands

- Install/setup: `composer install`, copy `.env.example` to `.env`, run `php artisan key:generate`, `php artisan migrate --force`, `npm install --ignore-scripts`, then `npm run build`.
- Start development: `composer run dev` (or run `php artisan serve` and `npm run dev` separately).
- Run all tests: `php artisan test --compact` or `composer test`.
- Run focused tests: `php artisan test --compact tests/Feature/AuthenticationAndRestaurantAccessTest.php` or use `--filter=TestName`.
- Format PHP: `vendor/bin/pint --format agent`. Use `--dirty` only when running inside a Git worktree.
- Verify routes/config: `php artisan route:list --except-vendor` and `php artisan config:show app.name`.
- Apply local development data: `php artisan migrate --force` followed by `php artisan db:seed --force`.
- Build frontend assets after Blade/CSS changes: `npm run build`. On Windows PowerShell with script execution blocked, use `npm.cmd run build`.
- Mission 2 focused tests: `php artisan test --compact tests/Feature/RestaurantSettingsTest.php`.
- Mission 3 focused tests: `php artisan test --compact tests/Feature/CatalogManagementTest.php`.
- Mission 4 focused tests: `php artisan test --compact tests/Feature/ProductCustomizationTest.php`.
- Mission 5 focused tests: `php artisan test --compact tests/Feature/RestaurantFloorPlanTest.php`.
- Mission 6 focused tests: `php artisan test --compact tests/Feature/EmployeeAndAttendanceTest.php`.
- Mission 7 focused tests: `php artisan test --compact tests/Feature/OrderManagementTest.php`.
- Mission 8 focused tests: `php artisan test --compact tests/Feature/OrderManagementTest.php`.
- Mission 9 focused tests: `php artisan test --compact tests/Feature/KitchenDisplayTest.php`.
- Mission 13 focused tests: `php artisan test --compact tests/Feature/SalesControlTest.php`.
- Mission 14 focused tests: `php artisan test --compact tests/Feature/Mission14Test.php`.
- Mission 15 focused tests: `php artisan test --compact tests/Feature/PilotGoldenPathTest.php` and `php artisan test --compact tests/Feature/PilotSecurityTest.php`.
- Platform owner focused tests: `php artisan test --compact tests/Feature/PlatformOwnerTest.php`.

## Development Data and Tests

- The development seeder creates `admin@marchando.test` with password `password`, the `Casa Marchando` restaurant, and an owner membership.
- Feature tests use PHPUnit, `RefreshDatabase`, in-memory SQLite, array sessions, and synchronous queues as configured in `phpunit.xml`.
- Create new Laravel files with `php artisan make:* --no-interaction`; use factories rather than hand-built model data in tests.
- Preserve tests for guest redirects, login/logout, membership authorization, and cross-restaurant isolation when changing panel routing.
- Employees are restaurant-owned operational identities, independent from SaaS users and memberships; optional user links never grant access by themselves.
- Employee PINs are hashed and scoped to the restaurant; active PIN fingerprints are unique within a restaurant and inactive employees cannot use them.
- Work intervals store UTC instants, support multiple/open overnight intervals, and administrative corrections append immutable audit records with actor and reason.
- Mission 6 terminal routes require an authenticated restaurant member and then a valid active employee PIN; no separate device-token model exists yet.
- TPV occupancy is derived from `active_table_orders`; do not repurpose `dining_tables.is_active` for occupied state.
- TPV accounts use persistent orders with draft/submitted rounds, immutable line snapshots, integer minor-unit totals, and employee attribution.
- Only authenticated restaurant members may enter the TPV; operational employees are separately verified by PIN for order attribution.
- Mission 7 deliberately excludes payments, cash, kitchen/KDS dispatch, printing, realtime synchronization, split bills, and table transfers.
- Mission 8 adds audited table transfers, confirmed-line voids, manager-authorized manual prices and discounts, account recovery, and pending split plans; it still excludes payments and kitchen.
- Split plans are preparation only: parts remain pending and do not free a table or represent payment.
- Confirmed order lines retain original snapshots; voids reduce active quantity without deleting history.
- Kitchen uses submitted order rounds and snapshot-derived kitchen items; it does not duplicate commercial sales lines.
- Kitchen stations are restaurant-owned and product routing falls back visibly to `Sin asignar` when not configured.
- Kitchen state transitions are persisted per item with versioned work state; the feed is tenant-scoped and reconciles from the database.
- Mission 9 excludes payments, printers, stock, recipes, and hardware; Reverb/Echo are optional transport acceleration over reconciliation.

## Workflow Constraints

- Use named routes and route model binding for restaurant links; never trust a restaurant ID/slug without checking the authenticated user's membership.
- Keep future modules as explicit, minimal placeholders until their mission is requested; do not add operational entities such as orders, products, reservations, payments, or kitchen data prematurely.
- Run Laravel Pint after PHP edits and the narrowest relevant PHPUnit test before the full suite.
- Laravel Boost is installed as a dev dependency and its MCP configuration is in `opencode.json`; use its Laravel-aware tools when available.
- If PHP/Composer are missing from `PATH` in the Laragon environment, use `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` and `C:\laragon\bin\composer\composer.phar` explicitly.
