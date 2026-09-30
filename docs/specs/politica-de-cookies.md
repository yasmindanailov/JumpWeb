# [SPEC] La política de cookies de producción — medida, y que no pueda mentir

> Estado: ✅ §1–§4 (validada por el owner, 30-09) · ⬜ §6 en curso · Decisiones: `#858` (el owner: «Mantener la sesión
> iniciada», sin marcar) · `#859` (el listado lo compone la configuración; el texto llega por huella) · `#860` (el owner:
> el aviso y «Configurar» piden solo lo encendido) · Carril:
> plataforma (el encargo del owner, 30-09: «los redactarás tú, con rigor y profesionalidad, con los datos del cliente»); el
> texto y el consentimiento eran del SPA (T3 de la analítica), avisado en el buzón · Sistema: `docs/sistemas/COOKIES.md`.

## §0 · Antes de tocar

- **La regla**: la política dice lo que la web pone DE VERDAD en ESTA instalación. El texto (la BD, editable) explica; el
  LISTADO de cookies no se escribe a mano: lo compone el servidor con la configuración de la instalación
  (`CookieInventory`) y viaja en `GET /legal/documents/cookies` (`inventory`), que pintan la vista del producto y la de la
  instancia. Así un mapa, un anti-bot, una herramienta de análisis o un píxel aparecen en la política cuando se encienden.
- **Empieza por** §1 (lo medido) → §3 (el diseño) → §4 (el texto) → §6 (el aviso pide solo lo encendido, `#860`).
- **Trampas**: (1) la vista de la instancia (`web/legales.blade.php`, `#844`) pintaba solo las secciones: los párrafos de
  la herramienta y los píxeles activos (T3b·3) se perdían en PlayJump. (2) La página vive en la BD: el texto nuevo llega a
  una instalación existente por una migración QUIRÚRGICA que no pisa lo que la clienta editó. (3) `cookie_consent` pide 24
  meses y Chrome la recorta a 400 días: la política habla de la DECISIÓN (24 meses), no de la cookie. (4) Lo que la
  configuración local no enciende (anti-bot, redes, análisis, píxeles) no se mide aquí: lo dice el listado al encenderse.
- **Medir**: `node scripts/sonda-inventario-cookies.mjs` (seis escenarios; sale con 1 si una cookie no está declarada).
- **Invariantes**: el bloqueo previo y «sin muro de cookies» (`COOKIES.md` §2) no cambian; no se sube `POLICY_VERSION`
  (ninguna finalidad nueva: la del mapa se ESTRECHA, ya sin reseñas).

## 1. Lo medido (30-09, local, `sonda-inventario-cookies.mjs`)

Sin decidir, rechazando, aceptando, entrando con el código (con y sin la casilla) y abriendo el alta; 12 páginas:

| Cookie | Duración medida | Cuándo |
|---|---|---|
| sesión (`config('session.cookie')`, aquí `jumpweb-session`) | 2 h | siempre |
| `XSRF-TOKEN` | 2 h | siempre |
| `visitor_id` | 13 meses | siempre (medición propia exenta) |
| `cookie_consent` | 400 días (pide 24 meses) | al decidir en el aviso |
| `remember_web_<hash>` | 90 días | solo con «Mantener la sesión iniciada» (`#858`) |

Terceros: **ninguno** sin consentimiento. Aceptando, solo Google Maps (`maps.googleapis.com`, `maps.gstatic.com`,
`www.google.com` y, desde el mapa, `fonts.googleapis.com`). Las reseñas ya son nuestras (`#771`); las fuentes de la landing
nueva, propias; las páginas del armazón del producto (404, encuestas, reintento de pago) piden Bunny Fonts, que NO pone
cookies (medido: su hoja responde 200 sin `Set-Cookie`), así que no entra en el listado.

## 2. Lo que decía y era falso

«Solo si marcas recuérdame» (no había casilla desde la A1); el mapa CON las reseñas de Google (`#771`/`#772`); el widget de
redes como si estuviera (depende de la configuración). Y en la PRIVACIDAD, fuera de este encargo: la contraseña del
cliente (se retira en la A5).

## 3. Diseño

- `App\Http\Legal\CookieInventory::rows(string $locale)` (en `Http`: lee de Identidad, Contenido y Plataforma, y
  `ModuleBoundariesTest` no deja que dos dominios se miren): las filas de ESTA instalación —propias (sesión,
  XSRF, medición, decisión, recuerdo) y de tercero solo si están encendidas (Redsys al pagar; Turnstile; el mapa; el
  widget de redes; la herramienta de análisis; cada píxel activo)— con nombre, titular, finalidad, duración, categoría y
  cuándo. Textos en `lang/*/cookies.php` (`inventory.*`). La duración de la sesión sale de su configuración.
- `GET /legal/documents/cookies` gana `inventory` (contrato 1.58.0, con `#858`); las vistas lo pintan como TARJETAS (una por cookie,
  legible a 360), tras el texto. Los párrafos de la herramienta y de los píxeles activos se funden en el listado.
