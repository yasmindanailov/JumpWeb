# Sistema: post-formulario de datos por invitado (packs de cumpleaños)

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Sistema IMPLEMENTADO** (iteraciones 1–4 del diseño origen + copiar-enlace + rediseño de la
> página pública). Este doc es **referencia**, no plan. Ver también `MODELO-DATOS.md`,
> `FLUJOS.md` (flujo de reserva de eventos) y `PANEL-ADMIN.md`.

---

## 1. Qué resuelve

Una reserva de **cumpleaños** (producto `type=pack`) *(vocabulario del sector origen; su
generalización se decide en `00-REFACTOR.md` Fase 1/2)* necesita, además de los datos básicos
del evento, **los datos de cada niño/invitado**. En el sector origen: por invitado un grupo de
4 campos {nombre · alergia/intolerancia · observaciones · menú especial}, hasta ~20 niños.

Problema operativo: al reservar, el cliente **no sabe aún cuántos invitados habrá ni sus
datos**. Pedirlo todo en la compra satura el flujo. Solución: **formulario posterior
(«post-form»)**, separado de la compra:

1. El cliente reserva con los datos básicos.
2. Tras pagar, recibe **email** + aviso en el sidecart de confirmación + botón en «Mis pedidos»
   para rellenar el post-form cuando tenga la lista.
3. El post-form es **editable** cuantas veces haga falta hasta el día del evento (después,
   solo lectura — §6).
4. El empleado ve en el **calendario** si está relleno, puede **reenviar el email** o **copiar
   el enlace**, y la **hoja de reserva PDF** imprime todos los datos (puede ocupar 2 hojas A4).

### Contexto histórico relevante (patrones)
- No existe ningún estado «preparado» persistido que reutilizar: el origen **eliminó
  físicamente** `order_items.prepared_at/prepared_by`. El estado operativo (`active`/`finished`)
  es **derivado al vuelo** (`OrderItem::isFinishedInPractice()`,
  `app/Domain/Booking/Concerns/OrderOperativeStatus.php`).
- Patrón canónico de estado **persistido**: par `cancelled_at` + `cancelled_by` con método
  idempotente (`OrderItem::markCancelled()`).
- La caja «Preparado» del PDF (`reservation-slip.blade.php`) es un **checkbox manual de
  papel**, no un estado digital.

## 2. Decisiones de diseño (cerradas en el origen)

1. **Modelo de datos = columna JSON** (`order_items.guest_data`), no tabla relacional. Espejo
   del patrón `event_data`: sin FK, sin N+1, bloque entero (N ≤ ~20). *Condición que lo
   cambiaría a tabla `order_item_guests`*: necesitar consultas transversales («todas las
   alergias del sábado» para cocina). No implementado.
2. **Esquema por-invitado data-driven por pack**: `ticket_types.guest_fields` (JSON), espejo
   exacto de `event_fields`. El admin añade/quita/renombra columnas por pack en el panel.
   Seed por defecto: las 4 columnas del sector origen (`DEFAULT_GUEST_FIELDS`).
3. **Reparto de campos básicos**: nombre del homenajeado → en la **reserva** (`event_fields`
   normal); nº aproximado de adultos + observaciones generales → en el **post-form**, modelados
   como `event_fields` con atributo `stage` (§3.2).
4. **Regla «FORM OK»**: para cada uno de los N invitados actuales (`N =
   order_items.quantity`), las columnas `required` de `guest_fields` están rellenas. Estado
   **derivado en vivo** contra `quantity`: si sube la cantidad tras enviar, el badge vuelve
   solo a «pendiente» (los datos rellenos se conservan). No toca aforo ni cobro.
5. **«Menú especial» por-invitado = texto libre** (no acoplado a `product_addons`).
6. **Granularidad: por OrderItem-pack**, no por pedido.
7. **Acceso del cliente**: «Mis pedidos» (autenticado) + **signed URL de larga vida** en el
   email (el evento es a semanas vista; los signed URL cortos no sirven).
8. **Permisos**: reutiliza `orders.edit_event_data` para la edición por el empleado; el
   reenvío reutiliza la infraestructura `RESEND_TYPE_*` de `ViewOrder`. Sin permisos nuevos.
9. **Pedidos manuales**: mismo `guest_data`/estado; el empleado rellena en la ficha o dispara
   el email/enlace.

