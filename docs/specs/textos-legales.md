# [SPEC] Los textos legales con lo nuevo: la privacidad y las condiciones, medidas contra el código y contra producción

> Estado: ⬜ borrador (documento para la revisión del owner; después, la asesoría y el código) · Última actualización:
> 2026-10-01 · Decisiones: `#863` (los invitados, a los 14 días) y `#864` (sin aviso), del owner; la del ✅, al aprobarse ·
> Carril: plataforma («retomar» 1; entran las notas
> del SPA a `/privacidad`: `#750`, `#754`, la TP·1 de `#792` y `#793`) · Encargo: «primero como DOCUMENTO para que el
> owner lo revise; después, al código» · El método, el de `/cookies` (`specs/politica-de-cookies.md`, `#859`).

## §0 · Antes de tocar

- **La regla**: el texto dice lo que el producto hace DE VERDAD en esta instalación —cada frase con su código (§1.2)—,
  como `/cookies` (`#859`); lo que depende de un interruptor (antibot, Google, mapa, análisis, píxeles, clics y apertura
  de los correos) se nombra solo encendido: lo compone el servidor (§4.3).
- **Empieza por** §1.3 (lo que el texto VIVO dice y es falso) → §4.2 (el texto nuevo) → §7 (lo que decide el owner).
- **Trampas**: (1) producción NO lleva el texto de fábrica: se editó a mano (Google, proveedores, 6 años) y no hay
  rastro en el repo; llega por un script con su huella, como `/cookies` (`ENTORNOS.md` §6). (2) El francés tutea
  (159 formas): va de «vous». (3) La plataforma ODR cerró el 20-07-2025. (4) Un tratamiento con consentimiento no lo
  legitima el texto: se pide aparte. (5) Se escribe para la v2.0.0: sin contraseña (A5).
- **Estado**: ⬜; lo revisa el owner (§7: D1–D10), después la asesoría (`[PENDIENTE: asesoría]`) y después el código.
- **Invariantes**: RGPD-01 (lo que la supresión hace es lo que el texto promete), RGPD-03 y RGPD-07; `#863` le da a
  RGPD-01 una poda (los invitados, a los 14 días): sin ella, el apartado 6 no se publica (§4.4). `#864`: sin aviso.

## 1. Contexto y problema (medido el 2026-10-01)

### 1.1 De dónde sale el texto

- **De fábrica**: `LegalContent::pages()` —privacidad, condiciones y aviso legal, en es/en/fr—, que siembra una instalación
  nueva y llega a una ya sembrada por migraciones QUIRÚRGICAS por huella (la última, la de la T3a de la analítica:
  `PROFILING_P`). Los datos del titular son marcadores que `LegalIdentity::interpolate()` sustituye al pintar
  (`:legal_name`, `:legal_nif`, `:legal_address`, `:legal_email`, `:legal_phone`, `:site_domain`, `:tax_rate`,
  `:business_name`, `:jurisdiction`).
- **En producción** (PlayJump, leído de `playjump.es/privacidad` y `/condiciones`): un texto EDITADO a mano sobre el de
  fábrica. Añade «Acceso con tu cuenta de Google», resuelve los `[PENDIENTE]` (alojamiento en la UE, Francia; correos desde
  servidores propios; DPF de Google y Cloudflare; seis años por el art. 30 del Código de Comercio; la baja de marketing
  desde «Mi cuenta → Privacidad») y titula las condiciones «Condiciones de reserva». **No hay rastro en el repo** de quién lo
  escribió (ni en `docs/`, ni en `app/`, ni en `database/`, ni en los guiones del owner). La migración por huella no lo
  tocaría: es otro texto.
- **Lo de fuera**: el correo sale por `sendmail` del propio servidor (`ENTORNOS.md` §6, sin proveedor externo); el
  alojamiento es infraestructura propia gestionada con Enhance, en la IP 51.68.7.199 (de OVH según su rango; el texto
  vivo dice «Francia»: `[PENDIENTE: owner]` confirmarlo).

### 1.2 Lo que el producto trata hoy

Medido en el esquema (columnas con datos personales), en el planificador (`schedule:list`: doce modelos con poda) y en
el código de cada pieza. «Vivo» = si el texto de producción lo dice.

