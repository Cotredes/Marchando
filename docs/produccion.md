# Marchando — Checklist de producción

- [ ] `APP_ENV=production`, `APP_DEBUG=false` (nunca debug en producción).
- [ ] `APP_URL` con HTTPS + `SESSION_SECURE_COOKIE=true`.
- [ ] Base de datos de producción y `php artisan migrate --force` verificado (instalación limpia y upgrade probados).
- [ ] Backups diarios programados (`backup:create` a las 04:00) + una restauración de prueba superada.
- [ ] `queue:work` supervisado (broadcasts, webhooks, fiscalidad) + `reverb:start` supervisado.
- [ ] `php artisan view:cache` + `npm run build` tras cada despliegue.
- [ ] Stripe en `live` solo con claves live; webhook configurado en el dashboard de Stripe.
- [ ] Fiscalidad en `live` solo cuando el obligado deba operar en producción (si no, `test`).
- [ ] Storage enlazado (`php artisan storage:link`) y backups incluyendo `storage/app/public`.
- [ ] Usuarios owner creados; PINs entregados fuera de banda (nunca por email/log).
- [ ] Logs revisados sin secretos (sin PIN, tarjetas, tokens ni claves).
- [ ] `MARCHANDO_VERSION` actualizada en cada release (trazabilidad fiscal y de soporte).

## Despliegue sin perder datos
1. `php artisan backup:create --verify`.
2. Desplegar código.
3. `php artisan migrate --force` (nunca `migrate:fresh` en producción).
4. `php artisan view:cache` + `npm run build` (o servir build versionado).
5. Verificar panel Inicio (readiness + atención) y una venta de prueba.
6. Rollback: restaurar backup SQLite + checkout del tag anterior + `migrate` (las migraciones de esta misión son aditivas).