## 3. Modelo de datos

### 3.1 Columnas

| Tabla | Columna | Tipo | Para qué |
|---|---|---|---|
| `ticket_types` | `guest_fields` | JSON nullable | **Esquema** por-invitado del pack (espejo de `event_fields`). Lista de `{key, label{es,en,fr}, type, required}`. Solo packs. |
| `order_items` | `guest_data` | JSON nullable, cast `array` | **Respuestas** por-invitado: lista de N objetos `[{<key>: valor, …}, …]`. |
| `order_items` | `guest_form_completed_at` | timestamp nullable | Sello de auditoría del envío completo. **El estado real se deriva en vivo**, no de este sello. |

### 3.2 Atributo `stage` en `event_fields`

Cada entrada de `event_fields` tiene un atributo opcional **`stage`**: `booking` (por defecto)
| `postform`.
- La **compra** (`Tickets\Purchase`) y el **pedido manual** muestran/validan solo los
  `stage=booking`.
- El **post-form** muestra/valida los `stage=postform` (sección «datos generales») + la tabla
  por-invitado (`guest_fields`). `missingRequiredEventFields()` está parametrizado por `stage`.
- Entradas sin `stage` → `booking` (compatibilidad total).

**Deudas menores documentadas** (aceptadas en el origen):
(a) editar un pack legacy sin `stage` registra UNA entrada de auditoría espuria la primera vez
(el saneo le añade `stage`); cosmético, se autocura.
(b) la elegibilidad del post-form se dispara por **`guest_fields`**: un pack con SOLO campos
`postform` generales y cero columnas por-invitado no recibiría post-form (config inusual; el
seed siempre incluye `guest_fields`).

### 3.3 Helpers (fuente única)

- **`TicketType`**: `guestFields()` (normaliza, espejo de `eventFields()`),
  `guestFieldLabel()`, `sanitizeGuestData(array $rows, int $n)`,
  `missingRequiredGuestFields(array $rows, int $n)`, `guestDataCompletedCount()`,
  `DEFAULT_GUEST_FIELDS`.
- **`OrderItem`**: cast `guest_data => array`; `guestData()`, `isGuestFormComplete()` (deriva
  contra `quantity`), `guestFormStatus(): 'ok'|'pending'|null` (null si no es pack o no pide
  `guest_fields`), `needsGuestForm()`, `markGuestFormCompleted()` (sella `now()` en servidor,
  idempotente), `guestFormProgress()` («X/N fichas completas»),
  `guestFormLinkExpiresAt()` (fecha de SU franja + 14 días),
  `guestFormSignedUrl()` (**fuente ÚNICA del enlace firmado**, `temporarySignedRoute`).
- **Nomenclatura crítica: `guest`, NUNCA `children`** — `OrderItem::children()` **ya significa
  los complementos (addons)**; mezclarlos es foco de bugs.

## 4. Superficies

### 4.1 Cara cliente (web pública, es/en/fr)

- **Ruta**: `/reserva/{reservation}/datos-invitados` — nombres `reservation.guests` /
  `reservation.guests.store`. El parámetro es el **OrderItem del pack**: **1 post-form POR
  RESERVA**, no por pedido. Un pedido con 2 packs → 2 post-forms, 2 emails, 2 botones.
- **`GuestFormController`** — controlador + Blade, **NO Livewire** (decisión: funciona para un
  invitado por signed URL sin fricción de sesión). Autoriza por **firma HMAC sin sesión** o
  dueño autenticado; re-valida que el item es un pack del pedido (defensa en profundidad).
  Al guardar: canaliza TODO por `sanitizeGuestData` + `maxLength` por campo
  (`ANSWER_MAX_LENGTH`), merge de `event_data` (preserva booking, reescribe postform),
  `markGuestFormCompleted()`, audit.