| # | Tratamiento | Datos | Cuánto tiempo (medido) | Dónde | Vivo |
|---|---|---|---|---|---|
| 1 | La cuenta | nombre, correo, teléfono (en los productos que lo piden, `#787`), idioma; fecha de nacimiento OPCIONAL (TP·1, `#792`) | mientras exista; la supresión ANONIMIZA (RGPD-01) | `SelfSignup`, `User::anonymize()` | sí, salvo la fecha |
| 2 | Entrar | código de un solo uso (huella `code_hash`, correo, IP; 10 min, 5 intentos); «Mantener la sesión iniciada» (90 días sin uso, sin marcar, `#858`) | los códigos, 24 h (`LoginCode::RETENTION_HOURS`) | `LoginCodes`, `RememberedDevice` | **no**: dice contraseña |
| 3 | Google | correo, nombre, el `sub` | mientras esté vinculado | `user_identities` | sí |
| 4 | Pedidos y pagos | productos, fecha, franja, importes, complementos; del banco, marca, país y autorización | seis años (texto vivo) | `Order`, Redsys | sí |
| 5 | Invitados de un cumpleaños | nombre y, si el pack lo pide, alergias (art. 9) y menú, en `order_items.guest_data`/`event_data` | **hasta suprimir la cuenta**: solo `User::anonymize()` los vacía | `OrderItem` | **falso** (§1.3) |
| 6 | Invitación digital | nombre y edad de quien cumple, el teléfono si se elige; respuestas de OTRAS familias (nombre del niño y, si se pide, alergias) | respuestas: 14 días tras la fiesta (`InvitationReply::RETENTION_DAYS`); todo, con la cuenta | `party_invitations`, `invitation_replies` | no |
| 7 | Menores a cargo | nombre, apellidos, fecha de nacimiento, parentesco | mientras estén; retirados, se borran salvo firma (`Dependent::prunable()`) | `dependents` | no |
| 8 | El descargo firmado | quien firma (nombre, correo, teléfono, parentesco), por quién (nombre y fecha del menor), versión del texto, IP, navegador, cadena de huellas | `waiver.retention_months` y `waiver.dependent_retention_months` (desde los 18); **vacío = no se poda** | `WaiverSignature` | a medias (y las condiciones vivas dicen que la web NO lo recoge) |
| 9 | La autorización de un menor invitado | quien firma (nombre, apellidos, parentesco, correo, teléfono) y el menor (nombre, apellidos, fecha) | el régimen de su firma | `guardian_authorizations` | no |
| 10 | «Avísame de fechas» (`#750`) | el correo de quien firmó la autorización; nombre y fecha del menor, leídos de ella | con la autorización (cascada) | `birthday_reminders` | no |
| 11 | El carné y la puerta | el carné (20 caracteres); la puerta ve nombre, reservas de hoy y de los próximos días, descargos y, de los menores, nombre, edad y descargo (nunca apellidos); la visita | mientras exista la cuenta | `customer_cards`, `GateProfileData` | no |
| 12 | Encuestas (`#754`) | participación y respuesta; anónimas: el sello se separa a los 90 días | respuestas y participaciones, 24 meses (`SurveyResponse::RETENTION_MONTHS`) | `SurveySeals` | no |
| 13 | Correos enviados | dirección, asunto, copia, adjuntos | copia 6 meses, fila 24 (`EmailSend`) | `email_sends` | no |
| 14 | Clics por persona (`jw_e`) | cuándo y qué enlace, sin IP ni navegador; APAGADO de fábrica; solo cuentas sin oposición; nunca la encuesta | con el envío | `EmailClickMarks` | no |
| 15 | Apertura de los correos | un píxel; APAGADO de fábrica; solo con «análisis» aceptado | con el envío | `EmailOpenMarks` | no |
| 16 | Medición propia exenta | `visitor_id` (13 meses), sesiones y eventos sin persona (25 meses), experimentos | 25 meses | `Visitor`, `AnalyticsSession` | en `/cookies` |
| 17 | Análisis vinculado a la cuenta | la navegación, solo con «análisis»; oposición en «Mi cuenta» | 90 días de sesiones al enlazar (`AccountLinker::LINK_DAYS`) | RGPD-07 | **no**: dice «ni elaboramos perfiles» |
| 18 | Publicidad | píxeles de Google Ads, Meta y TikTok y conversión de servidor con correo y teléfono en SHA-256, solo con «marketing» | lo de cada plataforma | `Pixels`, `SendConversionToPlatforms` | no |
| 19 | Opiniones de Google copiadas | nombre y foto de quien reseña, texto, respuesta | 30 días; nombre y foto, 3 sin confirmar (`GoogleBusinessReview`) | `business-profile:sync` | no |
| 20 | Seguridad y prueba | IP y navegador en `audit_logs`, `sessions`, `cookie_consent_logs` (24 meses) y `consents` | `audit_logs` **sin plazo** (no se poda) | `AuditLogger` | a medias |
| 21 | Comunicaciones comerciales | `marketing_opt_in` (sin marcar; baja en «Mi cuenta») | hasta retirarlo | `MePrivacyController` | sí |
| 22 | Contacto | correo, teléfono, WhatsApp (lo trata Meta) | — | la isla | sin WhatsApp |

**Terceros**, medidos en la configuración: Redsys y el banco (siempre); el alojamiento (UE); Cloudflare Turnstile (con
claves); Google (el acceso, el mapa con consentimiento y la API del Perfil de Empresa, de donde salen las opiniones); la
herramienta de análisis, PostHog o Matomo (`Drivers`, solo con «análisis»); Google Ads, Meta y TikTok (`Pixels`, solo con
«marketing»); Bunny Fonts (las páginas del armazón del producto: 404, encuestas, reintento de pago); WhatsApp (si la
persona escribe); y el proveedor de la plataforma (mantenimiento y soporte: encargado del tratamiento).

### 1.3 Lo que el texto VIVO dice y es falso o ya no vale

1. **Condiciones, «3. Cuenta de usuario»**: «el waiver NO se gestiona en esta web… Esta web no recoge ni almacena el
   waiver». FALSO desde la Fase 6 (`#160`): se firma en la web y se guarda su prueba.
2. **Privacidad, conservación**: «Los datos de los invitados a un cumpleaños se eliminan una vez celebrado el evento,
   salvo los que formen parte del pedido». Esos datos SON del pedido (`order_items.guest_data`) y solo se vacían al
   suprimir la cuenta: **alergias de menores sin plazo** (art. 5.1.e). Lo que se borra a los 14 días son las respuestas de
   la invitación. → D1.
3. **La contraseña** (tres veces en la privacidad, dos en las condiciones) y «verificamos tu correo mediante un enlace
   que caduca»: con la A5 se entra con un código.
4. **El muro de redes** (SnapWidget/LightWidget): no se ofrece (`#309`, `#860`); el propio texto vivo dice en otro
   apartado que no hay redes incrustadas.
5. **«ni elaboramos perfiles»**: con la v2.0.0 la navegación se vincula a la cuenta con «análisis» (la T3a ya lo cambió
   en fábrica, `PROFILING_P`).
6. **La plataforma europea ODR**: cerrada el 20-07-2025 (Reglamento (UE) 2024/3228).
7. **«hasta 3 días naturales»** escrito en el texto: es dato de cada producto (`cancellation_cutoff_hours`, que solo
   INFORMA, y `deposit_refundable_in_time`, que solo PROMETE; los cambios y las devoluciones los hace el personal).
8. **Falta el aviso de que no hay desistimiento**: el ocio con fecha concreta no lo tiene (art. 103.l del TRLGDCU) y hay
   que decirlo antes de contratar (art. 97.1.n).
