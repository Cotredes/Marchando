# Manual de Marchando

Guía completa para aprender a usar toda la aplicación, de principio a fin.
Escrita para ti, Ángel, como propietario de Marchando.

---

## 1. Entrar y orientarse

### 1.1 Cómo entrar

1. Abre la dirección de la aplicación en el navegador.
2. Escribe tu email y tu contraseña y pulsa entrar.
3. Llegas a tu restaurante (por ejemplo, Casa Marchando).

Si alguna vez entras y ves el mensaje "Tu cuenta está lista para empezar / Cuando tengas un restaurante asociado…", significa que esa cuenta no pertenece a ningún restaurante. Como propietario global, a ti no te saldrá: siempre caes en un restaurante o en la Administración.

### 1.2 Las dos zonas de la aplicación

Marchando tiene dos zonas separadas:

- **El panel del restaurante** (`/app/nombre-restaurante`): el día a día de un local. TPV, cocina, carta, personal, ventas… Todo lo que uses aquí afecta **solo a ese restaurante**.
- **La Administración** (`/admin`): tu zona como dueño de Marchando. Ver todos los restaurantes, crearlos, gestionar usuarios de toda la plataforma. Solo la ves tú.

En el menú lateral del panel verás al final el enlace **Administración**. En la Administración verás el enlace **Volver al panel**.

### 1.3 El restaurante actual

Arriba a la izquierda siempre pone **Restaurante actual** con su nombre, y la cabecera de cada página también lo muestra. Todo lo que hagas en el panel afecta a ese restaurante y a ningún otro.

Para cambiar de restaurante tienes dos caminos:

- El desplegable de la cabecera (arriba a la derecha): lista todos tus restaurantes. Al elegir uno, todo el panel cambia a ese contexto.
- Desde Administración → Restaurantes → **Entrar**.

### 1.4 Cinco ideas que lo explican todo

1. **Tú estás por encima de todo.** Puedes entrar en cualquier restaurante y gestionar cualquier módulo sin que nadie te tenga que dar permisos. Los demás usuarios solo pueden trabajar en los restaurantes que les asignes, con el rol que les asignes.
2. **Usuario no es lo mismo que empleado.** El *usuario* es la cuenta con la que se inicia sesión (tú, María, el encargado). El *empleado* es la identidad operativa del restaurante (ANA, MARIO…), con su PIN de 4 a 8 cifras. Las comandas y los fichajes se atribuyen al empleado, no al usuario. Tú puedes administrarlo todo, pero si abres una cuenta en el TPV tendrás que identificar al camarero con su PIN, como todo el mundo.
3. **Encargado no es lo mismo que administrador.** *Administrador* (rol `owner`) gestiona un restaurante: carta, personal, configuración. *Encargado* es una función operativa del empleado (rol `manager`): autoriza anulaciones, descuentos y cierres de caja con su PIN en el momento.
4. **Nada se borra.** Ventas, pagos, facturas, registros fiscales, auditoría y cajas cerradas no tienen botón de eliminar. Los errores se corrigen con los flujos previstos (anular, reembolsar, rectificar). Desactivar un usuario o un restaurante conserva todo su histórico.
5. **El dinero se guarda en céntimos.** En pantalla siempre verás euros (9,50 €), pero si algún formulario te pide una cantidad "en céntimos", escribe 950 para 9,50 €. Los precios de carta se escriben normal, con coma o punto (9,50).

---

## 2. Administración: tu zona como dueño de Marchando

Entra desde el menú lateral (**Administración**) o directamente en `/admin`.

### 2.1 Resumen

La primera pantalla muestra cuatro contadores: **Restaurantes**, **Restaurantes activos**, **Usuarios** y **Usuarios activos**. Debajo hay dos tarjetas para ir a **Restaurantes** y **Usuarios**.

Si hay problemas en algún local verás la sección **Incidencias**, con líneas del tipo "Casa Marchando: 3 pedidos públicos pendientes de aceptar". Cada línea es un enlace que te lleva directo al módulo donde se resuelve.

Al final verás la **Actividad reciente de administración**: las últimas acciones globales (quién creó un restaurante, quién desactivó a un usuario…). La navegación normal no se registra, solo las acciones relevantes.

### 2.2 Restaurantes

En **Administración → Restaurantes** ves todos los restaurantes con su estado (**Activo** o **Inactivo**), cuántos usuarios, mesas y empleados tiene cada uno, y tres botones:

- **Entrar**: te lleva a su panel para trabajar en él.
- **Gestionar**: edita sus datos administrativos.
- **Desactivar / Activar**: lo apaga o lo enciende.

**Crear un restaurante nuevo:**

1. Pulsa **Crear restaurante**.
2. Escribe el **Nombre** (obligatorio), la **Zona horaria** (por defecto Europa/Madrid) y la **Moneda** en 3 letras (por defecto EUR). El **Slug** (el identificador que sale en las direcciones web) se genera solo desde el nombre; puedes escribirlo a mano si quieres.
3. Pulsa **Crear restaurante**. Te lleva a la pantalla de gestión.
4. Pulsa **Entrar** y configúralo con los módulos normales: primero el checklist "Listo para operar" del Inicio (capítulo 3), que te guía por carta, mesas, personal, cocina, caja, pagos, horarios e identidad fiscal.

**Gestionar** permite cambiar nombre, slug, email, teléfono, dirección, ciudad, provincia, zona horaria y moneda. La configuración operativa (carta, zonas, horarios…) no se hace aquí, sino entrando en el restaurante.

