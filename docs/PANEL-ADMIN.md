# Panel de administración — especificación funcional

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Naturaleza del doc:** en el origen era la especificación funcional PREVIA a la
> implementación (su plan de implementación por sub-fases es histórico en el repo origen).
> El panel está **implementado en Filament** — `app/Filament/`: Resources (`Orders`, `Slots`,
> `SlotTemplates`, `Zones`, `Attractions`, `Catalog`, `RateTypes`, `Seasons`, `SpecialDates`,
> `Users`, `Roles`, `Pages`, `Faqs`, `ParkRules`, `LandingServices`, `Offers`, `AuditLogs`) y
> Pages (`CalendarPage`, `CreateManualOrderPage`, `WeeklySchedule`, `Settings`, `Maintenance`,
> `Dashboard`). Los ítems `[propuesta]` vienen del diseño original: comprueba en código si se
> implementaron tal cual antes de asumirlos.

El panel del **administrador del negocio**: su centro de mando del día a día. Es un
administrador **operativo**: además de configurar, puede **crear y gestionar** reservas y
entradas. Incluye **empleados con permisos limitados**.

**Alcance (decisiones funcionales heredadas):**
- Las **reservas de cumpleaños/eventos** *(vocabulario del sector origen; su generalización
  se decide en `00-REFACTOR.md` Fase 1/2)* se hacen **siempre desde el sistema** (web o panel).
- Las **ventas de entrada presenciales** pueden hacerse directamente en caja física, **sin
  pasar por el panel** → §2.3 y `OPERATIVA-SECTOR-ORIGEN.md`.
- **TODO el que entra debe estar registrado** (= aceptó términos y waiver). Se comprueba en
  puerta con la **validación de registro** (§2.5).

---

## 1. Quién accede

| Rol | Acceso |
|---|---|
| **Administrador (dueño)** | Total: operación, gestión y configuración. |
| **Empleado** | Limitado: ver calendario/reservas/entradas, crear y gestionar reservas/entradas (§5). |

---

## 1.bis Por dónde se llega a cada cosa (la FORMA del panel) ⭐

> `[DECIDIDO owner, 2026-08-28]` · `DECISIONES #223` · detalle y porqué:
> **`specs/panel-navegacion.md`** (léela antes de mover una entrada de sitio).

Hasta esa fecha el menú tenía **24 entradas en 6 grupos**, y solo **4** eran de la §2 de este
doc: el 83 % era configuración de puesta en marcha compitiendo por el ojo del empleado. Ahora
la barra lateral es **plana y solo tiene los sitios del día a día**:

```
Hoy · Calendario · Pedidos · Clientes · Puerta          ← el menú, sin grupos
[ marca ]        [ 🔍 buscador, centrado ]   [⊕ Crear pedido] ( avatar ▾ )   ← la barra superior
                                                 avatar ▾ → «Ajustes» · tema · «Español» · «中文» · «Salir»
```

- Las **19 pantallas de §3 y §4** viven en **`/admin/ajustes`**, en tarjetas por área y cada una
  con una línea de qué hace. Se entra **solo** por el menú del avatar.
- ▶ **`#461` cambió el ARMAZÓN** (`specs/auditoria-panel-admin.md` es el porqué medido): la marca
  lleva el logotipo **por tema** y debajo «Administración»; el **buscador va centrado** y dice qué
  encuentra; el **idioma salió del topbar al menú del avatar** (es preferencia personal, y en
  «Ajustes» un empleado no la alcanzaría); «Crear pedido» pasa a la **escala de acción primaria**
  (44 px); y el **menú lateral es fijo y estrecho** (6rem, icono grande con el rótulo debajo, sin
  flecha de plegar). ⚠️ Con el sidebar en 6rem el rótulo del menú **no puede pasar de 13
  caracteres** o se parte: lo vigila `PanelShellTest`.
- **«Crear pedido» es una acción, no un sitio**: por eso está arriba y no en el menú.
  «Calendario» se retiró de la barra superior — era el único enlace duplicado del panel.
  ▶ **Y detrás hay un ASISTENTE de siete pasos** (`#462`/`#463`, `specs/asistente-crear-pedido.md`):
  Cliente · Producto · Cuándo · Datos · Extras · Carrito · Pago, con auto-avance al elegir y **el
  salto de todo paso que no pregunta nada**. El producto se elige en **tarjetas agrupadas** por tipo
  —«Entradas» y «Packs y celebraciones»—, que es lo que cierra el crítico C1 de la auditoría: con el
  desplegable plano anterior se vendió un cumpleaños como diez entradas sueltas.