9. **El francés** de fábrica tutea en las tres páginas: 159 formas de «tu» y ninguna de «vous».

## 2. Objetivo

- **Éxito**: cada fila de §1.2 aparece en el texto nuevo con qué datos, para qué, con qué base y cuánto tiempo; cero
  frases de §1.3; lo que depende de un interruptor sale solo encendido; el francés, de «vous»; el ✅ del owner y la
  asesoría sobre cada `[PENDIENTE: asesoría]`.
- **Fuera**: el texto del descargo (`/waiver`: lo publica `LegalDocumentPublisher` por versiones; su texto definitivo
  sigue pendiente del owner, `specs/waiver-probatorio.md` §0); la política de cookies (✅ `#859`); el aviso legal, salvo su
  francés; el en/fr, hasta que el español esté aprobado.

## 3. Opciones consideradas

- **A · Texto fijo en la BD, editado a mano** (como hoy). Descartada: es como producción llegó a publicar frases falsas
  (§1.3): nada avisa cuando el producto cambia.
- **B · Texto en la BD + piezas compuestas por la configuración** (como el listado de `/cookies`). **Elegida**: la
  redacción sigue siendo editable por la clienta y su asesoría, y lo que depende de un interruptor no puede mentir.
- **C · Texto generado entero en el código**. Descartada: la redacción legal tiene que poder editarse sin desplegar.

## 4. Diseño elegido

### 4.1 Cómo se lee §4.2

- `⟨si X⟩ … ⟨/si⟩`: frase que el servidor compone y que solo sale con X encendido (§4.3).
- `[Dn]`: lo decide el owner (§7). `[PENDIENTE: asesoría]`: lo valida la asesoría.
- «(nuevo)» o «(cambia)»: contra el texto vivo de producción. Sin marca: igual que hoy.
- Los marcadores (`:legal_name`…) los sustituye el servidor; en PlayJump dicen su razón social, su NIF y su correo.

### 4.2 El texto nuevo, en español

#### 4.2.1 Política de privacidad

**1. Responsable del tratamiento**
> El responsable del tratamiento de tus datos es :legal_name, con NIF :legal_nif y domicilio en :legal_address. Para cualquier cuestión sobre esta política o para ejercer tus derechos, escríbenos a :legal_email.

**2. Qué datos tratamos y de dónde salen** (cambia)
> Tratamos los datos que tú nos das al crear tu cuenta, reservar o firmar; los que otras personas nos dan cuando te invitan a una fiesta o firman por un menor; y los que se generan al usar la web y al entrar en el parque. En cada apartado te contamos qué datos son, para qué los usamos, con qué base jurídica y cuánto tiempo los guardamos. Las cookies tienen su propia política, en el pie de página.

**3. Tu cuenta y cómo entras** (cambia)
> Para crear tu cuenta te pedimos tu nombre y tu correo electrónico, y guardamos tu idioma. Te pedimos también tu teléfono cuando reservas algo que lo necesita, como un cumpleaños, para poder contactarte por tu reserva. Tu fecha de nacimiento es opcional: si nos la das, la usamos solo en cifras de conjunto, para conocer mejor a quién visita el parque. No usamos contraseñas: entras con un código de un solo uso que te enviamos al correo y que caduca a los 10 minutos; de cada código guardamos solo una huella cifrada, el correo y la dirección IP desde la que se pidió, y lo borramos a las 24 horas. Si marcas «Mantener la sesión iniciada», recordamos ese dispositivo hasta 90 días sin uso. La base es el contrato que nos pides (art. 6.1.b RGPD); la de tu fecha de nacimiento, tu consentimiento al darla (art. 6.1.a RGPD), que retiras borrándola de tu perfil. [PENDIENTE: asesoría — la base de la fecha de nacimiento]

**4. Entrar con tu cuenta de Google** (cambia: sin contraseña)
> ⟨si Google⟩ Puedes crear tu cuenta o entrar con tu cuenta de Google. Si eliges esa vía, Google nos comunica tu correo electrónico, tu nombre y un identificador propio y permanente de tu cuenta de Google, que guardamos para reconocerte en tus siguientes visitas. Nunca conocemos tu contraseña de Google, y solo recibimos estos datos cuando pulsas ese botón y lo autorizas en la pantalla de Google. Los usamos para crear tu cuenta y darte acceso, con la misma base que el resto de tu cuenta (art. 6.1.b RGPD). Puedes desvincular Google desde «Mi cuenta» y seguir entrando con el código por correo. ⟨/si⟩

**5. Tus compras y reservas** (cambia)
> Al comprar entradas o reservar un cumpleaños o una excursión tratamos los datos del pedido: productos, fecha y franja, cantidades, complementos, importes y estado del pago, y lo que nos cuentes en el formulario de la reserva. El pago se hace siempre en el entorno seguro de Redsys: no conocemos ni guardamos el número de tu tarjeta, y de la respuesta del banco conservamos solo lo necesario para el soporte y la conciliación (marca y país de la tarjeta, códigos de autorización, importes y fechas). La base es el contrato (art. 6.1.b RGPD) y, para facturar y llevar la contabilidad, una obligación legal (art. 6.1.c RGPD).

**6. Los invitados de un cumpleaños y los datos de salud** (cambia)
> Tras reservar un cumpleaños puedes darnos, en el formulario de la reserva, los datos que pida por cada invitado: su nombre y, si el pack lo pide, alergias o intolerancias, menú especial y observaciones. Las alergias e intolerancias son datos de salud, una categoría especial: solo las usamos para preparar la fiesta y por seguridad alimentaria, con el consentimiento explícito de quien las aporta como responsable del menor (art. 9.2.a RGPD) y, cuando haga falta, para proteger un interés vital del menor (art. 9.2.c RGPD). Al escribirlas nos confirmas que cuentas con el permiso de sus padres o tutores. Borramos estos datos 14 días después de la fiesta; del pedido quedan solo las cantidades y los importes. [`#863`: lo hace una poda que aún no existe, §4.4]