**Desactivar** un restaurante no borra nada: conserva ventas, facturas, clientes y auditoría. Mientras está inactivo, sus miembros no pueden entrar (error de permiso) y su carta pública y sus QR dejan de funcionar. Tú sigues pudiendo entrar y reactivarlo cuando quieras. No existe "eliminar restaurante".

### 2.3 Usuarios

En **Administración → Usuarios** ves todas las cuentas: nombre, email, estado (**Activo** o **Desactivado**), tu insignia de **Propietario de Marchando** donde corresponda, y la lista de restaurantes de cada uno con su rol (**Administrador** o **Miembro**).

**Crear un usuario (por ejemplo, María, administradora de Casa Marchando):**

1. Pulsa **Crear usuario**.
2. Escribe **Nombre**, **Email** y **Contraseña** (mínimo 8 caracteres, dos veces).
3. Elige el **Restaurante** y el **Rol en el restaurante**: *Miembro* trabaja en lo operativo diario; *Administrador* además gestiona carta, personal y configuración de ese restaurante. Ojo: administrador de restaurante **no** es acceso global a Marchando.
4. Pulsa **Crear usuario**. El usuario ya puede iniciar sesión.

Desde **Gestionar** en la ficha del usuario puedes:

- Cambiar nombre, email y ponerle una contraseña nueva.
- En **Restaurantes asociados**: cambiar su rol en cada restaurante (**Cambiar rol**), **Quitar acceso** a un restaurante (solo se elimina su permiso; sus comandas, fichajes e historial quedan intactos) o **Asignar acceso** a otro restaurante con su rol.
- **Desactivar cuenta**: no podrá iniciar sesión, pero no se borra nada. Para reactivarla, el mismo botón (**Activar cuenta**).

**Protecciones que no puedes saltarte (a propósito):**

- Tu cuenta no se puede desactivar desde aquí.
- Tus asociaciones a restaurantes no se pueden modificar desde aquí (tu acceso global no depende de ellas).
- Ningún formulario permite crear otro propietario global ni convertir a nadie en propietario. Hoy solo existe tu cuenta.

---

## 3. Inicio: el cuadro de mandos de cada restaurante

Al entrar en un restaurante ves **Buenos días, Ángel**, una frase de presentación y tres bloques:

### 3.1 Requiere atención

Caja roja que solo aparece si hay algo pendiente, por ejemplo:

- Registros fiscales con incidencia → te lleva a fiscalidad.
- Trabajos de impresión en error → te lleva a hardware.
- Pedidos públicos pendientes de aceptar → te lleva a Pedidos.
- Productos con stock bajo o agotados → te lleva a stock.

Cada línea es un enlace directo al lugar donde se resuelve. Como tienes acceso global, el botón **Revisar** siempre te funciona: nunca te encontrarás un "no tienes permisos".

### 3.2 Listo para operar

Nueve tarjetas con ✓ (bien) o ✕ (falta algo), cada una con botón **Revisar** que te lleva al sitio exacto:

1. **Carta**: que haya productos activos, con precio y con IVA.
2. **Mesas**: que haya mesas activas con QR.
3. **Personal**: que haya empleados activos con PIN.
4. **Cocina**: que haya estaciones y que los productos sepan a qué estación van.
5. **Caja**: que exista caja y haya sesión abierta.
6. **Métodos de pago**: que haya métodos activos (efectivo, tarjeta…).
7. **Horario**: que el horario general tenga franjas.
8. **Identidad fiscal**: que haya NIF configurado.
9. **Impresión**: que haya impresoras (si no hay conector en línea, el KDS sigue funcionando).

Es la lista que debes completar al estrenar un restaurante.

### 3.3 Accesos rápidos

Cuatro tarjetas para ir volando a **Pedidos**, **TPV**, **Carta** y **Analítica**.

---

## 4. Pedidos: la bandeja de QR, Take Away y Delivery

En **Pedidos** ves todos los pedidos que hacen los clientes por su cuenta (QR de mesa, Take Away y Delivery) en una única bandeja, con paginación de 30.

Cada tarjeta muestra el canal (**QR · Mesa 5**, **TAKEAWAY** o **DELIVERY**), el nombre del cliente (o "Cliente anónimo"), el número de productos, el total y el estado: **pendiente**, **aceptado** o **rechazado**. Si pagó online verás la pastilla verde **Pagado online**.

**Aceptar un pedido pendiente:**

1. Escribe tu **ID de empleado** y tu **PIN** (cualquier empleado operativo vale).
2. Pulsa **Aceptar**. El pedido se crea como cuenta, se envía a cocina y verás "Pedido aceptado y enviado a cocina".

**Rechazar un pedido pendiente:**

1. Escribe el **motivo** (viene pre-rellenado "No disponible").
2. Si el pedido **no** está pagado online, cualquier empleado con PIN puede rechazarlo.
3. Si **está pagado online**, tiene que ser un **encargado** (ID + PIN de un empleado con función de encargado): al rechazar, el dinero **se reembolsa automáticamente**. Verás el aviso en la propia tarjeta.

---

## 5. TPV: el terminal punto de venta

El TPV es donde el equipo de sala abre cuentas, toma comandas y cobra. Funciona por **mesas ocupadas** y **rondas** (tandas).

### 5.1 Ver las mesas y abrir una cuenta

En **TPV** ves las zonas (botones **Todas**, **Salón**, **Terraza**…) y, agrupadas por zona, las mesas:

