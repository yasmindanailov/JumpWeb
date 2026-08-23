# Flujos paso a paso

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

Recorridos del usuario con las decisiones de diseño heredadas aplicadas: **simple**, con
**modales/paneles**, **compra solo para registrados**, **waiver en el registro** (vocabulario
del sector origen; su generalización se decide en `00-REFACTOR.md` Fase 1/2) y **pago por
redirección a Redsys**.

> 🪟 = modal/panel · 📄 = página · ✉️ = email automático

> ⚠️ Este doc es la **foto de diseño**: el código heredado implementa estos flujos y los
> extendió después (post-form de datos por invitado en cumpleaños, señal/depósito en packs,
> reintento de pago). Ante cualquier duda manda el código.

---

## Flujo 1 — Registro (cuenta + waiver, todo en uno)

1. El usuario pulsa "Registrarse" (o se le pide al ir a pagar). → 🪟 **el CAJÓN, en su zona de alta**.
   ⚠️ **Era un modal sobre la home hasta el 2026-08-23** (`DECISIONES #122`): entrar, darse de alta y
   recuperar la contraseña son ahora **zonas del cajón**, de modo que la gestión del cliente vive en
   un solo sitio (`#66`). La ruta `/registro` sobrevive como **puerta**: sirve la home y abre el
   cajón ahí. Lo que se rellena y lo que se guarda **no cambia**.
2. Rellena lo mínimo: **nombre, email, teléfono y contraseña** (los cuatro obligatorios).
3. Marca las casillas obligatorias: **acepto privacidad**, **acepto términos**, **acepto el
   waiver** (cada una enlaza a su 📄 página legal; textos = contenido gestionado en BD,
   entidad `LegalContent`). Sin marcarlas no se puede continuar.
   - Casilla **opcional**, **desmarcada por defecto**: *«Quiero recibir novedades y ofertas»*
     (marketing, opt-in RGPD).
4. Pulsa "Crear cuenta". Se guarda el usuario + los consentimientos (fecha, versión, IP).
5. ✉️ Email de **verificación**. Al confirmar, la cuenta queda activa.

> Si el waiver/términos cambian de versión, se pide aceptar la nueva versión la próxima vez
> que el usuario entre.

## Flujo 2 — Iniciar sesión / recuperar contraseña

1. "Entrar" → 🪟 **el CAJÓN, en su zona de identificarse** (email + contraseña). Rutas puerta:
   `/login` — que además es el destino del middleware `auth` de Laravel, así que no es opcional.
2. "¿Olvidaste tu contraseña?" → 🪟 **zona de recuperar** del mismo cajón: pide email → ✉️ enlace.
   ⚠️ **Y el PASO 5 del embudo de compra también lo ofrece desde `#122`**: hasta entonces, quien
   estaba comprando y no recordaba su contraseña tenía que abandonar el cajón —y perdía de vista su
   cesta—. «Volver» le devuelve a la compra donde estaba.
3. El enlace del correo lleva a `/restablecer-contrasena/{token}`, que **sí es una 📄 página**: trae
   un token en la URL y el cajón no es direccionable. Lo mismo `/email/verificar`.

> ⚠️ **Las tres pantallas eran modales sobre la home hasta el 2026-08-23** (`DECISIONES #122`). Sus
> rutas sobreviven como **puertas** (`Http\Sidebar\AccountDoor`): sirven la home y abren el cajón en
> la zona que toque, igual que `/entradas` y `/mi-cuenta/…`.

## Flujo 3 — Comprar entradas (el flujo principal)

1. Desde la Home o "Precios", pulsa **"Reservar / Comprar"**. → 🪟 **panel de compra**
   (en móvil, pantalla completa; con URL propia `/entradas`).
2. **Paso 1 — Día y franja:** elige fecha y franja horaria. Se muestran las **plazas libres**
   (aforo). Franja llena → deshabilitada.
3. **Paso 2 — Entradas:** elige tipo (adulto/niño…) y cantidad. Total actualizándose en vivo.
4. **Paso 3 — Identificación:** si no ha iniciado sesión, el propio cajón pide entrar o crear cuenta
   **sin salir del panel** (Flujo 1). Si ya está dentro, se salta.
   ⚠️ El alta de aquí es **pay-first** (`DECISIONES #31`): abre sesión y **no** manda correo de
   verificación, porque el pago la sustituye —un bot no paga—. El alta suelta del Flujo 1 hace lo
   contrario, y lo que separa las dos es un campo (`context`).
