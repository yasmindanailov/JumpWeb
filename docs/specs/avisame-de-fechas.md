# [SPEC] «Avísame de fechas» — un correo antes del cumple de un invitado

> Estado: ✅ aprobado (decisiones del owner `#743`·8, `#747` y `#750`) · Última actualización: 2026-10-03 (la C1a, §4.3) ·
> Carril SPA · Hermana: `fiesta-sistema-nuevo.md` §4.12 (F6b).

## §0 · Antes de tocar
- **La regla**: el adulto que FIRMÓ la autorización de un invitado con su correo marca en el recibo «Avísame de fechas
  para el cumple de mi hijo»; unas semanas antes del cumpleaños del niño le llega UN correo (el nº 12 del mockup de la
  instancia, «El cumple se acerca») y el parque lo ve en una lista del panel. Sin cuenta y **sin doble confirmación**
  (`#750`, contra la recomendación: el riesgo está escrito allí).
- **La prueba es la autorización firmada**: `birthday_reminders.guardian_authorization_id` (en cascada). De ella salen el
  correo, el nombre (`minor_name` ENTERO: «María José», no «María») y la fecha: aquí no se copia ni un dato del menor.
- **Todo pasa por `BirthdayReminders::offerableFor()`**: la casilla y su `POST` preguntan lo mismo (un «sí», su firma
  viva y un correo); el `POST` va a la MISMA URL firmada del recibo, en su rama, ANTES de la de «Su ficha».
- **La baja, en UN toque en cada correo** (LSSI art. 22.1) y en `List-Unsubscribe`; la página de baja escribe con un
  BOTÓN, no al abrirse (los escáneres de enlaces de los gestores de correo).
- **Una vez por cumpleaños** (`sent_for`), nunca con la baja puesta, y no a quien ya celebra aquí (§4.3).
- **Ajuste** `party.birthday_reminder_weeks` (6, como el mockup; 0 = apagado y la casilla no se pinta).
- **RGPD**: rastro sin PII (RGPD-02); suprimir la autorización arrastra la marca; el aviso de privacidad de la web tiene
  que nombrar este tratamiento: `[PENDIENTE: asesoría]` (buzón a la web).
- **Correos**: el molde es del carril de correos (`BrandedMailMessage`, sin tocarlo); este correo entra en sus censos.
- **Tandas**: A1 la casilla, la marca, la baja y el ajuste · A2 el correo, el comando horario y la lista del panel.

## 1. Contexto y problema
El owner decidió que «Avísame de fechas» entra (`#743`·8), sola en el recibo tras firmar con correo (`#747`), y qué hace
el parque con ella (`#750`): un correo antes del cumple y una lista en el panel. Medido (26-09): el único consentimiento
comercial del producto es de CUENTAS (`users.marketing_opt_in` + `consents`, `AccountPrivacy::setMarketing`), y quien
firma desde el recibo casi nunca tiene cuenta; la autorización (`guardian_authorizations`) guarda correo, nombre,
apellidos y fecha de nacimiento del menor, se conserva como prueba (solo se podan las que no tienen firma) y cuelga de
la respuesta (`invitation_reply_id`). El mockup de la instancia dibuja el correo (`correos-comerciales.js`, nº 12):
«seis semanas antes del cumpleaños de un hijo declarado, a quien no ha celebrado aquí; solo con la casilla marcada».

## 2. Objetivo
- La casilla, sin marcar, solo en el recibo de un «sí» con la autorización firmada CON correo, con su texto legal.
- UN correo por cumpleaños, `party.birthday_reminder_weeks` antes, en el idioma en que se marcó, con la baja de un toque.
- Una lista en el panel (quién, a quién, para qué cumple, en qué estado), con «Borrar» (supresión a petición).
- **Fuera**: la API (las páginas del invitado son web), campañas o envíos masivos, la doble confirmación (`#750`), el
  correo nº 11 («El año que viene», para quien sí celebró: otra tanda de correos).

## 3. Opciones consideradas
- **`consents` + `users`**: descartada. Es de cuentas; crear una cuenta por detrás para guardar una casilla sería peor.
- **Columnas en `guardian_authorizations`**: descartada. Hay una autorización por fiesta y niño; la baja y el «ya se
  mandó» son del correo y del niño, no de una fiesta, y esa tabla es la prueba de la firma (`WaiverSigner`, `CRITICAL_RE`).
- **Tabla propia `birthday_reminders`** con la autorización como prueba: **elegida**. No toca la firma ni su servicio.

## 4. Diseño elegido
### 4.1 El dato
`birthday_reminders`: `id` · `guardian_authorization_id` (FK en cascada, único) · `locale` · `accepted_at` ·
`revoked_at` (nulo) · `sent_for` (fecha del cumpleaños para el que salió, nulo) · `sent_at` (nulo) · timestamps. Modelo
`App\Domain\Identity\Models\BirthdayReminder` y servicio `App\Domain\Identity\Services\BirthdayReminders`:
`offerableFor()`, `set()` (crea, revive o da de baja), `revoke()`, `nextBirthday()`, `due()`, `markSent()`.

### 4.2 La casilla (A1)
En el recibo, donde el mockup pone el bloque del QR (que no va, `#747`): tras la línea de privacidad, una sección con
`x-pieza.casilla` «Avísame de fechas para el cumple de mi hijo.» y debajo «Te escribiremos unas semanas antes de su
cumpleaños, con la fecha que pusiste. Te das de baja en cada correo.» y la política. Solo si el ajuste está encendido,
la respuesta es un «sí» y su autorización tiene firma viva y correo. Se guarda contra la MISMA URL firmada del recibo
(`invitation.receipt.save`, su cupo) con el campo `dates` —un `0` escondido delante para que desmarcar viaje—, en su
propia rama y antes de la de «Su ficha» (que leería la falta de `guest_data` como «vacíala»): con JavaScript se guarda
sola al cambiar y dice «Guardado»; sin él, su botón. Marcar crea (o revive) la fila; desmarcar la revoca. Se DICE si ya
se mandó para este cumpleaños.