- **Libre** (etiqueta verde): pulsa **Abrir mesa**.
- **Ocupada** (etiqueta naranja): muestra el total actual y el camarero. Pulsa para entrar en su cuenta.

**Abrir cuenta:**

1. Indica los **Comensales** (opcional).
2. Elige el **Camarero** (solo salen empleados activos, con PIN y función de camarero o encargado).
3. Escribe su **PIN operativo**.
4. Pulsa **Abrir cuenta**. Entras en la pantalla de la cuenta.

### 5.2 La pantalla de la cuenta

Arriba ves la mesa, la zona, los comensales y la hora de apertura. Debajo hay tres zonas:

**Camarero operativo.** La cuenta necesita saber quién la lleva:

- Si pone **Identifica al camarero operativo**, elige camarero + PIN y pulsa **Identificar**. Hasta entonces no se puede añadir ni confirmar nada.
- Si ya hay **Camarero actual**, puedes **Cambiar** de camarero (otra vez con PIN) o pulsar **Bloquear TPV**. Bloquear no cierra la cuenta: solo olvida quién la estaba llevando, para que nadie pida en su nombre si se deja el terminal solo. Verás "TPV bloqueado. Identifica al camarero para continuar".

**Añadir productos.** A la izquierda tienes el **buscador** y las **categorías**. Cada producto muestra su precio (o "Desde X €" si tiene formatos/tamaños) y la etiqueta **Agotado** si no hay stock:

- Producto sencillo: botón **Añadir**.
- Producto con formatos o extras: botón **Configurar**. Eliges el **formato** (Tapa, Media, Ración…), marcas las opciones de cada grupo (punto de la carne, extras con suplemento…), la **cantidad** y una **nota** ("sin sal"), y pulsas **Añadir a la cuenta**.

**La cuenta y las rondas.** A la derecha ves las líneas:

- Las líneas de la **ronda actual (borrador)** se pueden ajustar con **−** y **+**.
- Las líneas ya **confirmadas** no se tocan con −/+: solo se pueden anular con autorización (capítulo 6).
- Cuando termines la tanda, pulsa **Confirmar comanda**. La comanda viaja a cocina (KDS e impresora) y verás "Comanda confirmada". No se puede cobrar mientras haya una ronda sin confirmar.

Si la cuenta está vacía verás el botón **Cancelar cuenta vacía**.

### 5.3 Cobrar

Pulsa **Cobrar** (en la barra de acciones de la cuenta). Verás tres cifras: **Total**, **Pagado** y **Pendiente**. Escribes los importes **en céntimos** (950 = 9,50 €); el servidor valida el total y calcula el cambio.

- **Pago completo**: el importe cubre todo lo pendiente. La cuenta se salda, la mesa queda **Libre** y se genera el ticket.
- **Pago parcial**: registras una parte (por ejemplo, 2000 de 5000). La cuenta sigue abierta con el resto pendiente.
- **Pago mixto**: combinas métodos en el mismo cobro (por ejemplo, parte en efectivo y parte con tarjeta).
- Si pagas en **efectivo**, puedes anotar lo **Entregado** y la aplicación calcula el cambio, registra el movimiento en caja y abre el cajón automáticamente.

**Requisito**: tiene que haber una **sesión de caja abierta** (capítulo 7). Si no la hay, verás un aviso y el botón deshabilitado.

Al saldar la cuenta verás "Pago registrado" y la mesa vuelve a libre en el mapa del TPV.

### 5.4 La barra de acciones de la cuenta

Encima del pedido tienes siempre: **Cobrar**, **Caja**, **Trasladar mesa**, **Dividir cuenta**, **Historial**, y varios desplegables que se explican en el capítulo 6: **Cupón**, **Recompensa**, **Descuento**, **Cliente** (asociar por teléfono), **Anular consumo consolidado** y **Precios manuales**.

---

## 6. TPV avanzado

Todo lo sensible pide un **encargado** (ID + PIN en el momento) y queda **auditado**: quién lo hizo, cuándo y por qué.

### 6.1 Trasladar una mesa

**Trasladar mesa** → elige la **Mesa destino** (solo salen las libres) → **Confirmar traslado**. La cuenta se mueve con todo su historial. Verás "Cuenta trasladada".

### 6.2 Anular algo ya confirmado

En **Anular consumo consolidado**, elige la línea, indica cuántas unidades (1 hasta las activas) y el **motivo** (obligatorio), y confirma con encargado. La cantidad activa baja, el total se recalcula, cocina recibe el aviso de anulación y el stock se devuelve. Verás "Línea anulada y auditada". No se puede anular si esa cuenta ya tiene pagos.

### 6.3 Descuentos

En **Descuento** elige el tipo (**Porcentaje** o **Importe fijo en céntimos**), el **valor** y el **motivo**, y aplica con autorización. Sustituye al descuento anterior si lo había. No se puede tocar si ya hay pagos registrados.

### 6.4 Precios manuales

Si la ficha del producto lo permite ("Permitir precio manual autorizado"), en **Precios manuales** puedes cambiar el precio de una línea en borrador escribiendo el nuevo precio en céntimos y el motivo, con encargado.

### 6.5 Cupones y fidelización

- **Cupón**: escribe el **Código** (por ejemplo VERANO10), el ID y el PIN del empleado, y pulsa **Aplicar cupón**. Los cupones se crean en Integraciones (capítulo 12).
- **Recompensa**: elige el programa de fidelización, ID + PIN, y pulsa **Canjear**. Para acumular sellos, el cliente tiene que estar asociado a la cuenta (desplegable **Cliente**: escribe su teléfono y pulsa **Asociar por teléfono**).

