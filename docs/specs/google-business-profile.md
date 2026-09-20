# [SPEC] Google Business Profile — las reseñas de la ficha siempre a la vista, y el horario publicado desde el panel

> Estado: ✅ **APROBADA POR EL OWNER tras una revisión adversarial de cinco lentes** (2026-09-11) ·
> **código NO empezado** · Decisión asociada: `DECISIONES #524`.
>
> ▶ **Esta es la versión reescrita tras la revisión.** La versión que pasó a revisión está en el historial
> (`3b31c45d`) y la revisión con sus citas, en §10. **Donde una versión anterior diga otra cosa, manda ésta.**
>
> ▶ **Sustituye como FUENTE a `google-reviews.md`** (Places API), que queda como registro de lo construido
> en `#490`/`#491`/`#494`. El contrato `Content\Contracts\SocialProof` se conserva, **pero cambia** (§4.3·9):
> el decorador, el contrato y la vista se tocan.
>
> ❗❗❗ **Lo que hay que saber si solo se leen seis líneas:**
> 1. **Fuente: la API de Google Business Profile**, la del dueño de la ficha, con un **proyecto CENTRAL de
>    JumpSystem** (`#719`) —la política prohíbe pedirle a cada cliente el suyo— al que cada parque solo le da
>    permiso, tras añadir esa cuenta como **administrador** de su ficha.
> 2. **Se publican TODAS las reseñas** (filtradas por estrellas, con fotos, anónimas incluidas) **sin pedir
>    permiso a cada autor**, como hace el sector. ⚠️ **La guía de marca de Google pide ese permiso**: es un
>    **riesgo aceptado por el owner** (§8·R1), mitigado con «Ocultar» en el panel.
> 3. **Nada de Google vive más de 30 días**, y el límite se aplica **al leer**, no solo al purgar.
> 4. **El horario se publica en Google con UN BOTÓN** del admin: Google exige consentimiento previo y
>    específico para cada edición. La comprobación diaria **solo avisa**.
> 5. **Toda escritura en Google, solo en PRODUCCIÓN**; desarrollo y staging usan **otro proyecto**: revocar
>    un permiso retira la autorización de todo el proyecto.
> 6. **Las entradas se configuran a mano en la ficha**: la API no tiene tipo «entradas».

---

## §0 · Antes de tocar

- **Aprobada por el owner tras revisión adversarial de cinco lentes** (`#524`); **código NO empezado**; las
  reseñas van DESPUÉS del diseño (`[owner]`). Sustituye como FUENTE a `google-reviews.md` (Places), que queda
  como registro; el contrato `Content\Contracts\SocialProof` se conserva pero cambia (§4.3·9). El programa
  `producto-e-instancias.md` (`#616`) la confirma: mecanismo del producto, API pública sin avatares.
- ⚠️⚠️ **La identidad ante Google es JumpSystem, no JumpWeb** (`#719`): cuenta, dominio y web propios. Y
  **no necesita ficha** —vale la de un cliente que gestionas—, así que el parque añade esa cuenta como
  **ADMINISTRADOR** de la suya (medido 20-09; antes se daba por gestionada). El **vídeo** de verificación
  exige el flujo desplegado → va **tras la T1**; la **solicitud** (§7·A·2) no, y es lo que más tarda.
- **Las seis líneas**: (1) la API del DUEÑO de la ficha con un proyecto CENTRAL de JumpSystem (la política prohíbe
  pedirle el suyo a cada cliente); (2) se publican TODAS las reseñas sin pedir permiso al autor —riesgo ACEPTADO
  por el owner frente a la guía de marca, mitigado con «Ocultar»—; (3) nada de Google vive más de 30 días y el
  límite se aplica AL LEER; (4) el horario se publica con UN botón (Google exige consentimiento por edición);
  (5) toda escritura solo en PRODUCCIÓN, desarrollo en OTRO proyecto (revocar retira la autorización de todo el
  proyecto); (6) las entradas se configuran a mano en la ficha.
- **La app OAuth va «En producción»**: en prueba el permiso caduca a los 7 días y la sincronización muere en
  silencio. El texto de una reseña puede traer la traducción de Google mezclada: se mide antes de analizarlo.
- Tandas T1 conexión → T2 reseñas → T6 horario (T5 entradas, sin código); §7 la guía (A: JumpWeb una vez · B:
  cada parque); §8 las decisiones; §10 el registro de la revisión. Anexo al final con la fila del enrutador.

## 1. Contexto y problema — MEDIDO

