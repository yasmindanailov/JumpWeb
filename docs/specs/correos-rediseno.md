# [SPEC] El rediseño de los correos — la plantilla nueva, los de la reserva al brief y los comerciales

> Estado: ⬜ **borrador, medido el 29-09** · Última actualización: 2026-09-29 · Decisiones: `#800` (el orden: ahora los
> correos), `#789` (los correos, del carril del SPA), `#793` (las felicitaciones, sin vender) y la de su aprobación ·
> Carril: **SPA** (banda 790–819). Amplía `correos-desde-canvas.md` (el molde de septiembre sigue siendo suyo).

## §0 · Antes de tocar

- **La regla**: manda el diseño de la instancia (la carpeta `paginas/correos` del zip y su brief `brief-correos-playjump`) y
  el contenido es el del brief, TAL CUAL: «no inventes correos, bloques, textos ni ofertas». Lo del parque (marca, datos,
  precios) va por Blade desde el panel y el catálogo, nunca en el producto (`correos-desde-canvas.md` §2).
- **Empieza por** §1 (el censo HAY/FALTA) → §4 (las tandas) → §7 (lo que espera al owner).
- **Trampas**: (1) el molde es `BrandedMailMessage` y todo componente nace dos veces, html y texto (`#500`→`#508`); (2) el
  modo oscuro, solo en el `<style>` del layout y por color; (3) un comercial lleva su porqué y la baja de un toque, también
  en `List-Unsubscribe` (LSSI 22.1, `#750`), y sale solo con su consentimiento; (4) la marca `jw_e` y el píxel siguen sus
  reglas (`#795`, `#797`); (5) los de la reserva, sin ofertas; (6) «nada que ya no sea verdad»: con la cuenta con contraseña
  (23-09), los textos del 8 del brief están desfasados, y el diseño lo dice.
- **Estado**: ⬜ borrador; §7 espera al owner (lo que tiene «por ahí», el 7, las ocasiones y el orden).
- **Invariantes**: `RGPD-01` (lo enviado), `RGPD-07`, el consentimiento de marketing; `PAY-14` (se encolan).

## 1. Contexto — medido el 29-09

- **Hoy**: 27 clases de correo (25 notificaciones y 2 `Mailable`), vestidas con el molde del canvas de septiembre
  (`correos-desde-canvas.md`, `#500`→`#508`; el aviso de la víspera, `#717`).
- **El diseño nuevo** (el zip del 24-09 por la tarde, en la carpeta `paginas/correos` de la instancia —la plantilla, los de
  la reserva, los comerciales, el visor y su hoja— y el brief en `uploads`): **nueve de la reserva y siete comerciales**, y
  tres grupos cuyo texto el propio diseño deja abierto (8, 9 y 10). El zip del 27-09 no los tocó (su diferencia, medida).
- **El censo**:

| Nº | En el diseño | Hoy | Estado |
|---|---|---|---|
| 1 | Reservado, entradas | `OrderConfirmation` | HAY, cambia: el QR dentro (hoy, adjunto), «Antes de venir», «Si cambian los planes», calendario y «Cómo llegar» |
| 1b | Reservada, excursión | `OrderConfirmation` | HAY, cambia como el 1 |
| 2 | Fiesta reservada | `OrderConfirmation` y `GuestFormRequest` | HAY, cambia: «Ahora, dos cosas» con sus dos botones |
| 2b | Tres días antes de la fiesta | — | FALTA |
| 3 | Mañana, entradas | `VisitEveNotice` (solo si queda algo) | A MEDIAS: el diseño lo manda siempre, con el QR |
| 4 | Mañana es la fiesta | `VisitEveNotice` | HAY, cambia |
| 5 | El pago no ha salido | `OrderPaymentDeclined` | HAY, cambia |
| 6 | La reserva ha caducado | `OrderExpiredWithoutPayment` | HAY, cambia |
| 6b | Tienes una reserva a medias | — | FALTA (comercial) |
| 7 | ¿Qué tal ayer? | `SurveyInvitation` (la encuesta anónima, `#754`) | CHOCA: el diseño pide la reseña en Google y «Reservar otra vez» |
| 8 | Cuenta y acceso | seis clases y las dos del framework | HAY; su texto nuevo, PENDIENTE en el diseño |
| 9 | Cambios hechos por el parque | siete clases | HAY; el patrón dado, sus textos PENDIENTES |
| 10 | Invitados, extras y autorizaciones | cuatro clases | HAY; los textos, con la iteración de Invitados |
| 11 | El año que viene | — | FALTA (comercial) |
| 12 | El cumple se acerca | `BirthdayComingNotice` (a quien firmó por un invitado, `#750`) | A MEDIAS: el diseño lo amplía a los hijos declarados de quien no ha celebrado aquí |
| 13a · 13b | Hace tiempo que no venís (con niños · a saltar) | — | FALTA (comercial) |
| · | Por ocasiones | — | FALTA, y sin mecanismo: un correo a un público |
| — | Las felicitaciones (TP·3c, `#793`) | — | FALTA, fuera del diseño: su copy, con el owner |
| — | Los del equipo (el enlace de la analítica, Google, la incidencia de pago, el mensaje de contacto) | cuatro clases | fuera del diseño: toman la plantilla |