**7. Los menores a tu cargo** (nuevo)
> En «Mi cuenta» puedes añadir a los menores a tu cargo (nombre, apellidos, fecha de nacimiento y parentesco) para asignarles entradas y firmar en su nombre el descargo de responsabilidad. Solo puede hacerlo su padre, madre o tutor. Si quitas a un menor, borramos sus datos, salvo que tenga un descargo firmado, que se conserva como se explica en «El descargo de responsabilidad». La base es el contrato (art. 6.1.b RGPD).

**8. La invitación digital** (nuevo)
> ⟨si invitación⟩ Si tu pack la incluye, puedes crear una invitación digital y compartir su enlace con las familias de los invitados. La invitación muestra el nombre y la edad de quien cumple años y, si lo decides, tu teléfono. Cada familia contesta en ella si viene, con el nombre de su hijo o hija y, si el pack lo pide, sus alergias: esos datos los aporta cada familia y los usamos solo para preparar la fiesta. Las respuestas se borran 14 días después de la fiesta, y la invitación entera si suprimes tu cuenta. Quien tenga el enlace puede abrir la invitación: compártelo solo con quien invites y, si se difunde, pídenos que lo cambiemos; el anterior deja de funcionar al momento. ⟨/si⟩

**9. El descargo de responsabilidad** (nuevo)
> Para usar las atracciones, cada participante necesita un descargo de responsabilidad firmado. Lo firmas en la web, para ti y para los menores a tu cargo; la firma queda hecha cuando confirmas tu correo, cuando pagas una reserva o, en el parque, ante nuestro personal. Para poder demostrar quién firmó, qué texto y cuándo, guardamos un registro de cada firma: el nombre, el correo y el teléfono de quien firma y, si firma por un menor, su parentesco y el nombre y la fecha de nacimiento del menor; la versión del texto aceptado; la fecha y la hora; y la dirección IP y el navegador. Cada registro va encadenado al anterior mediante huellas, de forma que cualquier alteración se detecta. La base es nuestro interés legítimo en poder acreditar esa aceptación ante una reclamación (art. 6.1.f RGPD). El registro se conserva aunque suprimas tu cuenta, apartado y al alcance solo del personal autorizado, durante el plazo de prescripción de las acciones de responsabilidad [D2: n años]; el de un menor, contado desde que cumple 18 años.

**10. La autorización de un menor invitado** (nuevo)
> ⟨si autorización⟩ Cuando un menor viene invitado a una fiesta o a una excursión, su padre, madre o tutor firma una autorización desde el enlace que recibe: nos da su nombre, apellidos, parentesco, correo y teléfono, y el nombre, los apellidos y la fecha de nacimiento del menor. La usamos para que el menor pueda entrar y para poder acreditar que estaba autorizado, con el mismo registro y el mismo plazo que el descargo. Esos datos no son de quien reservó: no los ve en su cuenta ni se borran con ella. ⟨/si⟩

**11. «Avísame de fechas»** (nuevo)
> ⟨si avisos⟩ Al firmar la autorización de un menor invitado puedes marcar «Avísame de fechas». Si lo marcas, unas semanas antes del cumpleaños del menor te enviamos un correo para recordártelo, con el correo, el nombre y la fecha de nacimiento que figuran en la autorización. La base es tu consentimiento (art. 6.1.a RGPD); cada correo lleva un enlace para darte de baja, y el aviso se borra con la autorización. [PENDIENTE: asesoría — sin doble confirmación] ⟨/si⟩

**12. El carné y la entrada al parque** (nuevo)
> Tu cuenta tiene un carné con un código QR que te identifica en la entrada. Al escanearlo, nuestro personal ve tu nombre, tus reservas de hoy y de los próximos días, si los descargos están firmados y, de los menores, su nombre, su edad y el estado de su descargo, nunca sus apellidos; y anota tu visita. Si pierdes el carné, puedes renovarlo desde «Mi cuenta» y el anterior deja de valer. La base es el contrato (art. 6.1.b RGPD).

**13. Las encuestas** (nuevo)
> Después de tu visita podemos enviarte una encuesta breve. Es anónima: a los 90 días tu respuesta se separa de ti del todo, y nadie puede saber qué contestaste; las respuestas, ya sin nadie detrás, se guardan 24 meses. La base es nuestro interés legítimo en mejorar el servicio (art. 6.1.f RGPD). Si no quieres recibirlas, desactívalas en «Mi cuenta → Privacidad».

**14. Los correos que te enviamos** (nuevo)
> Guardamos un registro de los correos que te enviamos (dirección, fecha, asunto y una copia) para poder demostrar el envío y ayudarte si algo no te llega: la copia se borra a los 6 meses y el registro a los 24. ⟨si clics⟩ En los correos de tus reservas contamos qué enlaces pulsas, para saber qué información os sirve; la base es nuestro interés legítimo (art. 6.1.f RGPD) y puedes oponerte en «Mi cuenta → Privacidad». ⟨/si⟩ ⟨si apertura⟩ Si aceptaste las cookies de análisis, una imagen diminuta nos dice cuándo abres el correo; sin esa aceptación, el correo sale sin ella (art. 6.1.a RGPD). ⟨/si⟩ Ni los clics ni las aperturas guardan tu dirección IP ni tu navegador, y la encuesta no lleva ninguna de las dos marcas. [PENDIENTE: asesoría — clics por persona con interés legítimo y oposición]

**15. Comunicaciones comerciales y cómo darte de baja** (cambia)
> Solo te enviamos novedades y ofertas si lo aceptas expresamente en «Mi cuenta → Privacidad», nunca con la casilla marcada de antemano (art. 6.1.a RGPD y art. 21 de la LSSI). Puedes retirarlo allí mismo con un clic, desde el enlace de baja que encontrarás en cada correo comercial, o escribiéndonos a :legal_email. ⟨si felicitaciones⟩ Con ese mismo permiso podemos felicitarte el cumpleaños, a ti y a los menores a tu cargo. ⟨/si⟩ No vendemos ni cedemos tus datos para la publicidad de otros.

