# Marchando — Checklist de piloto

## Pre-servicio (panel Inicio → "Listo para operar")
- [ ] Carta con productos, precio e IVA
- [ ] Mesas operativas con QR activo
- [ ] Personal operativo con PIN
- [ ] Cocina con estaciones (revisar "Sin asignar")
- [ ] Caja configurada y sesión abierta
- [ ] Métodos de pago activos
- [ ] Horario general definido
- [ ] Identidad fiscal (NIF) si se emitirá factura
- [ ] Impresoras (el KDS cubre si no hay conector en línea)

## Turno piloto mínimo
1. MARIO abre caja (fondo registrado).
2. ANA abre mesas, añade productos, envía comandas (2 rondas).
3. Cocina prepara en KDS; un QR pide y se acepta a la misma cuenta.
4. Traslado de mesa, anulación con motivo, descuento autorizado.
5. Split en 2 + pago mixto (efectivo con apertura de cajón + tarjeta).
6. Ticket impreso; Take Away aceptado, cobrado y recogido.
7. Arqueo y cierre de caja sin diferencias inexplicadas.
8. Analytics = Pagos = Caja; stock refleja ventas; auditoría refleja incidencias.

## Fallos a provocar durante el piloto
- Doble tap en cobrar/aceptar/enviar (sin duplicados).
- Dos camareros sobre la misma mesa (una sola cuenta).
- Impresora apagada (KDS sigue, reintento único).
- PIN erróneo repetido (throttle, sin bloqueo legítimo).
- Pago online fallido + webhook duplicado (sin doble pago).
- AEAT simulado en `offline` y `reject_once` (pendiente visible, reintento seguro).

## Criterios de bloqueo (P0, no piloto si aparecen)
Pérdida de pedidos, doble cobro, fuga multi-tenant, total incorrecto, split incorrecto,
caja incoherente, factura duplicada, stock con doble descuento, QR a mesa incorrecta,
KDS perdiendo comandas, secretos expuestos, backup no restaurable, incidencia fiscal silenciosa.
