<div align="center">

# 🍽️ Marchando

### La plataforma completa para gestionar tu restaurante

**TPV · Cocina · Carta · Personal · Ventas · Fiscalidad · Pedidos online**

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Tests](https://img.shields.io/badge/Tests-107%20pasando-22c55e?style=for-the-badge&logo=phpunit&logoColor=white)](#-calidad-y-tests)

[🚀 Empezar](#-instalación) · [📖 Manual de usuario](docs/manual-usuario.md) · [✨ Módulos](#-qué-incluye) · [🧪 Tests](#-calidad-y-tests)

</div>

---

## 💡 ¿Qué es Marchando?

Marchando es una aplicación web para llevar un restaurante de principio a fin desde el navegador: tomar comandas en sala, cobrar, enviar a cocina, gestionar la carta con QR, controlar al personal y sus turnos, ver las ventas, emitir facturas, conectar impresoras y pagos online, y recibir pedidos de QR, Take Away y Delivery.

Todo multi-restaurante, con permisos por roles y un propietario global que lo administra todo desde la propia aplicación.

```
┌─────────────────────────────────────────────────────────┐
│                    🧑‍💼 ADMINISTRACIÓN                    │
│       Restaurantes · Usuarios · Auditoría global        │
└───────────────────────────┬─────────────────────────────┘
                            │ entra en cualquiera
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌───────────────┐   ┌───────────────┐   ┌───────────────┐
│ Casa Marchando│   │  Local Centro │   │     …         │
│ TPV·KDS·Carta │   │ TPV·KDS·Carta │   │               │
│ Caja·Personal │   │ Caja·Personal │   │               │
└───────┬───────┘   └───────┬───────┘   └───────────────┘
        │                   │
        ▼                   ▼
┌───────────────┐   ┌───────────────┐
│ 📱 Cliente QR │   │ 🛵 Take Away  │
│ 🛵 Delivery   │   │ 📦 Seguimiento│
└───────────────┘   └───────────────┘
```

---

## ✨ Qué incluye

| Módulo | Descripción |
|---|---|
| 🧾 **TPV** | Mesas por zonas, cuentas por rondas, cobro completo/parcial/mixto, cambio automático, traslados, splits cobrables, anulaciones auditadas, descuentos, precios manuales, cupones y fidelización |
| 💰 **Caja** | Sesiones con fondo inicial, arqueo con diferencias, ingresos y retiradas, apertura automática del cajón |
| 📦 **Pedidos** | Bandeja única de QR, Take Away y Delivery: aceptar con PIN, rechazar con motivo y reembolso automático si estaba pagado |
| 👨‍🍳 **Cocina (KDS)** | Panel oscuro auto-actualizado con temporizadores, filtro por estaciones, estados En cola → En preparación → Listo → Servido, cancelaciones reconocidas |
| 📋 **Carta** | Categorías, productos con alérgenos e imágenes, formatos (Tapa/Media/Ración), modificadores compartidos con suplementos, disponibilidad por canal |
| 🗺️ **Restaurante** | Plano operativo en vivo, zonas y mesas, QR por mesa con regeneración y hoja imprimible por zona |
| 👥 **Personal** | Empleados con PIN de 4–8 cifras, funciones (encargado/camarero/cocina/reparto), terminal de fichaje, correcciones auditadas |
| 📊 **Analítica** | Panel con facturación, ticket medio, top ventas, margen y tiempos de cocina; ventas, tickets, facturas inmutables, clientes, stock con trazabilidad, auditoría y CSV |
| 🔌 **Integraciones** | Impresoras y conectores, Stripe (pruebas/producción), fiscalidad VERI*FACTU con QR tributario, contabilidad, webhooks firmados, cupones y fidelización |
| ⚙️ **Configuración** | Datos, fiscalidad, canales (sala/Take Away/Delivery) y horarios semanales con franjas nocturnas |
| 📱 **Canal público** | Carta QR sin app ni login, carrito, checkout, seguimiento con token y pago online o simulado |

> 📖 **¿Quieres aprender a usarlo todo paso a paso?** Lee el [**Manual de usuario**](docs/manual-usuario.md): 18 capítulos desde el primer clic hasta el último rincón.

---

## 🛠️ Tecnología

- **Backend:** PHP 8.3 · Laravel 13 · Eloquent · Blade
- **Frontend:** Tailwind CSS 4 · Vite · JavaScript nativo (sin frameworks SPA)
- **Datos:** MySQL/SQLite · migraciones · seeders con datos de demo
- **Tiempo real:** polling con reconciliación (Reverb/Echo opcional)
- **Impresión:** colas tolerantes a fallos + conectores locales · QR con `endroid/qr-code`
- **Calidad:** 107 tests PHPUnit · Laravel Pint · `composer audit` limpio

---

## 🚀 Instalación

```bash
# 1. Clonar y entrar
git clone https://github.com/Cotredes/Marchando.git
cd Marchando

# 2. Dependencias
composer install
npm install --ignore-scripts

# 3. Entorno
cp .env.example .env
php artisan key:generate

# 4. Base de datos + datos de demo
php artisan migrate --force
php artisan db:seed --force

# 5. Assets
npm run build

# 6. Arrancar
composer run dev
```

Abre http://localhost:8000 y entra con la cuenta de demostración:

| Email | Contraseña |
|---|---|
| `admin@marchando.test` | `password` |

> Incluye el restaurante **Casa Marchando** con carta, mesas con QR, empleados con PIN, una cuenta abierta y estaciones de cocina, listo para probar el TPV y el KDS.

---

## 🧪 Calidad y tests

```bash
# Suite completa
php artisan test --compact        # o: composer test

# Por áreas
php artisan test --compact tests/Feature/PlatformOwnerTest.php      # propietario global y autorización
php artisan test --compact tests/Feature/PilotGoldenPathTest.php    # flujos de turno completos
php artisan test --compact tests/Feature/OrderManagementTest.php    # TPV y cuentas
php artisan test --compact tests/Feature/KitchenDisplayTest.php     # KDS

# Formato y rutas
vendor/bin/pint --format agent
php artisan route:list --except-vendor
```

---

## 📚 Documentación

| Documento | Contenido |
|---|---|
| [📖 Manual de usuario](docs/manual-usuario.md) | Guía completa de uso, módulo por módulo |
| [🆘 Runbooks](docs/runbooks.md) | Qué hacer ante 8 incidentes típicos |
| [🧪 Piloto](docs/piloto.md) | Alcance y criterios del piloto controlado |
| [🏭 Producción](docs/produccion.md) | Checklist de despliegue |
| [🏗️ Arquitectura](docs/arquitectura.md) | Visión técnica del sistema |
| [🔒 Privacidad](docs/privacidad.md) | Tratamiento de datos |

---

## 🔐 Seguridad y principios

- 🔑 Sesiones con PIN operativo separado del login; secretos que nunca se vuelven a mostrar.
- 🧅 Multi-tenant estricto: cada restaurante solo ve sus datos; el propietario global cambia de contexto de forma explícita.
- 🧾 Nada se borra: ventas, pagos, facturas, fiscalidad y auditoría son append-only; los errores se corrigen con flujos auditados.
- 💶 Todo el dinero en céntimos enteros, sin floats.

---

<div align="center">

**Hecho con 🧡 para la hostelería**

*Marchando — del primer café de la mañana al arqueo de la noche.*

</div>