- **«Usuarios» es ahora «Clientes»**, y el EQUIPO es la otra pestaña de esa misma pantalla,
  a la que se llega desde Ajustes (§3 lo describe igual: la ficha y sus acciones no cambian).
- El **empleado** ve 4 entradas (no «Clientes»: exige `users.manage`) y **no ve «Ajustes»**.

⚠️ **Ocultar no es autorizar.** Las 19 conservan intacto su `canViewAny()`/`canAccess()`. Para
quitarle una pantalla a alguien se le quita el permiso, **nunca** la entrada del menú.

**Y hay BUSCADOR** (barra superior, `CTRL+K` / `⌘K`, `DECISIONES #224`): encuentra pedidos,
clientes, productos y demás registros — **y también PANTALLAS**, por su nombre o por lo que
hacen («precio» encuentra Tarifas). Es lo que hace barato tener 19 escondidas.
⚠️ **Un empleado busca PEDIDOS, no clientes** (`[DECIDIDO owner]`): para comprobar a una
persona está la pantalla de Puerta (§2.5), que es la que lleva límite y auditoría.

⚠️ **Ninguna pantalla puede quedar huérfana.** Un recurso nuevo que no entre ni en el menú ni en
Ajustes sería inalcanzable salvo tecleando su URL. `AdminNavigationTest` lo impide: toda pantalla
registrada tiene que estar en uno de los tres sitios declarados, o la suite se pone roja.

---

## 2. 🔵 Operación diaria (lo que más se usa)

### 2.1 Calendario (centro de mando)
- Vista mes / semana / día.
- Muestra **reservas de eventos** y **entradas (sesiones/aforo)**, diferenciadas por color.
- **Una sola vista con filtros y colores** (decidido): botón para ver solo reservas, solo
  entradas o ambas; cada tipo con su color.
- Clic en día/franja → detalle: quién viene, ocupación, plazas libres.

### 2.2 Listas rápidas
- Reservas y entradas de **hoy** y **próximas**.

### 2.3 Crear en back-office
- **Crear reserva** de evento manualmente (datos del cliente, pack, fecha, sala, invitados).
- **Crear entrada** manualmente (tipo, franja, cantidad).
- Respeta el **aforo** igual que la web.
- Cobro de altas manuales (decidido): el admin las **marca como pagadas** (efectivo/datáfono)
  o las deja **pendientes**. **No se generan enlaces de pago.**
- **Menores a cargo** (Fase 6 · C, tanda 5, `specs/menores-a-cargo.md` §9.10): al añadir una
  **entrada** para un cliente con menores declarados, bajo la cantidad aparece «¿Para quién son estas
  entradas?» con una casilla por menor —los que no tienen la exención firmada y vigente (en modo
  interno) o ya son adultos ese día van deshabilitados con su motivo—; el resto de unidades son
  adultos. Se comprueba ANTES de cobrar (un menor que dejó de ser asignable no crea ni cobra nada) y se
  escribe DESPUÉS del cobro. ▶ En la **ficha del pedido**, cada entrada dice «Para: Lucas (9 años ·
  exención ✓)» y el icono de personas de su fila abre **«Asignar menores»** (mismo permiso que editar
  la línea, `orders.edit_item`; solo en entradas de pedidos que admiten cambios): marcar y desmarcar
  deja rastro en la auditoría con el operador y sin el nombre. Un menor retirado de la cuenta del
  cliente conserva su entrada y se enseña marcado como tal.

### 2.4 Verificación en puerta y canje por pulsera
- Buscar el pedido (código, email o QR) → ver **qué entrada es** y el **color de pulsera**
  (zona) correspondiente → entregar/activar la pulsera y **marcar la entrada como canjeada**
  (no reutilizable). Detalle: `OPERATIVA-SECTOR-ORIGEN.md`.
- Ver **ocupación (aforo)** por franja en tiempo real.

### 2.5 Validar registro / waiver en puerta (privacidad por diseño) ⭐

> 📱 **Es un KIOSCO de tablet** (`DECISIONES #232`, `specs/panel-navegacion.md` §8): tablet propia,
> fija en un soporte y en horizontal. El buscador se queda pegado arriba —atender al siguiente es
> tocar el campo que ya está delante, y el lector de QR escribe ahí—, todo control mide 44 px y la
> ficha entera con «Registrar visita» se ve sin desplazar. ⚠️ **«Nueva búsqueda» no se puede
> ocultar**: además de vaciar el campo, quita de la pantalla la ficha del cliente anterior.