- **Lo que el brief pide y hoy no hay**: el QR DENTRO del correo (imagen y código dictable; hoy va adjunto y solo en la
  confirmación), «Añadir al calendario» (ningún `.ics`), «Cómo llegar», WhatsApp (solo en la autorización de un menor) y que
  responder llegue al parque (solo el mensaje de contacto lleva `replyTo`).

## 2. Objetivo

- La plantilla nueva en TODOS los correos (también los del equipo). Los de la reserva, con el contenido del brief. Los
  comerciales nuevos, cada uno con su disparador, su consentimiento, su «una vez» y su baja. Las felicitaciones.
- **Éxito**: cada correo contra su mockup (Mailpit y una sonda), su versión de texto con lo mismo, las guardas del molde
  verdes, y un comercial solo a quien consintió (con su prueba y su mutación).
- **Fuera**: el marketing con coste (`#800`); inventar textos; los grupos 8–10 hasta que el diseño tenga sus textos.

## 3. Opciones

- **A (elegida): vestir el molde** (`BrandedMailMessage`) con la plantilla nueva y rehacer el contenido correo a correo. El
  molde ya resuelve lo difícil —tablas, el tema en línea, el modo oscuro, la versión de texto, el remitente, las marcas—.
- **B (descartada): empezar de cero** (otra plantilla u otro lenguaje de correo): una dependencia nueva y rehacer lo resuelto.

## 4. Las tandas (propuesta del agente, vetable)

- **R1 · la plantilla**: la cabecera, el resguardo, el cuerpo, el botón y el pie del diseño, en el molde; los 27 la heredan
  sin cambiar su contenido.
- **R2 · la reserva**: 1, 1b, 2, 3, 4, 5 y 6 con el brief —el QR dentro, el calendario, «Cómo llegar», WhatsApp, responder al
  parque—. **R3**: el 2b.
- **C1 · los comerciales automáticos**: 11, el 12 ampliado, 13a y 13b, 6b; cada uno con su comando, su consentimiento
  (`marketing_opt_in`), su «una vez por persona y periodo» y su baja.
- **C2 · las felicitaciones** (TP·3c): el copy, con el owner antes de construir.
- **C3 · por ocasiones**: mandar un correo a un público —la mayor de todas—: al owner.
- **El 7** y **8–10**: al owner y al diseño.

## 5. Impacto en invariantes

`RGPD-01` (cada envío queda en `email_sends`), el consentimiento de marketing (un comercial sin él no sale; una cuenta
anonimizada, nunca), `RGPD-07` (la analítica de los correos, sin persona), LSSI 22.1 (la baja); `PAY-14` (se encolan).

## 6. Plan de verificación

Las guardas del molde (la versión de texto y el contraste en claro y en oscuro); cada correo en Mailpit contra su mockup y
una sonda por tanda; los comerciales con su prueba de consentimiento y de «una vez», con su mutación; el ojo del owner.

## 7. Revisión y decisión — espera al owner

1. «Alguna otra cosa que tengo por ahí» (29-09): qué es.
2. **El 7**: el diseño pide la reseña en Google y «Reservar otra vez»; hoy sale la encuesta anónima (`#754`).
3. **Por ocasiones**: una herramienta para escribir y mandar un correo a un público; ¿ahora o después?
4. El orden de las tandas (§4).
