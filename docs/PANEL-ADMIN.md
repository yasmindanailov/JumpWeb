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

### 2.4 Verificación en puerta y canje por pulsera
- Buscar el pedido (código, email o QR) → ver **qué entrada es** y el **color de pulsera**
  (zona) correspondiente → entregar/activar la pulsera y **marcar la entrada como canjeada**
  (no reutilizable). Detalle: `OPERATIVA-SECTOR-ORIGEN.md`.
- Ver **ocupación (aforo)** por franja en tiempo real.

### 2.5 Validar registro / waiver en puerta (privacidad por diseño) ⭐
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

---

## 5. Permisos del empleado (decidido)

El empleado **puede**:
- Ver el calendario y las reservas/entradas.
- Crear reservas/entradas en back-office.
- Gestionar reservas existentes (confirmar, reprogramar, cancelar).
- **Verificar en puerta:** validar registro/waiver (§2.5), buscar pedidos, entregar pulsera
  y marcar entradas como canjeadas (§2.4).

El empleado **NO** puede: configuración, información fiscal, precios, ni crear/borrar usuarios.

---

## 6. Decisiones funcionales heredadas (cerradas en el origen)
1. Cobro de altas manuales → **marcar como pagado / pendiente** (efectivo/datáfono), sin
   enlaces de pago.
2. Permisos del empleado → §5 (incluye check-in en puerta).
3. Calendario → **una sola vista con filtros y colores**.