Todo el que entra debe estar registrado (= aceptó términos y waiver). En puerta se valida
buscando por **email o teléfono**:
- Resultado: **solo** un estado → ✅ «Registrado · waiver aceptado (fecha)» o ❌ «No registrado».
- **No se muestran datos personales** (ni nombre completo, ni dirección, ni historial). Solo
  el estado. *(RGPD: minimización de datos.)*
- **Fase 6 · waiver** (`specs/waiver-probatorio.md`): el ajuste «Gestión del waiver» (Ajustes →
  Puerta) tiene TRES modos. En **interno** la puerta lee el **registro firmado**, no el sello, y si la
  firma es de una **versión anterior** del texto lo SEÑALA en ámbar pero deja pasar — la re-firma se
  pide en la siguiente compra o inicio de sesión, nunca en el mostrador. El texto se publica como
  versión firmable desde «Páginas → waiver → Publicar versión firmable»: es **irreversible** (una
  versión publicada no se edita ni se borra) y un borrador con marcador —`[PENDIENTE…]`,
  `[PENDING…]` o `[À COMPLÉTER…]`, en cualquier idioma— se rechaza; si el texto menciona «borrador»,
  «draft» o «brouillon», el modal de confirmación lo AVISA antes de publicar (`#174`).
  ▶ En la ficha del PEDIDO, el badge «Waiver» sigue la misma regla que la puerta: por el registro
  firmado (no el sello), «versión anterior» señalada, y oculto si el modo es «desactivado».
  ▶ **El alta MANUAL** («Crear pedido manual → Registrar al cliente», `#178`): en modo interno con
  versión publicada, el modal enseña el **texto vigente** y una casilla —«Le he enseñado al cliente
  la exención vigente y declara que la acepta»—. **Sin marcarla no se registra ninguna firma**: el
  cliente firmará desde su cuenta y la puerta le pedirá la tablet mientras tanto. Con ella, la firma
  queda como **declarada por el operador** (y el PDF lo dice).
  ▶ **El registro probatorio** vive en la ficha del usuario como la acción «Registro del waiver»,
  con permiso PROPIO `waiver.view` (no lo tiene el staff por defecto): abrirla queda en la auditoría,
  lista las firmas (versión, canal, quién la declaró si fue en mostrador, integridad) y cada una
  tiene su **PDF** —compuesto del texto exacto que la persona aceptó, en su idioma, no del texto
  actual de la página— que también se audita. Funciona sobre cuentas ya anonimizadas: la firma lleva
  copia del nombre y el email de entonces. El **alta presencial** (pedido manual → cliente nuevo) en
  modo interno deja una firma **declarada por el operador**, y el PDF lo dice con todas las letras.
- **Fase 6 · subsistema A — el CARNÉ QR y la FICHA de puerta** (`specs/identidad-qr-puerta.md`, `#208`):
  cada cliente tiene un **carné QR** (viaja adjunto en el correo de confirmación y por `GET /me/card`;
  escanearlo NO autentica: identifica). El lector de mostrador teclea el código en el **mismo input**
  de esta pantalla. Quien tiene el permiso **`puerta.profile`** (el staff por defecto) ve, además del
  semáforo, la **ficha**: nombre del titular, exención, carné, **reservas de hoy** con lo pendiente de
  cobrar en puerta (el dinero sale del ledger), la ventana ±N días, los **menores a cargo como edad y
  estado de la exención — jamás el nombre —** y el botón **«Registrar visita»** (explícito, una vez por
  día: es lo que acredita la visita para JumpPoints). La búsqueda tecleada también abre la ficha, con su
  propio límite por hora (Ajustes → Puerta) y auditada aparte; la ficha **caduca sola en el servidor**
  (Ajustes → Puerta, 5 min) y se vela a los 60 s sin tocarla. Un carné rotado o revocado dice «Carné
  caducado — busca por email».
- Si la entrada se compró online, además se valida su **QR** (§2.4).
- Si la persona **no está registrada**, se registra en el momento: **desde su móvil**
  (QR/enlace en la entrada) **o en una tablet** del local (ambas opciones, decidido).

> Matiz de privacidad clave: esta búsqueda es **anónima** (solo devuelve ✓/✗ para cualquier
> persona). En cambio, al gestionar un **pedido concreto** (§2.6) el empleado sí ve el
> **nombre del comprador** — lo necesita para preparar la pulsera; es cliente propio del
> negocio, conforme a RGPD.