### 4.3 El correo (A2)
`App\Notifications\BirthdayComingNotice` sobre `BrandedMailMessage`, a la ruta de correo de la autorización
(`Notification::route('mail', …)->locale(…)`): el asunto «:nombre cumple :edad en :mes: ¿lo celebramos aquí?», la
cabecera «El cumple de :nombre, resuelto», lo que incluye la fiesta y el precio «desde» LEÍDOS del catálogo (el pack de
cumpleaños más barato), el botón «Ver días libres» a la página de cumpleaños de la web, y el pie «Te escribimos porque
marcaste la casilla» con la baja. Lo manda `birthday-reminders:send`, cada hora desde las 10:00 del parque (el
molde de `surveys:send-external`): a las filas vivas cuyo próximo cumpleaños cae dentro del plazo y a no menos de 7
días (más cerca llegaría tarde: ese año se salta), UNA vez por correo, niño y fecha (el mismo niño firmado en dos
fiestas es un correo), y marca `sent_for` en todas sus filas ANTES de encolar (reintentar no duplica). **No** a quien ya tiene, con ese correo y en una cuenta, una fiesta pagada de
un año antes del cumpleaños en adelante («a quien no ha celebrado aquí»). El 29 de febrero, el 28 en año no bisiesto.

▶ **Desde la C1a de los correos** (`correos-rediseno.md` §4.4, `#920`, `#921`, 03-10): el correo es el de su diseño (sin
chapa, el precio en negrita, «mira los días libres» enlazado y el pie comercial «Recibes este correo porque marcaste «Avísame
de fechas»… [toca aquí]»), y el MISMO comando lo manda también, en la misma ventana, a las cuentas con «novedades» por cada
menor que declararon (`BirthdayReminders::declaredDue()`, marca `dependents.birthday_mail_for`), con la baja de «novedades».
Nunca dos veces el mismo cumple por los dos caminos, y a quien se dio de baja aquí, tampoco por «novedades».

### 4.4 La baja y la lista (A1 y A2)
La baja: `GET` firmado sin caducidad (`birthday-reminder.unsubscribe`) → página enfocada con UN botón → `POST` firmado
(`….confirm`) → `revoked_at` en TODAS las filas vivas de ese correo. La lista del panel: «Ajustes → Precios y productos
→ Avisos de cumple» (`/admin/avisos-de-cumple`, permiso de clientes `users.manage`), con quien firmó y su correo, el
nombre del niño, el día del cumpleaños, desde cuándo, y el estado (esperando · mandado el … · de baja) con su filtro, y
«Borrar» con confirmación (la autorización se queda: es la prueba del descargo).

## 5. Impacto en invariantes
RGPD-02 (rastro sin nombres ni correos: solo ids) · RGPD-01 y la poda de autorizaciones (la cascada arrastra la marca) ·
SEC-06 (el `POST` del recibo lleva el cupo del recibo). Ningún fichero del `CRITICAL_RE`: no toca `WaiverSigner`.

## 6. Plan de verificación empírica
Pruebas de la casilla (cuándo sale y cuándo no; crear, revivir, revocar; firma inválida), del cálculo del próximo
cumpleaños (el 29 de febrero, hoy mismo, ya pasado), del comando (el plazo, una vez, la baja, «ya celebra aquí», el
ajuste apagado, `--dry-run`), del correo (asunto, precio del catálogo, baja y `List-Unsubscribe`) y de la lista y su
borrado; mutaciones de cada guarda; la sonda del recibo y de la baja en navegador; y el correo en Mailpit.

## 6.1 Ejecución (26-09, A1 + A2 en una tanda)
Hecho lo de §4 entero. **Verificado**: `AvisameDeFechasTest` 8 (cuándo se ofrece y cuándo no —sin firma, sin correo,
podada, tras un «no», ajuste a 0—, crear/desmarcar/revivir, que la casilla no toca «Su ficha», la baja firmada y sin
caducidad que no escribe al abrirse, el 29-F) · `AvisameDeFechasEnvioTest` 10 (la ventana, una vez, dos fiestas un
correo, la baja a todo el correo, «ya celebra», antes de las 10, `--dry-run`, el nombre compuesto, el correo con su pie y
su `List-Unsubscribe`, el panel con un empleado DEL PANEL sin permiso) · **arnés `scripts/mutar-avisame-fiesta.sh`
42/42** · `sonda-avisame.mjs`: el camino de un padre con y sin JavaScript, el correo en Mailpit y la baja.
**Límites sabidos**: la casilla vive en el recibo DESDE el que se firmó (un segundo recibo del mismo niño dice «Firmada»
sin casilla: la prueba es de otro); borrar desde el panel no deja rastro de auditoría; los avisos siguen mientras viva la
firma del menor (hasta sus 18 más el plazo de retención) o hasta la baja; el aviso de privacidad de la web, pendiente.

## 7. Revisión y decisión
- **25-09, `[DECIDIDO owner]` `#743`·8**: entra, sin marcar y con su texto legal.
- **26-09, `[DECIDIDO owner]` `#747`**: se queda sola en el recibo tras firmar con correo (el QR no va).
- **26-09, `[DECIDIDO owner]` `#750`**: un correo antes del cumple + la lista del panel; sin doble confirmación.

## Anexo · fila del enrutador
`| «Avísame de fechas» · el correo antes del cumple de un invitado · su baja | docs/specs/avisame-de-fechas.md §0 |`
