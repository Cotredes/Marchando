# Marchando — Mapa real de dominios (M15)

- **Catálogo**: categorías, productos (precio/coste/IVA en céntimos), formatos, modificadores con reglas en el grupo, alérgenos, disponibilidad por canal. `CatalogPriceCalculator` es la única autoridad de precio.
- **Sala**: zonas, mesas (QR 64 hex, `qr_is_active`), `active_table_orders` (una cuenta por mesa).
- **TPV**: `orders` + `order_rounds` (borrador único por `draft_slot`) + `order_lines` con snapshot inmutable. `PosService` transaccional.
- **Cocina/KDS**: `kitchen_dispatches/items` por tanda, estaciones, polling + Echo (`restaurant.{id}.operations`) como invalidación.
- **Pagos/caja (M10)**: `payments` + `payment_tenders` + idempotencia `request_key`, sesiones/arqueos, estado `paid` cierra cuenta y libera mesa.
- **Público (M11)**: `public_order_requests` (token hash + idempotencia) → aceptación a tanda normal; Take Away/Delivery con `order_fulfillments` + cargos.
- **Control (M13)**: `customers` (teléfono normalizado, NIF único por restaurante), `sale_documents` + contador por tipo, `stock_movements` append-only, `AnalyticsService`/`AuditService`.
- **Integraciones (M14)**: impresoras + conector local + `print_jobs`, Stripe (`online_payment_intents`, webhooks firmados, reembolsos), VERI*FACTU (`fiscal_identities/records/attempts`, cadena hash, QR tributario), exports contables, webhooks salientes firmados, cupones, fidelización por sellos.
- **M15 (esta misión)**: sin dominios nuevos salvo índices y `backup:create`; endurece authn/PIN (throttle + bloqueo TPV), versionado (`config app.marchando_version`), readiness/atención en dashboard, scheduler de backups, tests de golden path/seguridad/carga.

Multi-tenant: todo cuelga de `restaurant_id` con middleware `restaurant.member` + policies (miembro vs owner) + checks `abort_unless` en writes.
