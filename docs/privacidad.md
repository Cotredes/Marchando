# Marchando — Datos personales (estado piloto)

- **Minimización**: QR en mesa no exige cuenta; Take Away pide nombre+teléfono; Delivery añade dirección. Sin DNI ni datos innecesarios.
- **Acceso**: clientes, teléfonos, emails y direcciones solo visibles para miembros del restaurante (políticas `viewSales`/`viewSensitiveOrders`); auditoría y fiscalidad solo owner; cocina no recibe datos de contacto.
- **Rutas públicas**: tokens opacos no enumerables (QR 64 hex, seguimiento aleatorio); snapshots de tracking sin PII; `Referrer-Policy: no-referrer`.
- **Limitación conocida (post-piloto)**: cualquier miembro puede ver la ficha de cliente, no solo encargados; no existe borrado/anonymización self-service. No borrar facturas para "olvidar" a un cliente: la factura es inmutable por obligación fiscal; gestionar caso a caso (anonimizar ficha, conservar documento).
- **Secretos**: Stripe/AEAT/webhooks cifrados en BD (`encrypted`), nunca re-renderizados ni enviados al frontend; PINs con hash + throttle + sin logs.