### 1.1 Lo que hay hoy

    fuente          Places API (New) · Place Details con `reviews`        `GoogleSocialProof`
    caché           30 min (`CACHE_TTL_SECONDS`), Redis `allkeys-lru`
    refresco        cada 3 h (`social-proof:refresh`), una llamada por idioma
    umbral          1 reseña (`MIN_REVIEWS`, `[DECIDIDO owner]` #494, definitivo)
    consentimiento  las reseñas solo con la categoría `maps` aceptada     por el avatar (`RGPD-05`)
    respaldo        opiniones propias (`testimonials`, `CmsSocialProof`)

### 1.2 Por qué no salen siempre

| # | Causa | Medido |
|---|---|---|
| C1 | Caché de 30 min contra refresco cada 3 h | vacía **150 de cada 180 min** (`#499`) |
| C2 | Places **no permite** guardar valoraciones ni reseñas | política leída el 2026-09-11 |
| C3 | Sin la categoría `maps` aceptada no se pintan (el avatar es una petición del visitante a Google) | `#491`, `FallingBackSocialProof`, `AppServiceProvider` |
| C4 | En desarrollo, la clave de Places rechaza la IP (403 `API_KEY_IP_ADDRESS_BLOCKED`) | `#523`; `[owner]`: en local no hace falta |

⚠️ Y la causa de fondo: el parque tiene **2 reseñas** en Google.

### 1.3 Places contra Business Profile (documentación oficial, 2026-09-11)

| | Places (lo de hoy) | Business Profile (lo aprobado) |
|---|---|---|
| Quién la usa | cualquiera, sobre cualquier sitio | el dueño o administrador de la ficha, o **quien este autorice por OAuth** |
| Reseñas | **5**, por relevancia | **todas**, páginas de hasta 50 |
| Media y total | `rating`, `userRatingCount` | `averageRating`, `totalReviewCount` |
| Guardar | ❌ *«You must not pre-fetch, cache, or store Places API content beyond the allowed exceptions»* (exentos `place_id` y coordenadas) | ✅ *«…for use outside of your Business Profile project except for limited amounts of Content»*: **hasta 30 días**, *«stored securely»*, *«cannot be manipulated or aggregated»* |
| Fotos de la reseña | no | ✅ `reviewMediaItems[]` (`thumbnailUrl`, `thumbnailLabel`, `videoUrl`) |
| Enlace por reseña / perfil del autor | sí | **no** (solo `metadata.mapsUri` de la ficha) |
| Coste | SKU Enterprise + Atmosphere | gratuita, 300 consultas/min por proyecto |
| Requisitos | una clave de API | acceso solicitado y aprobado + OAuth |

---

## 2. Objetivo

**Que la portada publique las reseñas reales del parque desde su ficha de Google, a todos los visitantes,
sin guardar más de lo que Google permite, sin que un fallo de Google o del cron las deje mal, y que el
horario de la web se pueda publicar en la ficha con un botón.**

Criterios de éxito, todos medibles:

1. **Con Google caído, la sección sigue con lo último sincronizado** mientras tenga menos de 29 días, y
   **ninguna reseña de más de 29 días se pinta aunque el cron esté muerto** (límite al leer).
2. **Las reseñas se ven sin aceptar cookies** y **ninguna petición del navegador sale a Google** (sonda que
   cuenta peticiones, incluido el caso «imagen ausente»).
3. **La portada no gana ninguna llamada HTTP** en su render (`PERF-02`).
4. **La media y el total son los de Google**, con «a fecha de …», jamás calculados.
5. **El texto se publica íntegro**; el recorte es solo visual.
6. **Una reseña borrada en Google desaparece** en la siguiente pasada coherente; **una pasada incoherente
   no borra nada**.
7. **Una reseña se puede ocultar desde el panel** y no vuelve con la resincronización.
8. **Ninguna escritura en Google sin un gesto del admin**, y ninguna fuera de producción.
9. **Un permiso caducado o una sincronización rota se DICEN**, en el panel y por correo, sin depender del
   cron para enterarse.
10. **White-label**: la ficha y la conexión por instalación; el proyecto de Google es de JumpWeb; ni un dato
    de PlayJump en el código.

### Fuera de alcance

- ⛔ **Preguntas y respuestas**: Google cerró esa API el **3 de noviembre de 2025**.
- ⛔ **Histórico de más de 30 días** de nada que venga de Google.
- ⛔ **Pedir permiso a los autores** (`[DECIDIDO owner]`, §8·R1).
- ⛔ **Leer la ficha pública sin API** (scraping): lo prohíben los términos de Google.
- ⏸️ **Aparcadas**: responder reseñas desde el panel y avisos de reseña nueva (T3), métricas de la ficha
  (T4), el correo de «déjanos tu reseña» (pendiente de consulta legal, `D9`), y **los vídeos de las reseñas**
  (pendiente del owner).

---

## 3. Opciones consideradas

| | Opción | Veredicto |
|---|---|---|
| A | Seguir con Places y alargar la caché | ❌ La política de Places no permite guardar reseñas; 5 elegidas por Google |
| B | **API de Business Profile con proyecto central** | ✅ **Elegida** — es lo que hacen los widgets que conectan tu cuenta (EmbedSocial: *«We are not using any scraping. Everything works on the official Google Business Profile API»*) |
| B′ | Business Profile con un proyecto por cliente | ❌ Prohibido: *«you cannot require them to apply for their own Business Profile project»* |
| C | Widget comercial con script de terceros | ❌ Petición del visitante a un tercero (`RGPD-05`), CSP (`SEC-01`), `PERF-02`, cuota mensual y los datos en casa de otro |
| D | Leer la ficha pública sin API (Elfsight lo describe como *«without using API»*; proveedores como Outscraper o Apify) | ❌ Contra los términos de Google y frágil |

---

## 4. Diseño aprobado

### 4.0 La forma y los módulos

    [admin del parque] ──OAuth──▶ proyecto CENTRAL de JumpWeb ──token de refresco──▶ cifrado, tabla propia
                                                                                         │ (Platform)
    [comando programado 1×/día · botón del panel que ENCOLA lo mismo] ◀──────────────────┘
          │  accounts.list · locations.list · reviews.list (paginado, en memoria)
          ▼
    solo las CANDIDATAS + el resumen, con `fetched_at`  ──filtro ≤29 días al leer · purga──▶ ∅   (Content)
          │
    BusinessProfileSocialProof (futuro) ─┐
    CmsSocialProof (existe)             ─┼─▶ FallingBackSocialProof (CAMBIA) ─▶ la portada (CAMBIA, §4.3·9)
                                         ┘
    [botón «Publicar horario en Google»] ─▶ lee Booking\Contracts\OperatingCalendar ─▶ locations.patch

| Pieza | Módulo | Por qué |
|---|---|---|
| Conexión: token, refresco, cliente HTTP, estados | **Platform** | sin dependencias; ni Content ni Identity |
| Ida y vuelta OAuth | `app/Http`, hermana de `GoogleAuthSession` (no compartida con el login) | el reto vive en la sesión del admin |
| Sincronización, tablas y fuente | **Content** | es prueba social |
| Publicar el horario | **Content**, leyendo `Booking\Contracts\OperatingCalendar` | Content no puede usar `OperatingSchedule` (`ModuleBoundariesTest`) |

**La portada NUNCA habla con Google** (`PERF-02`).

### 4.1 Tandas

| Tanda | Qué | Depende de |
|---|---|---|
| **T1** | **La conexión**: pantalla «Ficha de Google», OAuth, token cifrado, elegir y revalidar la ficha, estados, `business-profile:verify` | el proyecto central con acceso aprobado (§7·A) |
| **T2** | **Las reseñas**: sincronización, candidatas, fotos, «Ocultar», la fuente nueva, la sección, el texto de privacidad y **la retirada de Places** | T1 |
| **T6** | **El horario**: comparar, botón «Publicar en Google», comprobación diaria que avisa | T1 · el acceso de escritura verificado en producción |
| **T5** | **Entradas**: guía y enlace exacto para configurarlas a mano en la ficha (sin código de API) | — |

▶ Orden: **T1 → T2 → T6**. T5 es documentación y puede ir cuando el owner quiera. T6 va la última porque es
la única que ESCRIBE en la ficha que ven todos los clientes de Google.

✅ **T1·1 · EL CIMIENTO, EN EL ÁRBOL** (2026-09-20, `#720`): la tabla `google_business_connections` (fila
única por índice UNIQUE), `GoogleBusinessStatus` con los siete estados, el modelo con el token cifrado y
`GoogleBusinessCredentials` / `GoogleBusinessConnectionState`. 15 casos, arnés 12/12, Larastan 0.
⚠️ **Su migración está aplicada en la BD local y NO en ninguna otra**: empujada ≠ aplicada.

✅ **T1·2 · LA IDA Y LA VUELTA, EN EL ÁRBOL** (2026-09-20, `#721`): `GoogleBusinessOAuthSession` (reto de
un solo uso con **PKCE S256**, que no existía en el repo), `GoogleBusinessOAuth` (autorización y canje),
`GoogleBusinessConnector` (guardar bajo candado y revocar el anterior), el controlador de las dos
peticiones y la pantalla **«Ficha de Google»** en Ajustes → Web. 24 casos, arnés 15/15.
▶ ⚠️⚠️ **LA URI DE REDIRECCIÓN, para el §7·A·5**: `https://<host>/admin/ficha-google/callback`, **ruta
completa** y una por instalación. Es el dato que hay que dar de alta en el cliente OAuth central.
✅ La pantalla **la vio el owner el 2026-09-20** en su estado «sin configurar», que es el de hoy.

▶ Lo que sigue de la T1: elegir y revalidar la ficha (§4.2·4), el resto de la pantalla —quién conectó y
la última pasada (§4.2·1)—, desconectar (§4.2·8) y `business-profile:verify` (§4.2·10).

### 4.2 T1 · La conexión

1. **Pantalla «Ficha de Google»** en **Ajustes → Web** (`AdminSettingsHub::areas()`), con `canAccess()` sobre
   **`settings.manage`**. Pinta el estado de la conexión (§4.2·7) con lo que hay que hacer en cada uno, la
   ficha conectada, **qué usuario del panel la conectó**, la última pasada y su resultado.
2. **OAuth 2.0 de código de autorización** contra el cliente **central** de JumpWeb:
   - `access_type=offline` y `prompt=consent select_account` (sin ellos no llega el token de refresco);
   - **PKCE S256** con el `code_verifier` solo en el servidor (no existe aún en el repo);
   - **reto de un solo uso en el servidor**, con **clave de sesión propia**, que anota el `user_id` que lo pidió
     —el precedente es `GoogleAuthSession` (`startChallenge`/`consumeChallenge`)—; a la vuelta se exige **el
     mismo usuario** y se **re-comprueba `settings.manage`** (la ruta va con `web`, `auth`, `panel_role`, y
     `panel_role` deja pasar a `staff`);
   - se comprueba que el **`scope` concedido** incluye `business.manage` (el consentimiento de Google es
     granular); **sin token de refresco, error dicho y nada guardado**;
   - al reconectar se guarda el nuevo, **se revoca el anterior** y todo va bajo candado;
   - **limitadores** en la ida y la vuelta (`SEC-06`); **desconectar solo por POST**.
3. **Un solo ámbito**: `https://www.googleapis.com/auth/business.manage` (sensible; no hay uno de solo lectura).
4. **Elegir la ficha**: `accounts.list` → `locations.list` (`readMask` = `name,title,storefrontAddress,websiteUri,metadata`).
   Se guardan su nombre de recurso y de `metadata` el `placeId`, el `mapsUri` y el `newReviewUri`.
   **Se revalida en el servidor** que la ficha está en el `locations.list` de ESE token —al guardar y antes de
   cada escritura— y que **el host de su `websiteUri` es el del sitio**. Si cambia el `placeId` respecto a la
   conexión anterior: confirmación explícita, rastro y correo a los admins.
5. **Dónde viven las credenciales** (`SEC-11`):

   | Dato | Dónde |
   |---|---|
   | ID y secreto del **cliente central** | ajustes, **claves protegidas** de `SetSetting`, **solo por CLI con entrada oculta**; el panel no los edita ni los enseña |
   | **Token de refresco** | **tabla propia** de conexión (fila única, con versión) y cast **`encrypted`** —precedente `CustomerCard`—; **nunca** en `settings` (se lee entero en cada petición), en una propiedad de Livewire, en un export ni en una vista |
   | Token de acceso | solo en memoria del proceso; se refresca una vez por pasada con 5 min de margen y un único reintento ante un 401 |

   ⚠️ `DecryptException` → estado «caducado», sin registrar el cifrado. **Rotar la `APP_KEY` no obliga a
   reconectar** si se usa `APP_PREVIOUS_KEYS`. Runbook: si se filtra la `APP_KEY`, se revoca el permiso en Google.
6. **Tokens nunca en la URL ni en logs**: un único envoltorio HTTP que registra solo el estado y el código
   `error` de Google; `#[\SensitiveParameter]` en todo método que reciba un secreto; los jobs leen las
   credenciales en `handle()`, no las llevan dentro. Test con un **token-canario** que no puede aparecer en el
   log ni en `failed_jobs` tras forzar un timeout.
7. **Máquina de estados de la conexión**, con **aviso en la transición** a los administradores activos con
   correo y recordatorios escalonados:

   | Estado | Se entra por | La pasada |
   |---|---|---|
   | sin configurar | faltan las credenciales del cliente | no llama |
   | lista para conectar | credenciales, sin token | no llama |
   | **conectada** | vuelta OAuth válida | sincroniza |
   | **caducada** | `invalid_grant`, `invalid_client`, `DecryptException` | **no llama** (no se reintenta) |
   | **sin permiso** | 403 `PERMISSION_DENIED` (la cuenta perdió el rol) | no llama |
   | **ficha perdida** | 404 de la ficha | no llama |
   | **sin acceso a la API** | cuota 0 / API sin aprobar | no llama |

   Marcar «caducada» es **comparar-y-escribir sobre la huella del token usado**: un worker con el token viejo
   no pisa una reconexión recién hecha. Las credenciales se leen con consulta fresca, no con `Setting::value()`
   (memoriza la tabla por proceso). **La antigüedad de la última pasada completa se calcula AL LEER** y se
   enseña también en «Hoy», no solo en Ajustes.
8. **Desconectar**: revoca en Google (cuerpo POST, nunca `?token=`), borra el token, **borra lo sincronizado
   y sus ficheros**, deja rastro sin PII. Si `revoke` falla, se borra lo local igual y se dice cómo retirar el
   acceso desde la cuenta de Google. **Fuera de producción no llama a `revoke`.**
9. **Quién conectó**: se guarda el usuario del panel, y si pierde el rol se avisa a los admins. §7 recomienda
   una **cuenta de Google dedicada** del parque, administradora de la ficha.
   ⚠️ **FK del esquema, SIN relación de Eloquent** (`#720`): `ModuleBoundariesTest` dice `'Platform' => []` y
   **ni el kernel compartido lo exime** —`AuditLog` necesita su entrada explícita en `SEAM` para mirar a
   `User`—. Se descartó imitarlo: el §4.0 puso la conexión en Platform **porque** no depende de nadie, y una
   excepción más habría borrado ese motivo. Quién conectó lo resuelve la capa de ENTREGA.
   ⚠️⚠️ **El token se descifra A MANO, no por la propiedad** (`#720`): por la propiedad el fallo es invisible
   —Larastan llegó a declarar muerto el `catch (DecryptException)`, y medirlo demostró que no lo está—. Y se
   lee de `getAttributes()`, **nunca de `getRawOriginal()`**, que devuelve lo leído de la base y entregaría el
   token ANTERIOR entre poner uno nuevo y guardarlo.
10. **`business-profile:verify`** (futuro): cuenta, ficha, número de reseñas y estado de cada API, **sin
    imprimir tokens, correos ni cuerpos** en ningún nivel de detalle (test con canario).

### 4.3 T2 · Las reseñas

1. **Sincronización** (`business-profile:sync` (futuro)), **1× al día** a una hora propia, en segundo plano,
   con **presupuesto de tiempo** (p. ej. 120 s) y reintentos que respetan `Retry-After`. El botón del panel
   **encola el mismo trabajo** (job con un solo intento y `timeout` coherente) y, si hay una pasada en curso,
   lo dice. **Un solo candado dentro del servicio**, en un almacén que no se desaloja (`cache_locks` de la base),
   con caducidad igual al presupuesto.
2. **La pasada recorre TODAS las páginas en memoria** (`pageSize=50`) y **persiste solo lo que se puede
   publicar** —*«limited amounts of Content»*—:
   - **las candidatas**: con texto, con al menos el mínimo de estrellas, no ocultas, hasta **6 + un margen**
     (p. ej. 12);
   - **el resumen**: `averageRating`, `totalReviewCount`, `mapsUri`, `newReviewUri`, `fetched_at`.
   Del resto no se guarda nada. **Si el mínimo de estrellas cambia en el panel, se fuerza una pasada.**
3. **Solo se borra con una pasada COHERENTE**: lo recogido cuadra con el `totalReviewCount` de la primera y de
   la última página y la media no cambió entre ellas. **Una lista vacía con total mayor que cero no borra**;
   una caída brusca, tampoco (se avisa). Se deduplica por el `name` de la reseña. El reemplazo va **por
   diferencias en una transacción**. `fetched_at` se renueva en **toda** fila devuelta, cambie o no.
4. **El límite de 30 días se aplica AL LEER**: la fuente solo devuelve filas con `fetched_at` de los últimos
   29 días. La purga (`Prunable`, **no** `MassPrunable`, en la entrada de `model:prune` de `routes/console.php`)
   es limpieza, no la garantía. ▶ Y **si pasan 3 días sin una pasada completa**, nombre y foto dejan de
   pintarse: una reseña borrada en Google no puede seguir en la web porque la sincronización esté rota.
5. **Anónimas** (`[DECIDIDO owner]` se publican): nombre y foto a `null` **antes de guardar**; se pintan
   como «Usuario de Google» y sin foto. ⚠️ **Medir primero** si Google manda un rótulo propio en lugar del vacío.
6. **Imágenes: la foto del autor y las fotos de la reseña** (`reviewMediaItems`, `thumbnailUrl`) se descargan
   **solo de las candidatas** y se sirven desde nuestro servidor. **Los vídeos no** (pendiente del owner).
   Descargador endurecido:
   - lista blanca de host exacta (`lh[3-6].googleusercontent.com`, solo https), **sin redirecciones**, timeouts
     cortos, **tope de bytes** leyendo en streaming;
   - tipo por **bytes mágicos**: jpeg, png, webp o gif; **SVG nunca**; la extensión sale de los bytes;
   - nombre por hash, **nunca** derivado del autor; escritura atómica; ninguna librería de imagen las procesa
     (no se redimensionan: *«cannot be manipulated»*);
   - **disco privado servido por una ruta de Laravel** (recibe `SecurityHeaders`), con
     `Content-Security-Policy: default-src 'none'; sandbox`, `nosniff` y `Cache-Control` corto;
   - el fichero se borra **en la misma operación que su fila** (purga, reemplazo, «Ocultar», desconectar) y un
     **barrido de huérfanos** programado limpia lo que quede;
   - si la descarga falla: **la inicial, jamás la URL de Google**.
7. **Ocultar desde el panel** (lo exigió la revisión de privacidad): por reseña, con **motivo tasado**
   —petición del autor · menores · salud o terceros · otros—. Lista de supresión por **hash del identificador**
   que sobrevive a la resincronización y a la purga; borra en el acto nombre, foto, fotos y texto (también la
   respuesta del parque); rastro en `audit_logs` con solo el hash y el motivo. **No toca la media ni el total.**
8. **El texto de la reseña** puede traer la traducción de Google mezclada (`(Translated by Google) …
   (Original) …`, sin documentar, en orden variable): **se mide con la ficha real antes** de escribir el
   analizador; se publica **el original**; el analizador **falla cerrado** (ante marcas ambiguas guarda el texto
   crudo, lo marca y lo cuenta en la verificación). Al pintar: `{{ }}`, `white-space: pre-line`, `dir="auto"` con
   aislamiento bidi; **prohibido `nl2br` sin escapar**.
9. **El contrato y la vista CAMBIAN**:
   - `Testimonial` gana la **respuesta del parque**, las **fotos** y la marca de **anónimo**; hace falta dónde
     llevar el **enlace a la ficha**, **«Escribir una reseña»** y **la línea del filtro** (un objeto de selección
     en el contrato); el doble anónimo de `GoogleAttributionTest` se actualiza;
   - `FallingBackSocialProof` cambia: recibe hoy el tipo concreto de Google y aplica el consentimiento de
     `maps`; **la fuente declara si sus opiniones necesitan consentimiento**, y ésta no lo necesita;
   - **invariante con guarda: la URL de una imagen es una ruta propia o `null`, nunca un host de terceros**;
   - binding **`scoped`** con memo de instancia (el controlador pide el contrato dos veces);
   - la fecha relativa se deriva de la fecha, como `CmsSocialProof`; `starRating` llega como texto (`FIVE`…).
10. **Qué enseña la portada** (`[DECIDIDO owner]`):
    - las **6** más recientes que pasan el filtro, **con la respuesta del parque debajo** y sus fotos;
    - el **mínimo de estrellas es un ajuste del panel** (por defecto **4**);
    - **la media y el total nunca se filtran**, con **«a fecha de …»**;
    - **la línea del filtro, siempre visible**: *«Reseñas de 4 estrellas o más · ni el parque ni Google
      verifican que los autores sean clientes · ver todas en Google»* — la ley europea de consumo (Ómnibus
      2019/2161) considera engañoso enseñar solo las positivas sin decirlo [consulta legal de la redacción];
    - la entradilla de hoy (*«No las elegimos nosotros»*) **se reescribe**: deja de ser cierta;
    - dos enlaces: **«Ver todas en Google»** (`mapsUri`) y **«Escribir una reseña»** (`newReviewUri`);
    - **atribución de marca con esta fuente**: la «G» o la palabra Google (no el logotipo de Google Maps de
      `#494`), **sin estrellas pegadas al logotipo**, y nada de «Google rating»;
    - **el umbral `MIN_REVIEWS = 1`** (`#494`, definitivo) se conserva para la cifra y se muda con su guarda;
    - si el filtro deja menos de 6, las que haya; **si deja 0**, las opiniones propias, y la media se sigue enseñando;
    - **JSON-LD: nunca `aggregateRating` ni `Review` con datos de Google** (guarda);
    - `data-nosnippet` en el bloque de reseñas.
11. **Privacidad** (artículos 6.1.f, 14, 21 del RGPD):
    - **texto nuevo en la política de privacidad** (borrador en §10) y **paso de despliegue manual por
      instalación**: la migración no toca una política ya editada [validación de la asesoría del cliente];
    - **ponderación del interés legítimo** y fila en el registro de actividades (plantilla para el cliente);
    - **prohibido cruzar** la tabla de reseñas con clientes o pedidos (guarda de arquitectura);
    - las copias de seguridad declaran su retención y excluyen esta tabla y sus ficheros.
12. **CSP**: `lh3.googleusercontent.com` sale de `img-src` **en el MISMO despliegue** que el cambio de binding,
    con guarda nueva (*«`img-src` no nombra a Google»*). Las entradas de caché de Places (`social-proof.google.*`)
    se borran.
13. **Se retira Places** tras verificar la T2 con la ficha real: `GoogleSocialProof`, `SocialProofRefresh`,
    `RefreshSocialProof` y su línea del programador, `config/services.php` (`google_places`), la fila
    `social.google_place_id`, el comentario de CSP de `SecurityHeaders`, y los casos que pierden su sujeto
    (`GoogleAttributionTest`, `ReviewsSectionTest`, `SocialProofNeverHitsTheRenderPathTest` —re-apuntado a la
    fuente nueva—), clasificados por sujeto (`CONVENCIONES §3.quater`). `deploy.sh` pasa a comprobar las tareas
    programadas **por NOMBRE**. En la consola de Google, la clave de Places se retira.

### 4.4 T6 · El horario, publicado con un botón

1. **La fuente es el panel**, leído por la cara pública `Booking\Contracts\OperatingCalendar::windowFor()`
   (la de `HeroStatus`), **nunca** por la variante por zona.
2. **Qué se publica**: `regularHours` = la semana efectiva vigente; `specialHours` = fechas especiales, cierres
   y **los días de las temporadas** dentro de un horizonte (una temporada del panel no es un rango en Google).
   El tope de periodos y la ventana se **miden con `validateOnly`** antes de fijarlos.
3. **Lo que Google no puede expresar no se publica y se dice**: un día **sin configurar** (el producto lo
   trata como «abierto sin ventana»), una fecha especial abierta sin horas que herede un semanal nulo. **Las
   zonas nunca van a Google.** El cierre a medianoche y la zona horaria del parque (`DisplayTime`; la app
   está en UTC) tienen caso propio.
4. **Cada publicación sale de un gesto del admin** (`[DECIDIDO owner]`, y lo exige la política: *«must not
   automate or trigger … listing edits … without the user's prior specific and express consent»*): el panel
   enseña **qué cambia** (la ficha dice X · la web dice Y) y el admin pulsa **«Publicar en Google»**.
   - **Permiso propio** (`google_business.write` (futuro)), no asignable con los de gestión: hoy editan el
     horario `slots.manage` y `prices.manage`;
   - **solo en producción**, con un **interruptor** explícito apagado por defecto y excluido de toda copia de
     configuración; fuera de producción la clase que escribe se enlaza a una que solo hace `validateOnly`;
   - **`updateMask` fija en el código**: `regularHours,specialHours`, nada más;
   - por **cola**, con **`validateOnly` antes**, rastro con actor y cambios, y **correo a los admins** en cada
     publicación; un interruptor de parada inmediata.
5. **Antes de publicar se leen `hasPendingEdits` y `getGoogleUpdated`**: no se reenvía lo pendiente, y lo que
   Google haya actualizado se enseña para que el admin decida. Una edición puede tardar **hasta 30 días** en
   moderarse o no aprobarse: el panel distingue **pendiente · aplicada · no aprobada · divergente**.
6. **La comprobación diaria solo AVISA** si la ficha y la web no coinciden. Compara la forma normalizada, solo
   fechas futuras, contra la última carga publicada. **Nunca corrige sola.**

### 4.5 T5 · Entradas en la ficha, a mano

`[DECIDIDO owner]`: las atracciones gestionan sus **Entradas** en la propia ficha (*«All attraction businesses
and tour operators»*; *«Ticket or Activity name and Booking URL are required fields»*), sin API. El producto da
el **enlace exacto a la página de reserva** de la instalación y la guía (§7·B·3). **Sin UTM**: el producto no
tiene analítica ni guarda UTM, así que no se promete medir.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **PERF-02** | La portada lee de nuestra base (1–2 consultas indexadas, binding `scoped`). Presupuesto de `HomePageTest` medido antes y después |
| **SEC-01** | `img-src` deja de nombrar a Google; las imágenes propias van con su propia CSP |
| **SEC-04** | La vuelta OAuth re-comprueba el permiso en el servidor |
| **SEC-06** | Limitadores en la ida, la vuelta, el botón de sincronizar y el de publicar |
| **SEC-07** | `mapsUri` y `newReviewUri` solo https y con lista blanca de hosts de Google, saneados donde nace el dato |
| **SEC-11** | Credenciales solo por CLI; el token, cifrado en tabla propia. ▶ Se propone ampliarla: *«una credencial de un tercero con permiso de escritura se guarda cifrada y fuera de `settings`»* (del owner, `CONVENCIONES §9.1`) |
| **RGPD-01** | Una petición de supresión de un cliente se comprueba también contra las reseñas (procedimiento de «Ocultar») |
| **RGPD-02** | Ni textos, ni nombres, ni tokens en logs, `audit_logs` ni `failed_jobs` |
| **RGPD-05** | Las reseñas **dejan de depender del consentimiento**: ninguna petición del navegador a Google |
| **RGPD** (tratamiento nuevo) | Nombre, foto y fotos de autores terceros hasta 30 días: interés legítimo, política de privacidad, «Ocultar», plazo al leer |
| **SUITE** | `Http::preventStrayRequests()`; todo con `Http::fake`; ningún caso habla con Google |

---

## 6. Plan de verificación empírica

**T1** — `state` de un solo uso (reutilizado, de otra sesión, de otro usuario → rechazado) · permiso
re-comprobado a la vuelta (un `staff` no conecta) · `scope` sin `business.manage` → error dicho · sin token de
refresco → nada guardado · cada estado de §4.2·7 con su transición y su aviso · comparar-y-escribir frente a
un worker con el token viejo · token cifrado en la base e ilegible en el panel, el HTML, el snapshot de
Livewire y `audit_logs` · canario en log y `failed_jobs` · ficha revalidada antes de escribir · desconectar
revoca (solo en producción) y borra ficheros · **`business-profile:verify` contra la ficha real**, con su
salida pegada en la decisión.

**T2** — ① **medición previa con la ficha real**: formato del comentario (traducción), anónimos, fotos,
respuestas; ② paginación en memoria · solo candidatas persistidas · pasada incoherente o vacía no borra ·
reemplazo por diferencias · filtro de 29 días al leer **sin ejecutar la purga** (`travel()`) · 3 días sin pasada
→ sin nombre ni foto · el fichero desaparece con su fila · barrido de huérfanos · descargador (redirección,
tamaño, SVG, host) · «Ocultar» sobrevive a la resincronización · anónimo sin nombre ni foto · URL de imagen
nunca de terceros · la media es la de Google · la línea del filtro presente · sin `aggregateRating` ·
`img-src` sin Google · presupuesto de la portada · **sonda de navegador sin consentimiento que cuenta
peticiones a Google (cero), también con una imagen ausente**; ③ arnés de mutación con control y por código de
salida.

**T6** — la carga se construye desde `OperatingCalendar` · día sin configurar → no se publica y se avisa · zonas
fuera · temporada → días en `specialHours` · medianoche y zona horaria · `updateMask` fija · sin permiso propio
no se publica · fuera de producción solo `validateOnly` · `hasPendingEdits` bloquea el reenvío · la comprobación
diaria no escribe · **`validateOnly` contra la ficha real antes de la primera publicación**.

---

## 7. Guía de configuración

### A · Lo que hace JumpSystem, UNA vez (el owner) `[DECIDIDO owner, 2026-09-20]` (`#719`)

> ⚠️ **La identidad ante Google es JumpSystem**, la casa que agrupa JumpWeb, JumpApp y lo demás — **no
> JumpWeb ni la marca de un cliente** (`#719`): lo que ve el dueño de un parque al autorizar es el nombre
> de la app y el dominio. Cuenta de Google propia, y **la misma** para el dominio, Search Console y los dos
> proyectos: la aprobación de la API queda atada a la CUENTA, y migrarla obliga a re-solicitar y esperar.

0. **La web de JumpSystem**, en su dominio y **pública**: `/` (qué es y **qué hace la app** — sin eso hay
   rechazo), `/privacidad` y `/aviso-legal`. ⚠️ Esa privacidad **no es la del §10**: aquélla es la del parque y
   habla de los autores de las reseñas; ésta habla de **los datos de Google del admin que conecta** (token,
   ficha, reseñas 29 días, sin cesión). *«The privacy policy must be visible to users, hosted **within the same
   domain** as your application's home page, and linked to on the OAuth consent screen»*.
1. **Dos proyectos de Google Cloud**: el **central** («JumpSystem Business Profile») y **otro de desarrollo**.
   Separados porque revocar un permiso retira la autorización de TODO el proyecto: con uno solo, desconectar
   en local mataría la conexión de producción.
2. **Pedir el acceso a la API para el proyecto central**: formulario de Google Business Profile → *Application
   for Basic API Access*, con el **número de proyecto** y un correo **propietario o administrador de una ficha
   verificada y activa 60+ días, con web**.
   ⚠️⚠️ **JumpSystem no tiene ficha, y no la necesita** (medido el 2026-09-20; la versión anterior daba por
   hecho que el owner gestionaba la del parque, y **no la gestiona**): *«This GBP can be the applicant's own
   office or headquarters or **it could belong to one of the clients they manage**»*. ▶ El paso real es que
   **el parque añada la cuenta de JumpSystem como ADMINISTRADOR de su ficha** (su perfil → Usuarios → Añadir →
   rol *Administrador*, no *Propietario*). Hace falta igualmente para la T1: la conexión OAuth la autoriza una
   cuenta que administre la ficha. ⚠️ **Comprobar que esa ficha lleve 60+ días verificada**, no 60 días abierta.
   Aprobado = la cuota pasa de **0 a 300** consultas por minuto. Google no da plazo.
3. **Habilitar las APIs** en los dos proyectos: My Business Account Management · My Business Business
   Information · Google My Business (aparece tras la aprobación). No hacen falta Performance, Place Actions,
   Notifications, Lodging, Verifications ni Business Calls (T3, T4 y T5 por API quedan fuera).
4. **Pantalla de consentimiento** del proyecto central: tipo **Externo**, marca **JumpSystem**, dominio y
   política de privacidad **de JumpSystem** (§7·A·0), ámbito `business.manage`, **estado «En producción»** (en
   «En prueba» el permiso caduca a los 7 días).
   ⚠️ **Verificación del ámbito sensible**: la autorizarán varios dueños de parques, así que la excepción de uso
   personal no vale. Hasta verificarla (3–5 días hábiles: dominio de JumpSystem verificado en Search Console
   **con una cuenta que sea Owner o Editor del proyecto**, política de privacidad en ese dominio, vídeo de
   demostración, justificación del ámbito), la app enseña el aviso de «no verificada» y tiene un **tope de 100
   usuarios**: suficiente para los primeros parques, no para crecer. Sin verificar la marca, la pantalla enseña
   **el dominio**, no el nombre.
   ⚠️⚠️ **El vídeo va DESPUÉS de la T1, y eso ordena el plan** (medido el 2026-09-20): Google exige que enseñe
   *«the OAuth consent flow … correct app name display on consent screen … browser address bar showing your
   OAuth client ID … detailed functionality demonstrating each sensitive scope's usage»*, o sea **el flujo real
   funcionando y desplegado**. ▶ La **solicitud** del §7·A·2 no lo necesita y es lo que más tarda: va primero.
5. **Cliente OAuth web** en el proyecto central, con una **URI de redirección por instalación** (la de cada
   parque, que se fija en la T1); el de **desarrollo**, en el otro proyecto, con `localhost`. **Nunca `localhost`
   en el central.**
6. **Entregar las credenciales a cada instalación** por CLI con entrada oculta (la orden exacta llega con la
   T1). **Nunca por chat.**
7. **Política de terceros de Google**: JumpWeb gestiona fichas en nombre de sus clientes, así que avisa al
   cliente de cambios en su cuenta en 48 h y le deja desvincularse en 7 días hábiles (lo cubre «Desconectar»).

### B · Lo que hace cada parque

1. **Comprobar su ficha**: verificada, con la web del parque puesta. Recomendado: una **cuenta de Google
   dedicada** del parque, administradora de la ficha.
2. **Conectar** (tras la T1): *Ajustes → Web → Ficha de Google → Conectar con Google* → cuenta → aceptar →
   elegir la ficha. El panel dice qué ha traído.
3. **Entradas** (T5): en la ficha de Google, apartado **Entradas**, con el enlace a la página de reserva que da
   el panel.
4. **Política de privacidad**: añadir el texto de §10 (validado por su asesoría) antes de activar las reseñas.
5. **Horario** (tras la T6): comparar y pulsar «Publicar en Google» cuando cambie.

---

## 8. Decisiones

| | Decisión | Estado |
|---|---|---|
| **R1** | **Se publican TODAS las reseñas sin pedir permiso a cada autor**, como el sector | ✅ `[DECIDIDO owner, 2026-09-11]` — ⚠️ **riesgo aceptado**: la guía de marca de Google dice *«You must get consent from the reviewer if you want to use customer reviews of your business for your own marketing purposes, such as on your website»*. Mitigación: «Ocultar» a petición, la línea del filtro, y un interruptor para apagar la sección si Google lo pide |
| **R2** | Proyecto **central de JumpWeb** | ✅ `[DECIDIDO owner]` (sustituye a D1) |
| **R3** | Horario **publicado con un botón**; la comprobación diaria solo avisa | ✅ `[DECIDIDO owner]` (sustituye a D8) |
| **R4** | **Entradas a mano** en la ficha; sin T5 por API | ✅ `[DECIDIDO owner]` |
| **D2** | Imágenes servidas desde nuestro servidor | ✅ `[DECIDIDO owner]` |
| **D3** | **6** en la portada | ✅ `[DECIDIDO owner]` |
| **D4** | **Filtradas por estrellas**, con mínimo configurable y la línea que lo dice | ✅ `[DECIDIDO owner]` |
| **D5** | Las **anónimas se publican** | ✅ `[DECIDIDO owner]` |
| **R5** | **Fotos de las reseñas: sí**. Vídeos: **pendiente** | 🟦 vídeos pendientes del owner |
| **D9** | El correo de «déjanos tu reseña» | ⏸️ aparcado; consulta legal (LSSI) |
| — | Redacción de la política de privacidad, de la línea del filtro y la ponderación del interés legítimo | ⚖️ asesoría del cliente |

---

## 9. Riesgos y trampas conocidas

- **R1 aceptado**: publicar sin permiso del autor contra la letra de la guía de marca de Google.
- **La app «En prueba»** → permiso de 7 días → sincronización muerta cada semana.
- **El mismo proyecto en desarrollo y producción** → desconectar en local mata producción.
- **El cron muerto** → la portada no sirve nada de más de 29 días (límite al leer), y se avisa.
- **Una pasada incoherente** borraría reseñas vivas → solo borra la coherente.
- **La traducción mezclada en el texto**: sin documentar; se mide antes.
- **No hay entorno de pruebas** en esta API: `validateOnly` antes de toda escritura.
- **La moderación de Google** puede tardar hasta 30 días o no aprobar un horario.
- **`Setting::value()` memoriza la tabla por proceso** → la conexión va en tabla propia con lectura fresca.
- **`withoutOverlapping()` no protege el botón** → candado dentro del servicio, en la base.
- **Un `MassPrunable` no dispara eventos** → los ficheros sobrevivirían: `Prunable` con `pruning()`.
- **El despliegue borra lo que no está protegido bajo `public/`** (`rsync --delete`) → imágenes en disco privado.
- **Cuota**: 300 consultas/min por proyecto (compartida entre parques) y **10 ediciones/min por ficha**.

---

## 10. Registro de la revisión adversarial (2026-09-11)

Cinco lentes en paralelo, en solo lectura y con evidencia: **cumplimiento con Google · seguridad · RGPD ·
robustez y operación · encaje con el repo**. **Los tres bloqueantes y el hallazgo de las fotos los verificó
además el agente principal en la fuente, con cita literal.** Lo que cambió respecto a la versión revisada:

| Hallazgo | Fuente | Resultado |
|---|---|---|
| Publicar reseñas en la web exige el consentimiento del autor | [guía de reseñas de Google](https://partnermarketinghub.withgoogle.com/brands/google/use-cases/customer-reviews/) + *«comply with Google's brand permissions guidelines»* | R1: riesgo aceptado por el owner |
| Un proyecto por cliente, prohibido | [políticas de la API](https://developers.google.com/my-business/content/policies) | R2: proyecto central |
| Editar la ficha exige consentimiento previo y específico | ídem | R3: botón |
| **Las reseñas SÍ traen fotos** (la versión revisada decía que no) | [recurso `Review`](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews) | fotos sí; vídeos pendientes |
| No hay tipo de acción «entradas»; las atracciones las gestionan en la ficha | [`PlaceActionType`](https://developers.google.com/my-business/reference/placeactions/rest/v1/locations.placeActionLinks) · [ayuda de entradas](https://support.google.com/business/answer/12944910) | R4: a mano |
| «Limited amounts of Content» | políticas de la API | solo candidatas |
| Revocar retira la autorización combinada del proyecto | documentación de OAuth de Google | proyecto de desarrollo aparte |
| La vista, el contrato y el decorador SÍ cambian (la versión revisada decía que no) | `FallingBackSocialProof.php`, `Testimonial`, `home.blade.php` | §4.3·9 |
| Staging y local escribirían en la ficha real | `ENTORNOS.md`, `#453` | solo producción + interruptor |
| El límite de 30 días dependía del cron | `routes/console.php`, `ENTORNOS.md` | límite al leer |
| Una pasada «completa» puede borrar reseñas vivas | semántica de `orderBy=updateTime desc` | solo pasadas coherentes |
| Sin mecanismo para retirar una reseña concreta | arts. 17, 21 y 9 RGPD | «Ocultar» |
| La política de privacidad no menciona reseñas y la migración no la actualiza | `LegalContent`, migración de páginas legales | texto + paso manual |
| Credenciales en el panel chocan con `SEC-11` | `Settings.php`, `INVARIANTES` | solo CLI |
| El horario del producto no cabe tal cual en el modelo de Google | `OperatingSchedule`, `horario-por-zona.md` | §4.4·2–3 |
| Atribución, «a fecha de» y JSON-LD | guía de reseñas · [datos estructurados](https://developers.google.com/search/docs/appearance/structured-data/review-snippet) | §4.3·10 |
| Filtrar sin decirlo, engañoso para la ley de consumo | Directiva 2005/29 modificada por la Ómnibus 2019/2161 (fuentes secundarias) | la línea del filtro [consulta legal] |

**Borrador del texto de privacidad** (ES; EN y FR a traducir; validar con la asesoría del cliente):

> **Reseñas de Google en nuestra web.** Mostramos en la página de inicio reseñas que los usuarios han publicado
> sobre nosotros en nuestra ficha de Google, obtenidas de Google mediante la herramienta que ofrece al titular
> de la ficha. Tratamos el nombre público y la foto de perfil con los que el autor publicó, el texto, la
> valoración, la fecha, las fotos que adjuntó y, en su caso, nuestra respuesta; las reseñas anónimas se
> muestran sin nombre ni foto. Finalidad: mostrar a los visitantes opiniones reales sobre el parque. Base:
> nuestro interés legítimo (art. 6.1.f RGPD). Conservamos estos datos como máximo 30 días desde la última vez
> que los obtenemos de Google; si el autor borra o cambia su reseña en Google, desaparece o se actualiza aquí.
> Las imágenes se sirven desde nuestro servidor: tu navegador no se conecta a Google para verlas. No usamos
> estos datos para ningún otro fin ni los cruzamos con nuestros clientes. Si eres el autor y quieres que tu
> reseña deje de mostrarse en esta web, escríbenos a :legal_email y la retiraremos; también puedes oponerte,
> pedir acceso o reclamar ante la AEPD.

**Fuentes**: [políticas de la API](https://developers.google.com/my-business/content/policies) ·
[guía de reseñas para negocios](https://partnermarketinghub.withgoogle.com/brands/google/use-cases/customer-reviews/) ·
[políticas de Places](https://developers.google.com/maps/documentation/places/web-service/policies) ·
[recurso `Review`](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews) ·
[`reviews.list`](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews/list) ·
[requisitos de acceso](https://developers.google.com/my-business/content/prereqs) ·
[configuración básica](https://developers.google.com/my-business/content/basic-setup) ·
[OAuth](https://developers.google.com/my-business/content/implement-oauth) ·
[fichas](https://developers.google.com/my-business/reference/businessinformation/rest/v1/locations) ·
[límites](https://developers.google.com/my-business/content/limits) ·
[entradas y actividades](https://support.google.com/business/answer/12944910) ·
[ediciones de la ficha](https://support.google.com/business/answer/3038311) ·
[verificación de ámbitos sensibles](https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification) ·
[caducidad de tokens](https://developers.google.com/identity/protocols/oauth2) ·
[apps no verificadas](https://support.google.com/cloud/answer/7454865) ·
[política de contenido de Maps](https://support.google.com/contributionpolicy/answer/7400114) ·
[datos estructurados de reseñas](https://developers.google.com/search/docs/appearance/structured-data/review-snippet) ·
[EmbedSocial](https://embedsocial.com/gbp/api/) · [Elfsight](https://elfsight.com/blog/how-to-work-with-gmb-reviews-api/) ·
[medida de la comunidad sobre traducción](https://ambience.sk/google-business-profiles-api-reviews-without-translation/).

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«Reseñas / valoración de Google · prueba social en la landing · la API de Google Business Profile (conexión OAuth, reseñas con fotos, «Escribir una reseña», horario publicado en la ficha, entradas)»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/specs/google-business-profile.md` · `docs/specs/google-reviews.md`.

- ❗❗❗ **EMPIEZA POR `docs/specs/google-business-profile.md`**
- ✅ **APROBADA tras revisión adversarial de cinco lentes, código NO empezado** (`#524`, `[DECIDIDO owner]`): la fuente pasa de Places a la **API del DUEÑO de la ficha** — todas las reseñas, **guardables 30 días** (Places no deja guardar nada salvo el `place_id`), gratis.
- ⚠️⚠️ **Proyecto de Google CENTRAL de JumpWeb** (la política prohíbe pedirle a cada cliente el suyo) · **se publican TODAS sin pedir permiso al autor** (riesgo ACEPTADO por el owner frente a la guía de marca de Google; mitigado con «Ocultar») · **el horario se publica con un botón** (Google exige consentimiento específico por edición) · **toda escritura solo en producción** y desarrollo en OTRO proyecto (revocar retira la autorización de todo el proyecto) · **el límite de 30 días se aplica al LEER**.
- ⚠️⚠️ **La app OAuth va «En producción»**: en prueba el permiso caduca a los **7 días** y la sincronización muere en silencio.
- ⚠️ **El límite de 30 días rige TODO lo que da la API.**
- ⚠️ El texto de una reseña puede traer la **traducción de Google mezclada** (sin documentar): se mide antes de analizarlo.
- ▶ Tandas **T1 → T2 → T6**; §7 es la guía (A: JumpWeb una vez · B: cada parque), §8 las decisiones y §10 el registro de la revisión. — Lo construido con Places: `docs/specs/google-reviews.md`
- ✅ (ejecutada; §3.2 corregida) —
- ⚠️⚠️ **§1.3: CINCO restricciones DURAS** verificadas contra la doc oficial, y tres chocan con invariantes ya endurecidos (`PERF-02`, `SEC-07`, `RGPD-05`). **§1.4: con Google como única fuente el parque NO elige qué sale en su portada.**
- ✅ **§3 YA ESTÁ RESUELTA** (owner, 2026-08-25): Google es la fuente de verdad y el CMS el respaldo —
- ⚠️ **y una de las dos opciones del owner NO se puede hacer**: el snapshot persistente de reseñas de Google está PROHIBIDO, la caché corta sí.
- ❗❗ **§3.3 reencuadra el trabajo: el respaldo NO es para cuando Google falle — es lo que ve TODO visitante que no acepta cookies de terceros, cada día.**
- ❗ Sigue **pendiente del owner** el
- ✅ a la spec y **tres datos** (`place_id`, clave de API, techo de gasto)