### 6.6 Dividir la cuenta

**Dividir cuenta** prepara subcuentas **cobrables**, pero ojo: dividir no es cobrar. La mesa no se libera hasta que todo está saldado.

- **Partes iguales**: escribe en cuántas partes (2 a 20) y pulsa **Preparar**. El total se reparte al céntimo (Cuenta 1, Cuenta 2…).
- **Por productos**: reparte unidades de cada línea entre subcuentas y pulsa **Crear subcuentas**. El descuento se reparte proporcionalmente y lo que sobre queda en "Resto de la cuenta".

Con el plan activo verás cada subcuenta con su total y su estado (**Pendiente** o **Cobrada**), botón **Cobrar** en cada una y **Cancelar preparación** (deja la cuenta como estaba). Mientras haya pagos o un plan activo no se puede modificar la cuenta ni preparar otro reparto.

### 6.7 Historial y recuperación

- **Historial** muestra todo lo ocurrido en la cuenta en lenguaje normal: traslados, descuentos, fecha, empleado y datos clave. Solo lectura.
- En **TPV → Recuperación** (solo encargados) ves las cuentas canceladas con su mesa y total. Elige una mesa libre y pulsa **Recuperar**: la cuenta se reabre con su historial intacto. Verás "Cuenta recuperada".

---

## 7. Caja

En **TPV → Caja** gestionas el dinero físico. Solo ves la caja principal y su estado.

**Abrir caja** (cuando no hay sesión abierta):

1. Escribe el **Fondo inicial en céntimos** (el cambio con el que empiezas).
2. Elige el **Encargado** e introduce su **PIN** (tiene que ser fresco, en ese momento).
3. Pulsa **Abrir caja**. Verás "Caja abierta".

**Cerrar caja** (cuando hay sesión abierta desde tal hora):

1. Cuenta el efectivo y escribe el **Efectivo contado en céntimos**.
2. ID del encargado + PIN fresco.
3. Pulsa **Cerrar y arquear**. La aplicación calcula lo esperado (fondo + ventas en efectivo − reembolsos + ingresos − retiradas), lo compara con lo contado y guarda el arqueo con la **diferencia**. Verás "Caja cerrada".

Los cobros en efectivo abren el cajón solos. También puedes abrirlo a mano desde **Integraciones → Hardware → Abrir cajón** (con ID + PIN). Todas las aperturas quedan registradas, y si el cajón falla, el cobro **no** se bloquea: se anota el error y listo.

---

## 8. Cocina (KDS)

La pantalla de cocina es un panel oscuro pensado para una pantalla fija. Muestra los tickets activos ordenados por antigüedad: a lo grande el destino (**Mesa T1**, **TakeAway**…), el canal y la estación, un **temporizador** que cuenta desde que entró la comanda, las cantidades y productos, el formato, los extras y las notas en naranja. Arriba puedes filtrar por estación (**Todas**, **Barra**, **Cocina**…). Si no hay nada pendiente verás "No hay comandas pendientes". La pantalla se actualiza sola cada pocos segundos y muestra si está **En línea**.

**El cocinero se identifica con su ID de empleado y su PIN de cocina.** Los estados de cada preparación avanzan así: **En cola → En preparación → Listo → Servido**. También se puede devolver de Listo a En preparación, o cancelar. Todo queda registrado con tiempos (cuándo empezó, cuándo estuvo listo…).

Si en el TPV anulan algo ya confirmado, en cocina aparece la **cancelación pendiente** con la cantidad y el motivo, y el cocinero confirma que la ha visto (**reconocer** la cancelación).

En **Cocina → Gestionar** (solo administradores del restaurante) se crean las **estaciones** (Barra, Cocina, Postres…): cada una tiene nombre, si está activa y a partir de cuántos segundos avisa de retraso. Para decidir qué va a cada estación, se indica en la **ficha del producto** (capítulo 9). Lo que no tenga estación aparece en la cola **Sin asignar**: visible, nunca perdido.

---

## 9. Carta

En **Carta** ves todos los productos en una tabla: foto, nombre, precio, alérgenos, canales (Local, Take Away, Delivery) y estado. Arriba tienes **buscador**, filtros por categoría, estado, disponibilidad y canal, y tres pestañas: **Productos**, **Categorías** y **Modificadores**.

Cualquiera del restaurante puede **ver** la carta. Solo los **administradores** pueden crear, editar, archivar o cambiar disponibilidades.

### 9.1 Categorías

**Categorías → Nueva categoría**: nombre (obligatorio), descripción, si está **activa**, en qué **canales** se vende (Take Away y Delivery solo salen si el restaurante los tiene activados) y una **imagen** (JPG, PNG o WebP de hasta 5 MB).

En la lista puedes **subir/bajar** cada categoría para ordenar la carta y **editarla**. Para retirar una categoría sin borrarla, desmárcala como activa. No se puede archivar una categoría que aún tenga productos: primero mueve o archiva sus productos.

### 9.2 Productos

**Nuevo producto** (o **Editar** en cualquier fila):