**16. Medición de la web, análisis y publicidad** (nuevo; sustituye a «ni elaboramos perfiles»)
> Para saber cuánta gente visita la web y cómo reserva, usamos una medición propia y de conjunto: una cookie con un identificador aleatorio, que dura 13 meses, y unos datos que se borran a los 25 meses, sin cruzarlos con otros ni cederlos; con ella probamos también versiones distintas de algunas páginas. Solo si lo autorizas en el aviso de cookies (categoría «análisis»), vinculamos tu navegación a tu cuenta para entender cómo usas la web y mejorarla ⟨si herramienta⟩, con :analytics_tool ⟨/si⟩; puedes retirarlo cuando quieras en «Mi cuenta → Privacidad» o en la configuración de cookies, y desvinculamos lo registrado. ⟨si publicidad⟩ Solo si aceptas la publicidad (categoría «marketing»), :advertisers miden si un anuncio terminó en una reserva: sus píxeles cargan en la web y reciben el código del pedido al pagar. ⟨si conversión de servidor⟩ A :server_platforms se lo enviamos además desde nuestro servidor, con tu correo y tu teléfono convertidos en una huella (SHA-256) que solo sirve para emparejarlos con las cuentas que esa plataforma ya tenga. ⟨/si⟩ ⟨/si⟩ El detalle de cada cookie está en la Política de cookies. [PENDIENTE: asesoría — la huella sigue siendo un dato personal; la corresponsabilidad con cada plataforma]

**17. Las opiniones de Google que mostramos** (nuevo; para quien reseña, art. 14)
> ⟨si opiniones⟩ En la web mostramos opiniones publicadas sobre el parque en Google, con el nombre y la foto de su autor tal como aparecen allí y la respuesta del parque. Las leemos de nuestro Perfil de Empresa de Google y no las guardamos más de 30 días; si una deja de estar publicada, el nombre y la foto dejan de mostrarse en 3 días como mucho. La base es nuestro interés legítimo en enseñar qué opinan quienes ya nos visitaron (art. 6.1.f RGPD). Si escribiste una y no quieres que salga aquí, escríbenos a :legal_email y la ocultamos. ⟨/si⟩

**18. Contacto** (nuevo)
> Si nos escribes o nos llamas, usamos tus datos para contestarte (art. 6.1.f RGPD, o el contrato si es sobre tu reserva). ⟨si WhatsApp⟩ Si nos escribes por WhatsApp, ese servicio lo presta Meta, que trata tus mensajes según sus propias condiciones. ⟨/si⟩

**19. Destinatarios y encargados del tratamiento** (cambia; compuesto)
> No vendemos tus datos. Los tratan por cuenta nuestra, como encargados del tratamiento y solo para prestarnos su servicio: Redsys y la entidad bancaria, que procesan el cobro; nuestro proveedor de alojamiento, que guarda la web y la base de datos en servidores de :hosting_country; y el proveedor de la plataforma de reservas, para su mantenimiento y soporte [D6]. Los correos salen ⟨desde nuestros propios servidores | a través de :mail_provider⟩. ⟨si antibot⟩ Cloudflare protege los formularios frente a bots. ⟨/si⟩ ⟨si fuentes⟩ Bunny Fonts (BunnyWay d.o.o., Eslovenia, UE) sirve las tipografías de algunas páginas: recibe la dirección IP de tu navegador y no usa cookies. ⟨/si⟩ Con tu consentimiento, también ⟨si mapa⟩ Google, que muestra el mapa ⟨/si⟩ ⟨si herramienta⟩ y :analytics_tool, para el análisis ⟨/si⟩ ⟨si publicidad⟩ y :advertisers, para la publicidad ⟨/si⟩. Y las autoridades, cuando una ley nos obligue.

**20. Transferencias internacionales** (cambia; compuesto)
> Algunos de estos proveedores tienen su sede en Estados Unidos: ⟨Google LLC⟩ ⟨Cloudflare, Inc.⟩ ⟨Meta Platforms, Inc.⟩. Están adheridos al Marco de Privacidad de Datos UE-EE. UU. (Data Privacy Framework), que la Comisión Europea reconoce como garantía adecuada. ⟨si TikTok⟩ [PENDIENTE: asesoría — el mecanismo de TikTok] ⟨/si⟩ ⟨si herramienta⟩ [PENDIENTE: asesoría — dónde trata los datos la herramienta de análisis] ⟨/si⟩ El alojamiento está en la Unión Europea, así que no implica ninguna transferencia.

**21. Cuánto tiempo guardamos tus datos** (cambia)
> Tu cuenta, mientras la mantengas (al suprimirla, la anonimizamos). Los pedidos y las facturas, seis años desde su fecha (art. 30 del Código de Comercio), bloqueados y solo a disposición de las autoridades. Los invitados de un cumpleaños, 14 días después de la fiesta. Las respuestas a una invitación, 14 días después de la fiesta. Los descargos y las autorizaciones firmados, el plazo de prescripción [D2] (el de un menor, desde que cumple 18 años). Los códigos para entrar, 24 horas; la sesión, 2 horas sin uso, o 90 días si pediste mantenerla. El registro de los correos, 24 meses (la copia, 6). Las encuestas, 90 días unidas a ti y 24 meses sin nadie. La medición de la web, 25 meses. Tus decisiones sobre cookies, 24 meses. Las opiniones de Google, 30 días. Los registros de seguridad, [D9].