### 2.6 Preparación de entradas (tablero interno) ⭐
Refleja el flujo físico. Las entradas online pasan por estados que **solo ve el personal**
(el cliente no los ve):
- **Comprada** (pagada, pendiente de preparar)
- **Preparada** (pulsera lista, con nombre del cliente y color de su zona)
- **Canjeada** (entregada en puerta)
- *(Anulada / reembolsada)*

Tablero **filtrable por estado** para controlar qué falta por preparar y qué se entregó.
Acciones: **«Marcar preparada»** y **«Marcar canjeada»**.

> Contexto operativo del sector origen: el personal está siempre frente al ordenador
> (verifica registros, gestiona entradas/reservas, prepara pulseras). Lo único que NO pasa
> por el panel es la **venta presencial** (caja física externa, `OPERATIVA-SECTOR-ORIGEN.md`).

---

## 3. 🟢 Gestión (cada cierto tiempo)

- **Entradas:** crear/editar tipos de entrada, precios, activar/desactivar.
- **Reservas / eventos:** crear/editar packs (qué incluyen, precio, mín/máx invitados,
  señal o total), salas.
- **Franjas y aforo:** horario semanal, duración de franja, plazas por franja, días cerrados
  y excepciones.
- **Usuarios:** ver, buscar, **crear, editar y borrar**.
  - Crear: se envía email para que el usuario establezca su contraseña `[propuesta]`.
  - Borrar: se **anonimiza** para cumplir RGPD (se conservan facturas obligatorias) `[propuesta]`.
  - **Ficha del usuario** (`ViewUser`), acciones de cabecera, todas con el mismo patrón de defensa
    (permiso + solo clientes, nunca uno mismo, nunca anonimizada; re-check al confirmar; auditoría):
    roles (`access.manage`) · registro probatorio del waiver (`waiver.view`, cada apertura auditada) ·
    enviar enlace de contraseña (`users.manage`) · **rotar carné QR** (`users.manage`; el carné actual
    —el del correo y el impreso— deja de valer EN EL ACTO y el cliente ve el nuevo en «Mi carné» o en
    su próxima confirmación; NO cierra sesiones: para eso está anonimizar/bloquear,
    `specs/identidad-qr-puerta.md` §9.6 B·5) · anonimizar (`users.anonymize`).
  - **Sección «Cliente 360»** (`customers.insights`, permiso propio que el staff no lleva por defecto;
    `specs/analitica.md` §4.6, T4a): compras, vendido, cobrado y devuelto, primera y última compra, frecuencia
    y productos —siempre, desde los pedidos cobrados—; y, solo si el cliente consintió «análisis» y no se opuso,
    su primera fuente y campaña, las visitas antes de comprar y los contactos recibidos. Una cuenta anonimizada
    no enseña nada.
- **Pedidos y reembolsos:** ver compras, reembolsar, reenviar entradas.

---

## 4. 🟡 Configuración (puntual)

- **Datos del negocio e información fiscal:** nombre, razón social, NIF, dirección, datos de
  factura, contacto, redes.
- **Pagos (Redsys)** y **email**.
- **Idiomas y traducciones** (ES/EN/FR).
- **Tema visual:** colores, tipografías, logo (re-tematiza todo el sitio — white-label).
  - **Correos transaccionales** (decisión heredada del origen): replican el sistema visual de
    la web mediante el theme de mail heredado
    (`resources/views/vendor/mail/html/themes/brand.css` — renombrado en Fase 1;
    su renombrado está previsto en `00-REFACTOR.md` Fase 1, junto con
    `config('mail.markdown.theme')`). La personalización de emails desde el panel se acota a
    **SOLO COLORES** (paleta primaria/acento); los textos de cada plantilla viven en
    `lang/*/emails.php` (editarlos desde panel introduce riesgo legal/UX). Logo y datos del
    negocio salen de la configuración general (`settings`), que alimenta web y emails.
- **Textos legales** (aviso, privacidad, cookies, condiciones, waiver).
- **Informes y exportaciones** (ventas, asistencia, reservas).

### 4.1 La REJILLA de horarios reservables ⭐ (`DECISIONES #420`, `INVARIANTES AFORO-12`)

**Qué horas se pueden reservar no es código: son filas de `slot_templates`** (zona × día de la semana ×
hora de inicio). No existe ningún «paso» implícito — si no hay una plantilla a las 15:30, no hay 15:30.