- **Página** (`reservation/guests.blade.php`): hoja enfocada sin distracciones — layout propio
  **`<x-focused-layout>`** (`resources/views/components/focused-layout.blade.php`): mínimo,
  sin Livewire, carga `landing.css` + `<style id="jj-theme">` (tokens de marca) + `site.css`;
  `<html class="no-js">` (mejora progresiva); título/marca desde `$site['name']`
  (white-label), no `config('app.name')`. Contenido: cabecera (producto · fecha/hora · nº
  invitados · referencia), **barra de progreso**, nota de privacidad (datos de menores),
  **acordeón de fichas** (estado Pendiente/Lista en vivo, prev/siguiente), barra de guardar +
  toast. Estructura CSS `.gf-*` en `site.css` con tokens existentes (`--bg/--fg/--zone-1`).
  Names sin anidar por item: `guests[i][k]`, `general[k]`.
- **Guardado con BOTÓN** (no autosave). **JS plano inline** — GOTCHA: `app.js` toma Alpine de
  Livewire, así que una página sin Livewire usa JS plano. Sin JS las fichas salen abiertas y
  el form es usable; la clase `js` se marca al FINAL del IIFE dentro de `try/catch` (si el JS
  falla, repone `no-js` → nunca queda colapsado-inaccesible). `inert` en fichas colapsadas.
- **Aviso post-reserva**: paso de confirmación del sidecart (`confirmationSummary` expone
  `is_pack`) + botón por reserva en `account/orders.blade.php` (subcard `.orders__product`
  por producto; el botón del post-form va justo debajo de su reserva).
- **Email `GuestFormRequest`** (es/en/fr por `preferredLocale`, botón con color de marca), 1
  por reserva pendiente, disparado al quedar pagado (`RedsysReturnHandler` **y**
  `ManualOrderFulfiller`).
- Avisos pendientes en la cuenta: contrato `CustomerReservations::pendingGuestFormsFor()` de
  Booking (impl. `App\Domain\Booking\Services\CustomerReservationsReader`), que `CustomerAccountContext`
  (Identity) consume y convierte en URL — un aviso por reserva. Antes era una consulta directa
  dentro de `CustomerAccountContext`; se extrajo en Fase 2, paso 1 (`docs/specs/modulos-dominio.md`).

### 4.2 Cara empleado (panel, es/zh_CN)

- **Calendario** (`CalendarEventsController::toEvent`): `extendedProps.formStatus =
  $item->guestFormStatus()` **solo en packs**; pintado en `resources/js/admin/calendar.js`
  (`renderEventContent`) como **badge/icono separado** — el fondo de la píldora ya lo usa
  `active/finished`, el form es un **tercer eje visual ortogonal**. CSS en `theme.css` scoped
  a `.jj-calendar` (requiere `npm run build`). Entrada de leyenda propia.
- **Modal del calendario** (`item-detail.blade.php`) + **ficha del pedido** (`ViewOrder`):
  estado del form + subcard colapsable «Formulario de reserva» con los datos por-invitado;
  edición con el patrón `eventDataFormFields()` + `saveItemEventData()` (permiso
  `orders.edit_event_data`).
- **Reenviar email**: `RESEND_TYPE_GUEST_FORM` en `Order` + `match` en `dispatchResend()` +
  `ViewOrder::resendEmail` (revalida `canResend` + audit de éxito Y bloqueo). Un reenvío por
  reserva. En el modal del calendario va como **Filament Action anidada** (el visor es
  read-only, `modalSubmitAction(false)` — un submit del visor rompería el render).
- **Copiar enlace** (entrega sin email, p. ej. cliente con solo teléfono): icono por producto
  en `items-list.blade.php` → `mountAction('copyGuestFormLink', {item})` →
  `ViewOrder::copyGuestFormLinkAction()`, cuyo `modalContent` es la vista **server-rendered**
  `partials/guest-form-link.blade.php` (input readonly + botón «Copiar» con Alpine clipboard).
  Gating en `ViewOrder::guestFormLinkForManageItem()`: solo `isGuestFormReservation()` +
  pedido **PAGADO**. i18n `admin.orders.copy_guest_form.*`.
  **GOTCHA**: un `->default()` en un campo de un modal con `fillForm` queda **VACÍO** → el
  enlace debe ir en vista server-rendered, no en un campo de formulario.
- **Email opcional en el alta manual**: una ficha sin email no recibe correos (ni
  confirmación ni post-form) → el icono de copiar enlace es su vía de entrega. El registro
  web sigue exigiendo email. DESCARTADO en el origen: teléfono como identidad única /
  auto-fusión (teléfono no verificado).

### 4.3 PDF (hoja de reserva)