**22. Tus derechos** (cambia)
> Puedes ejercer en cualquier momento los derechos de acceso, rectificación, supresión, oposición, limitación del tratamiento y portabilidad, y retirar los consentimientos que hayas dado, sin que eso afecte a lo hecho antes. Muchos los ejerces tú desde «Mi cuenta»: descargar una copia de tus datos en un fichero legible por máquina (acceso y portabilidad); corregir tu nombre, teléfono, fecha de nacimiento, idioma o correo; en «Privacidad», oponerte al análisis y a las encuestas y retirar el permiso de comunicaciones comerciales; y suprimir tu cuenta. Al suprimirla no borramos tus pedidos, porque las facturas deben conservarse: anonimizamos tu cuenta (quitamos tu nombre, correo, teléfono y fecha de nacimiento, retiramos tus consentimientos, cerramos tus sesiones y tu carné y desvinculamos Google y tu navegación) y borramos los datos de tus invitados, tus invitaciones, las copias de tus correos y los menores a tu cargo que no tengan un descargo firmado. Si tienes una reserva pagada pendiente de celebrar, primero hay que cancelarla o esperar a que pase. Lo que la ley nos obliga a conservar, o lo que acredita un descargo firmado, queda apartado y con acceso restringido. Para cualquier otro derecho, o si prefieres pedirlo por escrito, escríbenos a :legal_email; podremos pedirte que acredites tu identidad.

**23. Decisiones automatizadas y elaboración de perfiles** (cambia)
> No tomamos decisiones automatizadas que produzcan efectos jurídicos sobre ti o te afecten de forma significativa. Fuera de lo que cuenta «Medición de la web, análisis y publicidad», que solo ocurre con tu autorización, no elaboramos perfiles con tus datos.

**24. Medidas de seguridad** (cambia)
> Aplicamos medidas técnicas y organizativas para proteger tus datos. No hay contraseñas que robar: entras con un código de un solo uso que caduca a los 10 minutos y admite 5 intentos, y limitamos cuántos códigos se piden y cuántos intentos se hacen por correo y por dirección IP. Si cambias de correo, no sustituimos el actual hasta que confirmas el nuevo con un código, y avisamos al anterior. Desde «Mi cuenta» puedes cerrar tus sesiones en otros dispositivos. Protegemos los formularios públicos frente a bots, aplicamos cabeceras de seguridad y una política de seguridad de contenido (CSP), registramos las acciones del personal sobre tus datos y, en nuestros registros internos, no guardamos datos personales en claro, sino cifrados o reducidos al mínimo.

**25. Reclamación ante la autoridad de control**
> Si consideras que el tratamiento de tus datos no se ajusta a la normativa, tienes derecho a presentar una reclamación ante la Agencia Española de Protección de Datos (AEPD), C/ Jorge Juan, 6, 28001 Madrid, o en su sede electrónica, www.aepd.es. Te agradeceremos que antes nos escribas a :legal_email para intentar resolverlo.

#### 4.2.2 Condiciones de uso y compra

**1. Objeto y aceptación** (cambia)
> Estas Condiciones de uso y compra («Condiciones») regulan el uso de esta web y la compra de entradas, cumpleaños, excursiones y demás productos de :business_name. Al usar la web, crear una cuenta o hacer un pedido, declaras haberlas leído y aceptado; si no estás de acuerdo, no debes usar la web ni comprar en ella. Podemos actualizarlas para adaptarlas a cambios legales u operativos: a tu compra se le aplica la versión vigente cuando la haces. [PENDIENTE: asesoría — ¿aceptación en cada compra o basta la del alta?]

**2. Titular y datos de contacto**
> El titular de la web y responsable de la venta es :legal_name, con NIF :legal_nif y domicilio en :legal_address. Para cualquier consulta, reclamación o ejercicio de derechos sobre la web o tu pedido, escríbenos a :legal_email o llámanos al :legal_phone.

**3. Tu cuenta** (cambia: sin contraseña y sin la frase falsa del descargo)
> Para reservar necesitas una cuenta con tu nombre y tu correo electrónico, y tu teléfono en los productos que lo piden. Entras con un código de un solo uso que te enviamos al correo ⟨si Google⟩ o con tu cuenta de Google ⟨/si⟩; si marcas «Mantener la sesión iniciada», ese dispositivo queda recordado hasta 90 días sin uso. Te comprometes a dar datos veraces y a no compartir tus códigos ni tu carné; eres responsable de lo que se haga desde tu cuenta.

**4. Productos y servicios** (cambia)
> En la web puedes comprar entradas para una zona y una franja horaria con aforo limitado, reservar cumpleaños y excursiones de colegio y añadir complementos. La ficha de cada producto dice para quién es (edades y alturas), qué incluye, cuánto cuesta, si pide una señal, el mínimo y el máximo de personas y hasta cuándo puedes cancelar; vale lo que diga cuando compras. Las ofertas y los regalos valen en las fechas que indique cada uno y, cuando así se diga, hasta agotar existencias. Procuramos que todo sea correcto, pero puede haber errores: si alguno afecta a tu pedido, te avisaremos y, si hace falta, lo anularemos y te devolveremos lo pagado.

**5. Precios e impuestos** (cambia)
> Los precios están en euros e incluyen el IVA (:tax_rate). El precio puede depender del día, de la franja o de la tarifa (por ejemplo, la de fines de semana y festivos); «desde X €» es el precio más bajo posible, y el que pagas es el que ves en el resumen antes de pagar.

**6. Proceso de compra y confirmación**
> Eliges el producto, el día y la franja, revisas el resumen y el total, y pagas. Mientras pagas, tu plaza queda guardada durante un tiempo limitado; si no terminas, el pedido caduca y la plaza se libera. El contrato queda cerrado cuando se confirma el pago: entonces te enviamos la confirmación y, cuando corresponda, tus entradas.

**7. Pago y señal** (cambia)
> Se paga online a través de la pasarela segura de Redsys; nosotros no conocemos ni guardamos el número de tu tarjeta. En los productos con señal, pagas online la señal y el resto en el parque el día de la reserva. Si el cobro no se completa, el pedido no queda confirmado.

**8. Sin derecho de desistimiento** (nuevo) [PENDIENTE: asesoría]
> Las entradas, los cumpleaños y las excursiones son servicios de ocio para una fecha concreta, por lo que no tienen el derecho de desistimiento de 14 días de las compras a distancia (art. 103.l del texto refundido de la Ley General para la Defensa de los Consumidores y Usuarios). Puedes cancelar o cambiar tu reserva en las condiciones del apartado «Cancelaciones, cambios y reembolsos».