- El texto (`CookiePolicyContent`) se reescribe sin listas que envejecen; su migración quirúrgica lo sustituye solo si
  el guardado es EXACTAMENTE el que sembró el producto (por su huella), y si no, lo avisa.
- La sonda compara lo medido con lo declarado.

## 4. El texto, y cómo llega

- `CookiePolicyContent` (la «v5»): qué son; el responsable (tokens, los datos del cliente del panel); qué usamos, con el
  listado al final; las necesarias (art. 22.2 LSSI) con Redsys y el anti-bot en condicional; «Mantener la sesión
  iniciada» (`#858`); la medición exenta; y cada categoría con permiso con el MISMO nombre que su interruptor del aviso
  («Mapa (Google)», «Redes sociales», «Análisis de uso identificado», «Publicidad»), en condicional; las transferencias,
  remitiendo a la garantía de cada fila; cómo retirar; 24 meses. Francés de usted.
- El aviso y su panel: la categoría del mapa es ya solo el mapa («Mapa (Google)»; la clave `maps` no cambia). La finalidad
  se ESTRECHA: sin subir `POLICY_VERSION`.
- **A una BD sembrada**: `2026_09_30_150000_cookie_policy_for_production` sustituye cada idioma cuya huella es la de la v4
  (congelada en `tests/Support/CookiePolicyV4.php`); lo editado se queda y va al registro (`cookies.policy_not_updated`).
- **PlayJump**: su español y su inglés llevan el párrafo de transferencias reescrito en `#592` («no incrustamos contenido
  de redes sociales…»), así que la migración solo cambia el francés. El script gitignorado `aplicar-produccion-cookies.php`
  (en el `storage/app/` del owner; huella ESPERADA por idioma, transacción, la segunda pasada aborta: probado en local) los lleva al texto nuevo **tras el `migrate` de la v2.0.0** (`ENTORNOS.md` §6).
- Guardas: `CookieInventoryTest`, `CookiePolicyContentTest` (la cadena de migraciones, la huella), `DriversTest` y
  `PixelsTest` (lo activo, nombrado); `scripts/mutar-politica-cookies.sh` 14/14; la sonda, mutada a mano (sin la fila de
  `visitor_id`, sale con 1 y lo nombra).

## 5. Pendiente, fuera de este encargo

- La **política de privacidad** (`LegalContent`) habla de la contraseña del cliente (se va en la A5), y su francés es de
  tú. (Bunny Fonts, que nombra, SÍ se pide: en las páginas del armazón, §1.)
- `[PENDIENTE: asesoría]`: las garantías de transferencia de cada tercero (las de la T3b, conservadas).

## 6. El aviso y «Configurar» piden solo lo encendido (`#860`, `[DECIDIDO owner]` 30-09)

Medido: el aviso de la isla decía «…y enseñarte nuestros anuncios en otras webs» y los dos «Configurar» ofrecían SIEMPRE las
cuatro categorías (`CookieConsent::OPTIONAL`, en `site/body-state`), hubiera o no mapa, widget o píxeles. El owner: «así lo
haremos, si es lo más profesional y estándar».

- **Qué se ofrece**: `CookieInventory::offered()`, las de `OPTIONAL` con algo detrás, con las MISMAS condiciones que el
  listado: `maps` (mapa configurado), `social` (widget configurado), `marketing` (algún píxel activo) y `analytics`
  SIEMPRE (medido: el régimen identificado —`AccountAnalytics` ata la navegación a la cuenta al entrar— y la apertura de
  los correos, `EmailOpenMarks`, son propios y no tienen interruptor; la herramienta, si la hay, va dentro).
- **Por dónde llega**: `site/body-state` escribe `offered()` en `data-consent-categories`, de donde leen el almacén
  (`ui/cookie-consent.js`, sin cambios) y los dos paneles; los textos del aviso (`banner.text` del clásico y los dos de la
  isla) se COMPONEN con las mismas categorías. Sin nada que pedir, sin aviso.
- **El servidor manda**: `CookieConsentController` valida solo las ofrecidas y guarda las demás en `false`; la cookie
  anota qué se preguntó (`asked`). Una cookie válida sin `asked` es de antes de `#860`: se preguntaron las cuatro.
- **Una categoría que se enciende DESPUÉS**: quien ya decidió vuelve a ver el aviso (hay una pregunta nueva; lo que ya
  contestó sigue marcado) y, hasta contestar, la nueva está apagada. Por eso no se sube `POLICY_VERSION`.
- **El píxel de APERTURA de los correos** (`EmailOpenMarks`, `#797`: su interruptor `emails.track_opens`, apagado de fábrica,
  y el «sí» a `analytics` de la cuenta; su doc: «`/cookies` tiene que nombrarlo antes»): encendido, entra como fila del
  listado (categoría análisis) y la descripción de «Análisis» lo dice; apagado, no aparece.