- **Lo que ofreces**: nombre, nombre corto (el que sale grande en el TPV), categoría, descripción, si está **activo**, si está **disponible ahora** y si quieres **gestionar su stock**.
- **Venta y coste**: precio de venta (lo escribes normal: 9,50), coste interno (para tu margen) e **IVA** (si lo dejas vacío, hereda el IVA general del restaurante).
- **Dónde se vende**: Local, Take Away y Delivery. Recuerda: si el canal está apagado en Configuración, el producto no se vende por ahí aunque lo marques.
- **Alérgenos**: marca solo los declarados (gluten, leche, huevo…).
- **Imagen**: la foto del producto.
- **Estación de cocina**: a qué estación va su comanda (o Sin asignar).
- **Precio manual**: si marcas "Permitir precio manual autorizado", un encargado podrá cambiarle el precio en el TPV.

En la lista, el botón **Disponible / No disponible** retira un producto de la venta al instante sin borrarlo (útil para "se acabó el arroz").

### 9.3 Formatos: Tapa, Media, Ración

Dentro de **Editar producto**, la tarjeta **Formatos y precios** sirve para vender el mismo producto en varios tamaños con **precio propio cada uno** (la Tapa a 5 €, la Ración a 15 €). Si no creas formatos, el producto se vende a su **precio base**.

Al crear un formato indicas nombre, precio, coste opcional, si está activo y si es el **predeterminado** (el que sale marcado por defecto). Puedes reordenarlos con ↑ ↓. Hay reglas que te protegen: no puedes dejar un producto sin formatos, ni desactivar o archivar el predeterminado sin elegir otro antes.

### 9.4 Modificadores: las preguntas del producto

En la pestaña **Modificadores** creas **grupos reutilizables**. Un grupo es una pregunta y sus **opciones** son las respuestas:

- Grupo **Punto de la carne** (obligatorio, elegir 1): Poco hecho, Al punto, Muy hecho.
- Grupo **Extras** (opcional, hasta 3, con cantidades): Queso extra (+1,50 €), Bacon (+2,00 €).

Al crear un grupo defines: nombre, ayuda, **mínimo** (0 = opcional), **máximo** (1 = única, vacío = sin límite), si permite **cantidades** (pedir 2 quesos) y si está activo. Cada opción tiene nombre, **suplemento** (0,00 si no suma), cantidad máxima e indicaciones para cocina (normal, destacar, servir aparte).

**Importante**: los grupos son **compartidos**. Si un grupo está en 5 productos y cambias una opción, cambia en los 5 (verás el aviso amarillo "Grupo compartido"). Para personalizarlo solo para un producto, usa **Duplicar**: crea una copia inactiva solo para ese producto.

Para asociar grupos a un producto, entra en **Editar producto → Modificadores**: verás los asociados (con ↑ ↓ para ordenarlos y **Quitar** para desasociar) y un desplegable para **añadir un grupo existente**, además del botón **Crear grupo** (te devuelve al producto con el grupo ya asociado).

---

## 10. Restaurante: plano, zonas, mesas y QR

### 10.1 El plano (lo que ve el equipo)

En **Restaurante** ves las zonas con sus mesas. Cada mesa dice si está **Ocupada** (con el total y el camarero, y al pulsar entras en su cuenta del TPV) o **Libre** (con sus plazas, y al pulsar abres cuenta). Arriba puedes filtrar por zona. Es la misma información que el TPV pero en vista de sala.

### 10.2 Administrar zonas y mesas (solo administradores)

En **Restaurante → Administrar** tienes dos columnas:

**Zonas** (derecha): formulario **Nueva zona** (nombre, descripción, activa) y, por cada zona, subir/bajar orden, **Imprimir QRs** y **Archivar zona** (no deja si aún tiene mesas).

**Mesas** (izquierda): buscador y filtros por zona y estado. Por cada mesa puedes desplegar **Editar mesa y QR**:

- **Guardar cambios**: nombre, capacidad (plazas) y si está activa.
- **Mover**: cambiarla de zona (conserva su QR).
- **Activar / Desactivar QR** y **Regenerar QR** (el anterior deja de valer; se conserva al renombrar o mover).
- **Ver QR**: muestra el código grande.
- **Hoja de zona**: página lista para **imprimir** con los QR grandes de toda la zona (nombre de mesa y zona + "Escanea para acceder").

Las mesas y zonas nuevas se crean desde el equipo técnico o con creación en lote; en esta pantalla editas, mueves y gestionas QR de lo ya creado.

### 10.3 Qué hace el cliente con el QR

El cliente escanea el QR de su mesa con el móvil y se abre la **carta de sala**, sin instalar nada ni iniciar sesión: ve la cabecera del restaurante y "Estás pidiendo desde Mesa X", navega por categorías, abre cada producto (**Ver y añadir**), elige formato y extras, lo **añade al carrito**, revisa su carrito (**Tu carrito → Continuar**) y **envía el pedido** indicando nombre y teléfono (y hora deseada si el local lo permite). El pedido cae en la bandeja de **Pedidos** pendiente de aceptar y, al aceptarlo, viaja a cocina. Si el QR está desactivado o el restaurante inactivo, el enlace deja de funcionar.

---

## 11. Personal: empleados, PIN y fichajes

Los **empleados** son las identidades de trabajo del restaurante. No necesitan tener cuenta para entrar en la aplicación: ANA puede tener PIN y fichar sin saber lo que es un email. Si quieres, puedes **vincularle una cuenta** (solo sirve para saber quién es quién; el acceso lo da la pertenencia al restaurante, nunca el vínculo).

El menú de Personal tiene tres pestañas: **Control horario**, **Empleados** y **Terminal de fichaje**. Cualquiera puede ver y fichar; solo los administradores crean, editan, corrigen y añaden intervalos.

### 11.1 Empleados