5. **Paso 4 — Resumen:** revisa día, franja, entradas y total. Acepta condiciones de compra.
6. Pulsa **"Pagar"**. → 📄 **redirección a Redsys**, introduce la tarjeta en el banco.
7. **Vuelta del pago:**
   - ✅ Correcto → 📄 **página de confirmación** con las **entradas y su QR** + ✉️ email
     con las entradas.
   - ❌ Error/cancelado → 📄 página de "pago no completado" con opción de **reintentar**. No
     se consume aforo.
8. Las entradas quedan en 📄 **"Mi cuenta › Mis entradas"**.

> **Aforo seguro (implementado así en el código heredado):** al lanzar el pago, el `Order`
> nace `pending` con `expires_at = now + hold_minutes` (setting `sales.hold_minutes`, vía
> `PaymentSettings::holdMinutes()`): retiene la plaza de forma provisional. Si el pago no
> llega, el comando programado `orders:expire` lo caduca y libera el aforo. Tras la vuelta OK
> firmada se pone `expires_at = null` (pedido firme, no caduca). El reintento de pago extiende la
> ventana con un UPDATE **atómico** (check + extensión en la misma sentencia) para no reabrir cobros
> de pedidos ya caducados; desde Fase 3 · paso 2 esa sentencia vive UNA sola vez, en
> `Booking\Services\ReservationAdmissionPolicy::admitPaymentRetry()`, y la usan por igual el
> sidebar y «Mis pedidos».

## Flujo 4 — Reservar cumpleaños / evento

*(«cumpleaños», «sala»: vocabulario del sector origen.)*

1. Desde "Cumpleaños", elige un **pack** (ve qué incluye y el precio).
2. 🪟 **panel de reserva** (`/cumpleanos/reservar`):
   - **Fecha y franja** disponibles (y sala, si aplica).
   - **Nº de invitados**, **nombre y edad** del cumpleañero, extras y notas.
3. **Identificación:** login/registro si hace falta (Flujo 1).
4. **Resumen** y pago con Redsys de la **señal o el total** (configurable; el diseño de
   señal/depósito vive en el repo origen — verificar estado real en código antes de tocar).
5. ✅ → 📄 confirmación + ✉️ email. La reserva queda en 📄 "Mi cuenta › Mis reservas".
6. El administrador la ve en el panel y puede **confirmarla, reprogramarla o cancelarla**.

> Evolución posterior al diseño: **post-form de datos por invitado** tras la reserva
> (diseño histórico en el repo origen; verificar implementación en código).

## Flujo 5 — Preparación y entrega en el recinto (flujo físico)

*(«pulsera», «zona», «puerta»: vocabulario del sector origen; su generalización se decide en
`00-REFACTOR.md` Fase 1/2. Ver `OPERATIVA-SECTOR-ORIGEN.md`.)*

1. **Preparar (interno):** el empleado ve los pedidos en el panel, prepara la **pulsera**
   física (nombre del cliente + color de su zona) y marca la entrada como **Preparada**.
2. **Entregar (en puerta):** el cliente muestra su confirmación/QR. El empleado la verifica,
   entrega la pulsera y marca la entrada como **Canjeada** (no reutilizable).
3. **Registro/waiver:** además valida que la persona está **registrada** (por email/teléfono;
   solo ✓/✗, sin exponer datos). Si no lo está, se registra en el momento (móvil o tablet).

## Flujo 6 — Administrador (resumen)

1. Entra en 📄 `/admin` (acceso protegido por rol).
2. Ve ventas, próximas reservas y ocupación del día.
3. Gestiona pedidos, reservas, franjas/aforo, contenido y configuración.
4. Edita textos, precios, horarios y datos del negocio **sin tocar código** (principio
   data-driven / white-label).

Detalle del panel: `PANEL-ADMIN.md`.

---

## Reglas transversales (invariantes de los flujos)

- **Solo se compra/reserva con cuenta** (la cuenta lleva el waiver incluido).
- **Email obligatorio y verificado** para comprar (notificación dedicada
  `VerifyEmailForPurchase` cuando el bloqueo ocurre en el checkout).
- **Nada de guardar tarjetas:** el pago lo gestiona Redsys cada vez.
- **Cancelaciones/reembolsos:** el sistema prevé el estado "reembolsado"; la política
  concreta es configurable por instalación (white-label).
- **Idioma:** el flujo se muestra en el idioma elegido (ES/EN/FR).
