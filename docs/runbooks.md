# Marchando — Runbooks de piloto

Versión: ver `MARCHANDO_VERSION` en `.env` (también visible al pie del panel y en cada registro fiscal como versión SIF).

## 1. Internet caído
- **Detectar**: el KDS muestra "reconectando"; el tracking público no actualiza; `php artisan queue:monitor` no aplica (ver colas).
- **Impacto**: no entra ni sale nada en tiempo real; la operativa local sigue (TPV, KDS por polling, caja).
- **Hacer**: seguir operando con normalidad; no reintroducir comandas "por si acaso" (están persistidas).
- **NO hacer**: no cobrar online ni prometer envío fiscal inmediato; los registros quedan `pending` y se reintentan.
- **Recuperación**: al volver la red, TPV/KDS reconcilian desde base de datos (polling + Echo); verificar panel fiscal e integraciones.

## 2. Impresora caída
- **Detectar**: trabajo en estado `error` en Integraciones → Hardware; la cocina sigue viendo el KDS.
- **Impacto**: solo papel; ningún pedido se pierde (cola persistente).
- **Hacer**: reintentar desde la cola (máx. 5 intentos); si supera el tope, usar **Reimprimir** (sale marcado como REIMPRESIÓN y auditado).
- **NO hacer**: no reenviar la comanda desde el TPV como si fuera nueva (duplicaría cocina).
- **Recuperación**: al volver la impresora, un único reintento; comprobar historial del trabajo.

## 3. KDS no actualiza
- **Detectar**: indicador de conexión del KDS; comparar con Integraciones → Hardware → cola.
- **Hacer**: recargar la página (reconcilia desde el feed); si persiste, comprobar Reverb (`reverb:start`) y queue worker.
- **NO hacer**: no reimprimir comandas como "nuevas".

## 4. Pago online incierto
- **Detectar**: intención en `processing`/`failed` en el seguimiento; webhook pendiente.
- **Hacer**: NO marcar pagado manualmente; esperar webhook o pulsar retorno (reconcilia contra el proveedor).
- **NO hacer**: no aceptar el pedido como pagado por un pantallazo del cliente.
- **Recuperación**: reintentar el pago genera una intención nueva e idempotente; el webhook duplicado no duplica pagos.

## 5. Caja descuadrada
- **Detectar**: diferencia en el arqueo al cerrar.
- **Hacer**: revisar Analítica → Auditoría (módulo caja) y movimientos de la sesión; los pagos registrados NO se tocan.
- **NO hacer**: no "ajustar" el declarado para que cuadre; registrar la diferencia real.

## 6. AEAT no disponible / registro rechazado
- **Detectar**: panel Fiscalidad muestra pendientes/errores/rechazados (también en el dashboard, "Requiere atención").
- **Hacer**: la factura NUNCA se pierde; reintentar desde el detalle. Un rechazo requiere corrección de datos (NIF, importes) y nuevo intento.
- **NO hacer**: no editar ni borrar el registro; ante factura errónea usar **Anular fiscalmente** (genera registro de anulación encadenado, el alta se conserva).
- **Recuperación**: el reintento es idempotente por registro; test y producción no se mezclan.

## 7. Backup / restore
- Crear: `php artisan backup:create --verify` (copia SQLite + manifiesto con conteos; conserva 14 copias).
- Automático: programado a diario 04:00 vía scheduler (`backup:create`); requiere cron/worker del scheduler en producción.
- Restaurar: copiar `storage/app/backups/marchando-<fecha>.sqlite` sobre la ruta de `DB_DATABASE` con la app detenida; verificar con el manifiesto (restaurantes, pedidos, ventas).
- RPO/RTO piloto: RPO ≤ 24 h (backup diario), RTO ~30 min (restaurar + `migrate --force` + `view:cache`).

## 8. Empleado bloqueado / PIN olvidado
- Un owner puede cambiar el PIN desde Personal; los intentos PIN están limitados (throttle) y el TPV puede bloquearse por cuenta desde "Bloquear TPV".