**9. Aforo y franjas horarias**
> Las entradas y reservas están sujetas a disponibilidad y al aforo de cada franja. Una entrada para una franja no da acceso fuera de ella. Podemos modificar horarios, cerrar fechas o ajustar el aforo por motivos operativos, de mantenimiento o de seguridad; si eso afecta a una reserva confirmada, te ofreceremos otra fecha o la devolución de lo pagado.

**10. El descargo de responsabilidad y los menores** (nuevo)
> Para usar las atracciones, cada participante necesita un descargo de responsabilidad firmado: lo firmas en la web, para ti y para los menores a tu cargo, o en el parque ante nuestro personal. Los menores que vienen invitados a una fiesta o a una excursión necesitan la autorización de su padre, madre o tutor, que se firma desde el enlace que se les envía. En la entrada te identificas con el carné de tu cuenta y comprobamos que todo está firmado: sin descargo firmado no se pueden usar las atracciones, y si no lo has firmado puedes hacerlo en el parque ante nuestro personal. Las edades, las alturas y quién tiene que entrar con un adulto son las de las normas del parque.

**11. Cumpleaños: invitados, invitación y cambios** (cambia)
> Tras reservar un cumpleaños recibes el enlace al formulario de la reserva, donde nos cuentas quién viene y lo que necesitamos saber (por ejemplo, alergias). Hasta el plazo que indique tu reserva puedes cambiar ahí el número de invitados, sin bajar del mínimo ni pasar del máximo del pack, y añadir complementos; lo que sume se paga en el parque. ⟨si invitación⟩ Si el pack incluye invitación digital, puedes compartir su enlace con las familias para que contesten. ⟨/si⟩ Los datos de los invitados los aportas tú, o cada familia en la invitación, y solo se usan para preparar la fiesta, como explica la Política de privacidad.

**12. Excursiones de colegio** (nuevo)
> El centro reserva para un grupo, entre el mínimo y el máximo que indique la ficha, con un precio por alumno que depende del tamaño del grupo y del día. Cada familia firma la autorización de su hijo o hija desde el enlace que le hace llegar el centro.

**13. Cancelaciones, cambios y reembolsos** (cambia: sin cifras escritas a mano)
> Los cambios y las cancelaciones los gestiona el personal del parque a petición tuya, en :legal_email, por teléfono o en el parque; no hay cancelación automática desde tu cuenta. Cada producto indica hasta cuándo puedes cancelar o cambiar tu reserva (por ejemplo, «hasta 24 h antes» o «hasta 3 días antes»), en su ficha y en «Mi cuenta». Dentro de ese plazo, te devolvemos lo que corresponda [D10] y, si el producto lo indica, la señal; fuera de plazo, o si no te presentas, la señal no se devuelve. Si cambias la fecha, se aplica el precio de la nueva fecha. Lo que reduzca el importe de un pedido pagado se te devuelve al mismo medio de pago, y lo que lo aumente se paga en el parque.

**14. Normas y conducta**
> Te comprometes a usar la web y los servicios de forma lícita y conforme a estas Condiciones, sin dar datos falsos ni usar las entradas de forma fraudulenta. Dentro del parque hay que respetar las normas de uso y de seguridad, que puedes consultar en la web, y las indicaciones del personal; incumplirlas puede suponer que no se permita o se retire el acceso, sin devolución.

**15. Responsabilidad y disponibilidad del servicio**
> Procuramos que la web esté disponible y que su información sea correcta, pero no podemos garantizar que no haya interrupciones o errores; podemos suspenderla temporalmente por mantenimiento o por causas técnicas. No respondemos de los daños causados por un uso indebido de la web o de los servicios, ni de causas de fuerza mayor. Nada de lo que dicen estas Condiciones limita la responsabilidad que la ley no permite excluir.

**16. Propiedad intelectual**
> Los contenidos de la web (textos, imágenes, logotipos, marcas y diseño) pertenecen a su titular o a sus licenciantes y están protegidos por la normativa de propiedad intelectual e industrial. No se permite reproducirlos, distribuirlos ni usarlos sin autorización.

**17. Protección de datos**
> El tratamiento de tus datos se rige por nuestra Política de privacidad, que explica para qué los usamos, con qué base, cuánto tiempo los guardamos y cómo ejercer tus derechos.

**18. Legislación aplicable y reclamaciones** (cambia: sin la ODR)
> Estas Condiciones se rigen por la ley española. Si eres consumidor, puedes reclamar ante los juzgados de tu domicilio; en otro caso, las partes se someten a los de :jurisdiction. También puedes reclamar ante los servicios de consumo de tu comunidad autónoma ⟨si arbitraje⟩ o ante la Junta Arbitral de Consumo, a la que estamos adheridos ⟨/si⟩, y tienes hojas de reclamaciones a tu disposición en el parque. [D7 · PENDIENTE: asesoría]

#### 4.2.3 El aviso legal

Solo cambia su francés (de «vous»). Su español no dice nada falso medido hoy.

### 4.3 Cómo llega al código (después del ✅ del owner y de la asesoría)

1. **`LegalContent`** con el texto nuevo (es; después en y fr, con «vous»).
2. **Las piezas compuestas**: un inventario como `CookieInventory` —`PrivacyInventory` (futuro)— que lee la configuración
   (`Drivers`, `Pixels`, `EmailClickMarks`, `EmailOpenMarks`, las claves del antibot, el acceso con Google, el mapa, el
   remitente del correo, las opiniones, la invitación, «Avísame de fechas») y viaja en `GET /legal/documents/{slug}` como
   lo hace `inventory` en `/cookies`. ⚠️ La trampa (1) de `/cookies`: la vista de la instancia (`web/legales.blade.php`) tiene
   que pintar las piezas, o se pierden en PlayJump. Dos datos nuevos de la instalación: el país del alojamiento y, si lo hay,
   el proveedor del correo (futuro).