▶ **Dónde:** avatar → **Ajustes** → **Horarios y aforo** → **Plantillas de franja** → botón **«Generar
plantillas»** (permiso `slots.manage`, solo admin).

▶ **Los valores con los que se configuró el parque** (una pasada por zona, aditiva):

| Campo | JUMP | KIDS | Cumpleaños |
|---|---|---|---|
| Días de la semana | los 7 | los 7 | los 7 |
| Primera franja | `10:00` | `10:00` | `10:00` |
| Cierre (la última franja termina a esta hora) | `21:30` | `21:30` | `21:30` |
| Duración (min) | **`60`** | `60` | `60` |
| Cada cuánto empieza una franja (min) | **`30`** | `30` | `30` |
| Aforo total / online | `20` | `20` | `200` |
| Reemplazar las existentes | apagado | apagado | apagado |
| Regenerar las franjas al terminar | apagado | apagado | **encendido** (solo en la última) |

❗❗❗ **La duración es 60 aunque los inicios vayan cada 30, y no es un gusto**: `slot.end_time` es lo que
lee `OrderItem::isFinishedInPractice()` para dar una reserva por TERMINADA. Con franjas de 30, una
entrada de 1 h comprada a las 15:30 se declararía terminada a las 16:00 — y de ahí cuelgan el post-form
en solo lectura, el cierre de los extras y la ventana del suplemento mixto.

⚠️ **El aforo de las franjas nuevas es el MISMO que el de las viejas, nunca la mitad**: cada franja
declara cuánta gente cabe **a la vez**, no una cuota a repartir entre las dos medias horas.

⚠️ **Entre dos fiestas no se reserva tiempo de montaje/limpieza** (`prep_before_min`/`prep_after_min` = 0
en los dos packs, `[DECIDIDO owner, 2026-09-06]`). Encenderlo con `prep 30/30` cuesta **−40 % de capacidad
el sábado** (15 fiestas → 9), medido: no se toca sin volver a decidirlo.

⚠️ El generador descarta solo lo que no cabe en el horario del día, así que verás plantillas (10:00,
10:30…) que de lunes a viernes no generan nada: el recinto abre a las 16:30. Es normal.

▶ **Para deshacerlo:** borrar las plantillas a las `:30` y pulsar **«Regenerar franjas»** en *Franjas*.
La poda borra las `:30` vacías y **cierra —no borra— las que tuvieran reservas**, así que no se pierde
ninguna venta. ⚠️ Una prueba acotada a unos días **dura hasta la madrugada**: el proceso automático
(`slots:generate-rolling`, `AFORO-03`) regenera el horizonte completo cada noche.

---

## 5. Permisos del empleado (decidido)

El empleado **puede**:
- Ver el calendario y las reservas/entradas.
- Crear reservas/entradas en back-office.
- Gestionar reservas existentes (confirmar, reprogramar, cancelar).
- **Corregir las fichas por invitado de una fiesta** (T3 de reservas mixtas, 2026-08-31, `#294`):
  pestaña «Invitados» del modal **Gestionar**, permiso propio `orders.edit_guest_data` (en `staff`
  por defecto, revocable por rol). Entra por la misma puerta que el post-form del cliente y el
  rastro dice que fue el parque (`specs/cumple-mixto.md` §23.3).
- **Bajar un pack por debajo de su mínimo de invitados** (T3 · D7): interruptor explícito en
  «Editar producto», permiso propio `orders.edit_item_below_minimum` (en `staff` por defecto),
  y la excepción queda en el historial del pedido. El máximo sigue mandando y la web sigue
  exigiendo el mínimo al vender.
- **Verificar en puerta:** validar registro/waiver (§2.5), buscar pedidos, entregar pulsera
  y marcar entradas como canjeadas (§2.4). La ficha de puerta y la hoja de sala enseñan desde la
  T3 **lo escrito** del suplemento de fiesta mixta (la diferencia por cabeza que antes se hacía
  de memoria).

El empleado **NO** puede: configuración, información fiscal, precios, ni crear/borrar usuarios.

---

## 6. Decisiones funcionales heredadas (cerradas en el origen)
1. Cobro de altas manuales → **marcar como pagado / pendiente** (efectivo/datáfono), sin
   enlaces de pago.
2. Permisos del empleado → §5 (incluye check-in en puerta).
3. Calendario → **una sola vista con filtros y colores**.