En **Empleados** ves tarjetas con el **nombre operativo** (el grande del TPV), nombre y apellidos, funciones e insignias (**Inactivo**, **Sin PIN**, **Trabajando**…). Con **Ficha** ves sus datos y su **historial de jornadas**; con **Editar** (administradores) lo modificas.

**Nuevo empleado** (administradores):

1. Nombre, apellidos y **nombre operativo** (obligatorio: es el que sale grande en el TPV).
2. Teléfono y email opcional, y cuenta vinculada si procede.
3. **Funciones operativas** (mínimo una): Encargado, Camarero, Cocina, Reparto.
4. **PIN operativo** de 4 a 8 cifras. No se vuelve a mostrar nunca: solo se puede cambiar o retirar.
5. Si está **activo**.

Reglas del PIN: único por restaurante entre empleados activos (si está cogido te avisa), los inactivos no pueden usarlo, no puedes desactivar a alguien con jornada abierta, y al reactivar a alguien que tenía PIN hay que ponerle uno nuevo. Para quitarlo, marca **Retirar PIN operativo**.

### 11.2 Fichajes

**Terminal de fichaje**: el empleado toca su nombre, escribe su **PIN** y pulsa **Continuar**. Después verá un botón grande: **Iniciar jornada** si está fuera, o **Finalizar jornada** ("Trabajando desde las HH:MM") si está dentro. Al pulsar, queda registrado con la hora del restaurante. Con **Cambiar empleado** se pasa el terminal al siguiente. Se puede fichar varias veces al día y de noche (jornadas abiertas y nocturnas funcionan).

**Control horario**: la lista de lo ocurrido cada día, con filtros por día y empleado. Los administradores pueden **Añadir intervalo manual** (empleado, entrada, salida, motivo: "Olvidó fichar") y **Corregir** cualquier jornada indicando la entrada y salida correctas y el **motivo**. Las correcciones son inmutables y guardan quién las hizo: en la ficha verás "Corregida" y el número de correcciones auditadas.

---

## 12. Reservas

La pestaña **Reservas** existe en el menú pero el módulo aún no está construido: al entrar verás que estará disponible próximamente. No hay nada que configurar aquí.

---

## 13. Analítica: ventas, facturas, clientes, stock y auditoría

La barra superior tiene: **Panel, Ventas, Facturas, Clientes, Stock, Auditoría**. Cualquiera puede consultar; crear facturas y tocar el stock es de administradores.

### 13.1 Panel

Elige el período (**Hoy, Ayer, Últimos 7 días, Este mes** o un rango de fechas) y verás: **facturación** (con lo reembolsado aparte y % respecto al período anterior), **número de ventas y ticket medio**, **comensales**, **descuentos y anulaciones**, tabla de **ventas por canal**, tabla de **métodos de pago** (los pagos mixtos reparten sin duplicar), **más vendidos** y **más facturación** (top 10), ventas **por categoría** y **por empleado** (métrica operativa, no ranking), **margen bruto estimado** (ingresos menos coste de producto; no es beneficio neto), datos de **cocina** (tiempos medios) y gráfico de **ventas por día**. Debajo tienes los botones de **exportar a CSV** (ventas, productos, pagos).

### 13.2 Ventas y tickets

En **Ventas** filtra por fechas, canal, camarero, método de pago o busca por mesa, cliente o número. Cada fila enlaza al **detalle de la venta**: líneas con su snapshot histórico (no editable), subtotal, descuento, total, desglose de **IVA**, pagos, contexto (mesa, comensales, cliente, camarero, hora de cobro) y documentos. Con **Ver ticket** abres el ticket en formato estrecho, listo para **Imprimir**.

### 13.3 Facturas

Desde el detalle de una venta cobrada, el bloque **Crear factura** (administradores) te pide el cliente: o su **ID** si ya existe, o sus **datos fiscales** (nombre, NIF, razón social, dirección…). Al pulsar **Emitir factura** se genera el documento y vas a verlo. En **Facturas** está la lista (número, fecha, cliente, venta, total) y cada **factura** en formato A4 imprimible, con emisor, destinatario, conceptos, bases y cuotas de IVA, total y, si aplica, el **QR tributario verificable**. Las facturas son **inmutables**: lo emitido, emitido queda (ante un error se hará rectificativa).

### 13.4 Clientes

En **Clientes** busca por nombre, teléfono, email o NIF. Cada ficha muestra sus **visitas**, su **total gastado histórico**, su **ticket medio**, si tiene **cuenta abierta ahora mismo**, su historial de visitas y sus facturas. **Nuevo cliente** pide nombre, teléfono, email, NIF (solo si va a facturar) y dirección fiscal. El NIF y el teléfono no se pueden repetir entre clientes. La ficha completa añade si es empresa, razón social y notas internas. Cambiar los datos **no** altera facturas ya emitidas (usan su snapshot).

Para asociar un cliente a una cuenta abierta se usa su **teléfono** desde el TPV o desde ventas.

### 13.5 Stock

En **Stock** filtra por **Todos, Controlados, Stock bajo, Agotados**. Cada producto muestra su stock, su mínimo y su estado (**OK**, **Stock bajo**, **Agotado**, **Sin control**). En la ficha del producto verás el **historial de movimientos** (entradas, ventas, devoluciones, ajustes, con fecha, empleado y motivo) y, si eres administrador:

- **Control de stock**: activar el control, fijar la cantidad actual y el mínimo.
- **Añadir stock**: cantidad y motivo ("Reposición").
- **Ajustar a conteo físico**: la cantidad contada y el motivo (obligatorio). La diferencia queda registrada como ajuste.