3. **Una instalación con el texto de fábrica**: migración por huella (la de cada versión anterior), como las de `/cookies`.
4. **PlayJump**: su texto vivo NO es de fábrica → un script gitignorado con la huella ESPERADA de cada idioma (transacción;
   una segunda pasada aborta), al desplegar la v2.0.0, apuntado en `ENTORNOS.md` §6 junto al de `/cookies`.
5. **Sin aviso a las cuentas**: solo se publica el texto (`#864`).
6. **Guardas** (futuro): un censo de tratamientos (cada fila de §1.2, su apartado); «contraseña», «password» y «mot de
   passe» fuera del texto tras la A5; la ODR fuera; el francés sin tuteo; cada pieza compuesta sale con su interruptor y
   no sin él (con control); y la sonda de `/privacidad` y `/condiciones` en es/en/fr.

### 4.4 Lo que el texto NO arregla (código aparte, con su propia tanda)

- **`#863` (D1, decidida)**: una poda nueva de `guest_data`/`event_data` 14 días después de la fiesta (como
  `InvitationReply`), con su guarda y su mutación; toca RGPD-01. ⚠️ Hasta que exista, el apartado 6 de la privacidad
  nueva no se puede publicar: prometería lo que el código no hace (la misma falta que §1.3·2).
- **D9**: `audit_logs` no tiene plazo: una poda (propuesta: 24 meses, como el consentimiento de cookies).
- **El aviso de desistimiento en la compra**, antes de pagar (art. 97.1.n): una línea en el paso de pagar de la isla y del
  cajón. [PENDIENTE: asesoría — si basta con las Condiciones aceptadas]
- **D2**: `waiver.retention_months` y `waiver.dependent_retention_months` se configuran en el panel de producción con lo
  que diga la asesoría (hoy, sin medir: vacío = nada se poda).
- **El enlace de baja en cada correo comercial** (art. 21 LSSI): el texto lo promete; «Avísame de fechas» ya lo lleva y
  los comerciales del SPA (su C1) tienen que llevarlo cuando existan.
- **Lo medido en el código vs. la pieza**: la conversión de Google Ads sale del navegador con el código del pedido y nada
  más; la huella del correo y del teléfono solo viaja en la conversión de servidor (Meta y TikTok, RGPD-07).

## 5. Impacto en invariantes

- **El texto**: ninguno; lo describe (RGPD-01, RGPD-03, RGPD-07).
- **`#863` (D1)**: RGPD-01 gana una poda (los datos de los invitados ya no viven hasta la supresión) y su celda lo dice;
  `PrivacyTest` y el censo, con su caso.
- **D9 aprobada**: una poda de `audit_logs`; RGPD-02 no cambia (ya no lleva PII en claro).

## 6. Plan de verificación empírica

- **Ahora (el documento)**: cada apartado de §4.2 casa con una fila de §1.2 o con un hecho medido en §1; el owner lo lee
  entero.
- **Después (el código)**: las guardas de §4.3·6 vistas morder (`/mutar`); la sonda de las dos páginas en es/en/fr con
  cada interruptor encendido y apagado; en local, el script de PlayJump contra una copia del texto vivo (pasa, y la
  segunda pasada aborta); en producción, la lectura de las dos páginas tras desplegar.

## 7. Revisión y decisión

**Lo que decide el owner** (la recomendada, primero):

- **D1 · Los invitados de un cumpleaños** (nombres y alergias de menores): `[DECIDIDO owner]` 2026-10-01 (`#863`) —
  **se borran 14 días después de la fiesta**, como las respuestas de la invitación (descartado: conservarlos con el pedido
  hasta la supresión).
- **D2 · El plazo del descargo**: los años de prescripción que fije la asesoría, configurados en el panel.
- **D5 · Avisar a las cuentas** del cambio: `[DECIDIDO owner]` 2026-10-01 (`#864`) — **solo se publica el texto**, sin
  correo ni aviso en la cuenta (descartado: el correo y el aviso del cajón de la T3a·4, que sigue para lo suyo, RGPD-07).
- **D6 · Nombrar** al proveedor del alojamiento y al de la plataforma, o solo su categoría (recomendado: la categoría en el
  texto y el nombre en el contrato de encargo, art. 28).
- **D7 · El arbitraje de consumo**: si el parque está adherido.
- ~~**D8 · Sin descargo firmado**~~: ya decidido —«la exención se pide al 100 %» (`[DECIDIDO owner]`, `specs/auth-con-google.md`
  §4), y se puede firmar en el parque ante el personal (`#336`)—. El texto de §4.2.2·10 dirá: «sin descargo firmado no se
  pueden usar las atracciones; si no lo has firmado, puedes hacerlo en el parque ante nuestro personal».
- **D9 · Los registros de seguridad**: el plazo (recomendado: 24 meses, como el consentimiento de cookies; técnico, lo
  confirma la asesoría).
- **D10 · Dentro del plazo de cancelación**: el texto nuevo no lleva cifras (las dice cada producto). Medido en
  playjump.es (01-10, «¿Puedo cambiar o cancelar?»): «Las entradas, con al menos 24 horas de antelación. Los cumpleaños y
  las excursiones, con 5 días: te devolvemos la señal a la tarjeta o cambiamos la fecha» — contra los «3 días» de las
  condiciones vivas y de `#699`: va a la REVISIÓN FINAL del owner con el dueño (ya estaba en su lista), no bloquea esto.

- **D11 · Las dos frases falsas que producción publica HOY** (§1.3, 1 y 2): `[DECIDIDO owner]` 2026-10-01 (`#865`) — se
  corrigen al desplegar la v2.0.0, con el script de §4.3·4 (descartado: corregirlas ya en el panel).

**Lo que valida la asesoría**: cada `[PENDIENTE: asesoría]` de §4.2 y §4.4, las bases jurídicas propuestas, los plazos
y el aviso de desistimiento.