- `ReservationSlip::guestRows(): array` (solo packs; `[]` si no aplica) + tabla en
  `resources/views/pdf/reservation-slip.blade.php` (tras «Datos de la reserva»). **Subset CSS
  dompdf**: solo `<table>`, sin flex/grid; cabecera repetida (`thead { display:
  table-header-group }`) + `page-break-inside: avoid` por fila; puede fluir a 2 hojas A4.
  Si el form está **pendiente** → N filas numeradas en blanco para rellenar a mano.
- Vista delgada: toda la lógica en el presenter. Labels en `admin.orders.slip.*` (hay test que
  prohíbe claves i18n crudas en el HTML). Nunca PII de pago. Verificar el PDF real renderizado
  (gs→PNG): dompdf rompe en silencio.

## 5. Nombre de cara al usuario

Todas las superficies usan el genérico «**Formulario de reserva**» (no «datos de los niños»).

## 6. Ciclo de vida del enlace y solo-lectura

- **Caducidad del enlace POR RESERVA**: `OrderItem::guestFormLinkExpiresAt()` = fecha de su
  franja + 14 días (tope RGPD del enlace). Existe un helper agregado
  `Order::guestFormLinkExpiresAt()` no usado por el flujo.
- **Cierre en SOLO LECTURA al pasar el evento**: si la franja ya pasó
  (`isFinishedInPractice()`), `show` renderiza read-only (inputs `disabled`, sin guardar,
  aviso `guestform.readonly_notice`), **`store` no guarda** (blinda POST forjado/pestaña
  vieja) y «Mis pedidos» ofrece «Ver» en vez de editar. La gracia +14d es solo el tope del
  enlace; la **edición funcional se cierra al pasar la fecha**.
- Robustez adicional: reserva anonimizada → 410; respuestas `no-store`; anti-IDOR (la firma
  cubre la reserva concreta; test de IDOR cruzado).

## 7. Invariantes y gotchas (no romper)

- **`guest` ≠ `children`**: `OrderItem::children()` son los addons.
- **Estado derivado, no congelado**: el badge se calcula contra `quantity` ACTUAL; el
  timestamp es solo auditoría. Marcar el sello NO da «FORM OK» (el estado deriva de
  `guest_data` e ignora el sello).
- **Saneo server-side siempre**: toda respuesta del cliente se re-sanea/re-valida en servidor
  (espejo de `sanitizeEventData()`); `sanitizeGuestData` es robusto ante listas
  asociativas/con huecos (`array_values`). **Nunca** `OrderItem::update()` con input crudo
  sobre `guest_data`/`guest_form_completed_at` (`$guarded=[]`): usar `sanitizeGuestData` +
  `markGuestFormCompleted()`.
- **Ortogonalidad visual del calendario**: el indicador de form es un badge aparte; el fondo
  ya está tomado por `active/finished`.
- **FullCalendar es dueño de su DOM** (`wire:ignore`): el card se pinta en JS
  (`renderEventContent`), no en Blade; CSS scoped a `.jj-calendar`, exige `npm run build` +
  limpieza de caché. *(Prefijos `jj-*`: residuo de marca del origen; su renombrado cae en el
  desbranding, `00-REFACTOR.md` Fase 1.)*
- **Action de modal read-only**: acciones dentro del visor del calendario = Filament Action
  anidada con su propio schema, nunca un submit del visor.
- **dompdf**: subset CSS, locale forzado ES.
- **Reenvío**: revalidar estado ANTES de notificar + audit de éxito Y bloqueo. Email en
  `preferredLocale`.
- **`site.css` es estático** (no Vite) → en dev hace falta hard-reload para ver cambios CSS de
  la página pública del post-form. Tras tocar Blade: `php artisan view:clear`.

## 8. Tests

`GuestFormTest` (acceso por reserva, IDOR cruzado, multi-pack aislado, progreso, expiry
por-franja, solo-lectura GET+store) · `GuestFormEmployeeTest` (1 email por reserva en resend) ·
`CustomerAccountContextTest` (URL por reserva; 2 packs → 2 avisos) · `GuestFormLinkCopyTest`
(enlace firmado abre 200 sin sesión; gating pagado/pack; modal server-rendered lleva el
enlace) · `ManualOrderWithoutEmailTest` (alta sin email + sin correos + dedup por teléfono).