La cifra nunca se edita a mano: cada cambio es un movimiento explicado.

### 13.6 Auditoría

En **Auditoría** ves la trazabilidad en lenguaje normal: quién (**actor**), qué hizo (**acción**), en qué **módulo** (Ventas, Caja, Stock, Personal…), cuándo, con qué detalle y con enlace a la venta si la hay. Puedes filtrar por fechas, empleado y módulo. No se puede editar ni borrar.

---

## 14. Integraciones

En **Integraciones** está todo lo que conecta el restaurante con el exterior: hardware, pagos online, fiscalidad, contabilidad, webhooks, cupones y fidelización. Todo es de **administradores**. Si un servicio externo falla, lo básico (TPV y KDS) sigue funcionando.

### 14.1 Impresoras y hardware

En **Gestionar hardware**:

- **Nueva impresora**: nombre (por ejemplo "Cocina caliente"), ancho de papel (80/58), conexión (red, USB, serie), para qué se usa (**cocina, barra, ticket, factura, caja**) y si **abre el cajón**. No hay "impresora por defecto": cada uso busca su impresora, y cada **estación de cocina** tiene sus **impresoras destino** (se asignan aquí: eliges estación y marcas sus impresoras).
- Por impresora: **Editar**, activar/desactivar e **Imprimir prueba**.
- **Cola de trabajos** (últimos 30): cada trabajo con su tipo, impresora, estado (**pendiente, impreso, error**), intentos y error si lo hay. Si falla: **Reintentar**. Para volver a imprimir algo ya impreso: **Reimprimir** (pide ID + PIN de empleado y queda auditado). También puedes mandar el **ticket de una venta pagada**.
- **Conectores locales**: para vincular el programa que habla con las impresoras físicas. Al crear uno verás **una sola vez** un código de vinculación (caduca en 15 minutos) y el token: cópialo en ese momento.
- **Aperturas de cajón**: lista de automáticas (por cobro en efectivo) y manuales, con empleado, fecha y estado.

### 14.2 Stripe y pagos online

En la tarjeta **Stripe** del centro:

- **Conectar**: elige **modo** (Pruebas o Producción) y **proveedor** (Stripe real o Simulado para pruebas), pega la **clave secreta** y el **secreto del webhook**. Las claves **nunca se vuelven a mostrar**: se pueden reemplazar o borrar, pero no recuperar. La página te indica la URL de webhook que debes configurar en Stripe.
- **Probar conexión** comprueba que todo responde.
- **Desconectar** borra las claves y deja el histórico intacto.
- En **Métodos por canal** marcas qué se acepta en Sala, Take Away y Delivery: Efectivo, Tarjeta, Online. El pago online exige Stripe conectado.

El cliente paga desde su página de seguimiento (**Pagar online**): con Stripe real sale a la página de Stripe y vuelve solo; con el proveedor simulado verá dos botones, **Pagar** y **Simular fallo** (sin tarjetas de verdad). Si hay que devolver un pago online, se hace desde **Pedidos** con encargado (capítulo 4).

### 14.3 Fiscalidad (VERI*FACTU)

En **Panel fiscal** ves los registros (**altas** y **anulaciones** de tickets y facturas) con sus contadores: pendientes, enviados, aceptados, con incidencias, rechazados y errores. Cada registro muestra serie y número, total, estado, entorno (**pruebas** o **producción**, que nunca se mezclan), NIF, fecha e intentos. En el **detalle** verás la huella (hash), el encadenado con el registro anterior, la respuesta, los **intentos** y el **QR tributario** (el que sale impreso en la factura y se verifica oficialmente; no confundir con el QR de las mesas).

- **Configurar** (tarjeta Fiscalidad): entorno, **modo de simulación** (acepta todo, rechaza el primer envío para probar reintentos, o simula el servicio caído), **NIF** y **razón social**. Estamos en período de pruebas (la obligación general llega en 2027), así que no se afirma cumplimiento legal.
- **Reintentar envío** en los pendientes, rechazados o con error.
- **Anular fiscalmente** un alta con su motivo: genera el registro de anulación y conserva el original.

### 14.4 Contabilidad

En la tarjeta **Contabilidad**: el **mapeo de cuentas** (ventas, IVA, clientes, diario, notas) y los botones de **exportación** por fechas: ventas, facturas, pagos y caja. Los CSV usan punto y coma, abren bien en Excel y **cuadran con Analítica**. (No se anuncia compatibilidad con ningún programa concreto hasta validarlo.)

### 14.5 Webhooks

En **Gestionar endpoints** das de alta URLs a las que Marchando avisará de eventos (**pedido creado/aceptado/completado, pago completado, factura creada…**). Al crear uno eliges la URL (https) y los eventos (vacío = todos), y recibes un **secreto de firma que solo se muestra una vez**: sirve para comprobar que los avisos son auténticos. Puedes **reintentar pendientes** y **eliminar** endpoints. Un endpoint caído jamás bloquea el TPV.

### 14.6 Cupones

En **Cupones**: lista con código, tipo (**X %** o **Y €**), estado, usos y canjes, y botón **Activar/Desactivar**. **Crear cupón** pide: **código** (por ejemplo VERANO10, en mayúsculas, único), nombre interno, tipo y valor, fechas de vigencia, **pedido mínimo**, **usos máximos**, **un uso por cliente** y **canales** donde vale. Se aplican en el TPV (con PIN) y en el checkout online de Take Away/Delivery. No se acumulan con otros descuentos.

### 14.7 Fidelización

En **Fidelización**: "compra X y te regalamos Y, solo para clientes identificados". **Crear programa** pide nombre (por ejemplo "10.º café gratis"), el **objetivo** (un producto o categoría y cuántos sellos), y la **recompensa** (un producto). Los sellos se acumulan solos al pagar con cliente identificado (una cuenta con división genera un solo progreso) y se canjean desde el TPV con PIN.

---

## 15. Configuración del restaurante

En **Configuración** hay tres bloques independientes, cada uno con su botón de guardar. Verlos puede cualquiera; guardarlos solo los administradores.

### 15.1 Datos del restaurante

Nombre comercial, tipo de establecimiento, email, teléfonos, descripción, web y redes sociales; dirección completa, **zona horaria** y coordenadas; **razón social, NIF, IVA por defecto** (el que heredan los productos sin IVA propio) y el **texto del ticket** (pie personalizado).

### 15.2 Canales

- **Pedidos públicos**: carta pública visible o no, pedidos QR sí o no, y si la aceptación es **manual** (alguien pulsa Aceptar) o **automática** para QR, Take Away y Delivery.
- **Sala**: activada o no.
- **Take Away**: activado o no, tiempo estimado de preparación y si usa el horario general o el suyo propio.
- **Delivery**: activado o no, radio máximo en km, coste de reparto, pedido mínimo, tiempo estimado y horario general o propio.

### 15.3 Horarios

Tres calendarios: **general**, **Take Away** y **Delivery** (los dos últimos solo aparecen si están activos y no heredan el general). Por cada día (lunes a domingo): **abierto o cerrado**, y tantas **franjas** como quieras (apertura-cierre, con botón de eliminar y de añadir). Hay un botón de **copiar horario** para replicar un día en otros. Si una franja "termina antes de empezar" (22:00 a 02:00) se entiende como **nocturna** y cruza la medianoche. Todo se evalúa en la zona horaria del restaurante.

---

## 16. Lo que ve el cliente (canal público)

Sin iniciar sesión, el cliente tiene tres puertas:

- **QR de mesa** (`/t/…`): carta de sala, carrito, checkout y envío a cocina (capítulo 10.3).
- **Take Away y Delivery** (`/public/nombre-local/takeaway` o `/delivery`): igual que el QR pero sin mesa. En el checkout puede usar **código promocional**, y en Delivery tiene que escribir la **dirección** (y opcionalmente su posición). Puede pedir **para ahora** o **elegir hora**.
- **Seguimiento** (`/order-tracking/…`): con el enlace que recibe al pedir, ve el estado (**recibido, aceptado, rechazado con motivo**), si está **pagado online** o si el pago **falló y puede reintentarlo**, y el botón **Pagar online** cuando procede. Puede cerrar la página: su pedido sigue guardado.

---

## 17. Mantenimiento y salud del sistema

- **Copia de seguridad**: el sistema genera una copia diaria automática de madrugada de la base de datos con su manifiesto de verificación, y conserva las últimas 14. Pide a tu técnico que compruebe de vez en cuando que el programador del servidor la está ejecutando.
- **Versión**: al pie del menú lateral verás la versión instalada (por ejemplo "Marchando v15.0.0-pilot").
- **Incidencias**: el Inicio y el Resumen de Administración te avisan de lo importante (fiscal con errores, impresión en error, pedidos sin aceptar, stock bajo). Acostúmbrate a mirarlos al empezar el día.

---

## 18. Problemas frecuentes

**"No tienes permisos" / error 403.** Esa cuenta no pertenece al restaurante o no tiene el rol necesario. Como propietario, entra desde Administración y revisa sus accesos. Si te pasa a ti en un restaurante, entra por **Administración → Entrar**: tu acceso global no falla.

**Un usuario no puede entrar.** Comprueba en Administración → Usuarios que esté **Activo** y que tenga acceso al restaurante con el rol correcto. Recuerda: desactivado = no entra, pero su histórico sigue.

**El TPV no deja añadir productos.** Falta identificar al camarero (ID + PIN) o la cuenta tiene una ronda sin confirmar, o el TPV está bloqueado: identifica al camarero de nuevo.

**No se puede cobrar.** O hay una ronda pendiente de confirmar, o no hay sesión de caja abierta: abre caja con un encargado (capítulo 7).

**La comanda no llega a cocina.** Revisa que el producto tenga estación asignada (si no, cae en "Sin asignar", que también se ve en el KDS) y que haya estaciones activas. El KDS indica si está en línea.

**El QR de una mesa no funciona.** Revisa en Restaurante → Administrar que la mesa esté activa, su QR activo y la carta pública visible. Si cambió el QR impreso, usa **Regenerar QR** e imprime la hoja nueva.

**Un plato no sale online pero sí en sala.** La disponibilidad efectiva exige tres cosas a la vez: restaurante y canal activos, **categoría** activa para ese canal y **producto** activo y disponible para ese canal. Revisa las tres.

**Un pago online quedó a medias.** Desde el seguimiento del cliente o desde Pedidos puedes reembolsar con encargado; el estado se reconcilia solo al volver de Stripe.

**Dudas con un cobro o un descuento.** Ve a **Analítica → Auditoría** y filtra por empleado, módulo y fecha: verás quién hizo qué, cuándo y por qué.

---

*Fin del manual. Si algo de lo descrito no coincide con tu pantalla, anota qué ves y en qué restaurante: será la pista para mejorarlo.*
