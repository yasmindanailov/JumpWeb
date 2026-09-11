# [SPEC] Google Business Profile — las reseñas de la ficha del parque, siempre a la vista (y lo demás que da la API)

> Estado: 🟦 **EN REVISIÓN — spec escrita, código NO empezado** · Última actualización: 2026-09-11 ·
> Decisión asociada: `DECISIONES #524` (`[DECIDIDO owner, 2026-09-11]`: *«vamos a hacerlo así, de manera
> profesional»*).
>
> ▶ **Sustituye como FUENTE a `google-reviews.md`** (Places API) en cuanto la T2 esté verificada. Aquella
> spec queda como registro de lo construido en `#490`/`#491`/`#494`, y **su contrato se conserva**: la
> portada lee `Content\Contracts\SocialProof` y no sabe de dónde vienen los datos, así que el cambio de
> fuente **no toca la vista**.
>
> ❗❗❗ **Lo que hay que saber si solo se leen cinco líneas:**
> 1. **Places no permite guardar reseñas** (solo el `place_id` y las coordenadas), da **5 como máximo** y
>    las elige Google. **Business Profile** es la API del DUEÑO de la ficha: da **todas**, con la media y
>    el total reales, y permite **guardarlas hasta 30 días**. Es lo que hacen los widgets serios (§1.3).
> 2. **Necesita que el owner haga cuatro cosas en Google** que ningún agente puede hacer por él: pedir el
>    acceso a la API, crear el proyecto, la pantalla de consentimiento y el cliente OAuth (§7).
> 3. **La app OAuth tiene que quedar «En producción», nunca «En prueba»**: en prueba Google caduca el
>    permiso a los **7 días** y la sincronización se para en silencio (§4.2·6).
> 4. **«Siempre a la vista» tiene DOS mitades**: guardar las reseñas (lo resuelve esta API) y que el
>    visitante que no acepta cookies las vea (lo resuelve servir los avatares desde nuestro servidor —
>    decisión `D2`, §8—).
> 5. **El límite de 30 días lo rige TODO lo que da la API**, métricas incluidas: no se puede construir un
>    histórico largo con datos de Google (§4.5).

---

## 1. Contexto y problema — MEDIDO, no supuesto

### 1.1 Lo que hay hoy

    fuente          Places API (New) · Place Details con `reviews`        `GoogleSocialProof`
    caché           30 min (`CACHE_TTL_SECONDS`)                         en Redis, `allkeys-lru`
    refresco        cada 3 h (`social-proof:refresh`)                    una llamada por idioma
    umbral          1 reseña (`MIN_REVIEWS`, `[DECIDIDO owner]` #494)
    consentimiento  las reseñas solo con cookies de terceros aceptadas   por el avatar (`RGPD-05`)
    respaldo        opiniones propias (`testimonials`, `CmsSocialProof`)

### 1.2 Por qué no salen siempre — cuatro causas, todas medidas

| # | Causa | Medido |
|---|---|---|
| C1 | La caché dura 30 min y el refresco va cada 3 h | vacía **150 de cada 180 min** (83 %), `#499` |
| C2 | Places **no permite** guardarlas: alargar la caché no es una salida limpia | política leída el 2026-09-11 (§1.3) |
| C3 | Sin cookies de terceros aceptadas no se pintan (el avatar es una petición del visitante a Google) | `#491`, `RGPD-05` |
| C4 | En desarrollo, la clave de Places rechaza la IP (403 `API_KEY_IP_ADDRESS_BLOCKED`) | `#523`; `[owner]`: en local no hace falta |

⚠️ **Y una causa de fondo que ninguna API arregla**: el parque tiene **2 reseñas** en Google. La palanca
más grande para que la sección luzca es que haya más, y eso tiene su tanda (T7, §4.8).

### 1.3 Lo que dice la documentación oficial (leída el 2026-09-11)

| | Places API (lo de hoy) | Business Profile API (lo propuesto) |
|---|---|---|
| Quién puede usarla | cualquiera, sobre cualquier sitio | **solo el dueño o administrador** de la ficha (*«business listings that you either own or are authorized to manage»*) |
| Cuántas reseñas | **5**, por relevancia, sin paginar | **todas**, páginas de hasta 50 (`reviews.list`, `pageSize` máx. 50) |
| Media y total | `rating`, `userRatingCount` | `averageRating`, `totalReviewCount` |
| Guardarlas | ❌ *«You must not pre-fetch, cache, or store Places API content beyond the allowed exceptions»*; exentos el `place_id` y las coordenadas (30 días) | ✅ **hasta 30 días**, *«stored securely»*, *«cannot be manipulated or aggregated in any way»* |
| Respuesta del parque | no | ✅ `reviewReply` (leer y escribir, máx. 4096 bytes) |
| Aviso de reseña nueva | no | ✅ Pub/Sub: `NEW_REVIEW`, `UPDATED_REVIEW` |
| Coste | SKU Enterprise + Atmosphere (el más caro de Place Details) | **gratuita**, con cuota (300 consultas/min por proyecto) |
| Requisitos | una clave de API | **acceso solicitado y aprobado** + OAuth del dueño (§7) |

⚠️⚠️ **Corrige a `google-reviews.md` §3.2**, que daba por buena una excepción de *«temporary caching for
immediate performance optimization»* para Places. **La página de políticas de hoy no la tiene.** Está
anotado allí con la corrección delante del texto.

---

## 2. Objetivo

**Que la portada publique las reseñas reales del parque desde su ficha de Google, todas las que haya y a
todos los visitantes, sin guardar nada más allá de lo que Google permite y sin que Google caído tumbe
nada.**

Criterios de éxito, todos medibles:

1. **Con Google caído hasta 30 días, la sección sigue enseñando las reseñas** (las últimas sincronizadas).
2. **Nada de Google vive más de 30 días** en nuestro sistema: toda fila lleva la hora en que se trajo y
   un proceso la borra al cumplirlos (guarda con reloj congelado).
3. **La portada no gana ninguna llamada HTTP en su render** (`PERF-02`): lee de nuestra base.
4. **La media y el total son los de Google**, nunca calculados por nosotros (*«cannot be aggregated»*).
5. **El texto de cada reseña se publica íntegro** (*«cannot be manipulated»*): se puede cortar a la
   VISTA con `line-clamp`, jamás en el dato.
6. **Una reseña borrada en Google desaparece de la web** en la siguiente sincronización.
7. **El permiso de Google caducado o revocado se DICE** en el panel y por correo al admin: la
   sincronización no puede morir en silencio.
8. **White-label**: credenciales y ficha por instalación; ni un dato de PlayJump en el código.

### Fuera de alcance, explícitamente

- ⛔ **Preguntas y respuestas**: la API se cerró el **3 de noviembre de 2025** (sus tipos de aviso están
  marcados como retirados en `NotificationType`).
- ⛔ **Histórico de más de 30 días** de nada que venga de Google — reseñas ni métricas (§4.5).
- ⛔ **Reseñas de otras fichas o plataformas** (competencia, TripAdvisor, Facebook).
- ⛔ **Premios o descuentos a cambio de reseñas**, y pedírselas solo a los contentos: lo prohíbe la
  política de contenido de Google (§4.8).

---

## 3. Opciones consideradas

| | Opción | Veredicto |
|---|---|---|
| A | **Seguir con Places y alargar la caché** (3 h 30 min) | ❌ Arregla C1 pero choca con C2: la política no permite guardar reseñas, y seguiría dando 5 elegidas por Google |
| B | **Business Profile API** (la del dueño) | ✅ **Elegida**: todas las reseñas, 30 días de margen, gratis, y abre respuestas, avisos, métricas y el enlace de reserva |
| C | **Widget comercial** (Elfsight, Trustindex, EmbedSocial…) | ❌ Script de un tercero en la portada: choca con `RGPD-05` (petición del visitante sin consentimiento), con la CSP (`SEC-01`) y con `PERF-02`; cuota mensual; y los datos del parque en casa de otro |
| D | **Leer la ficha pública sin API** (scraping) | ❌ Prohibido por los términos de Google y frágil: se rompe con cualquier cambio de su HTML |

▶ **La opción no elegida también es información**: A sería el arreglo de una línea que se ofreció al
owner en `#523`, y se descartó al leer la política con la pregunta del owner delante (*«cómo se hace de
manera profesional»*).

---

## 4. Diseño elegido

### 4.0 La forma, en un dibujo

    [owner en el panel] ──OAuth──▶ Google ──refresh token──▶ CIFRADO en nuestra base
                                                                    │
    [comando programado, 1×/día + a demanda] ◀─────────────────────┘
          │  accounts.list · locations.list · reviews.list (paginado)
          ▼
    tablas locales con `fetched_at`  ──purga a los 30 días──▶ ∅
          │
    BusinessProfileSocialProof (futuro)  ─┐
    CmsSocialProof (existe)              ─┼─▶ FallingBackSocialProof (existe) ─▶ la portada (sin cambios)
                                          ┘

**La portada NUNCA habla con Google** (`PERF-02`, la regla de `#491` que se conserva entera). Quien
habla es el comando programado y la conexión del panel.

### 4.1 Por tandas

| Tanda | Qué | Depende de |
|---|---|---|
| **T1** | **La conexión**: pantalla en Ajustes, OAuth, token cifrado, elegir la ficha, comando de verificación | el acceso aprobado (§7·3) |
| **T2** | **Las reseñas**: sincronización, tablas con purga a 30 días, la nueva fuente, la sección siempre a la vista, y **se retira Places** | T1 · decisiones `D2`–`D5` |
| **T3** | **Responder desde el panel** y **aviso de reseña nueva** (primero por sondeo; Pub/Sub después) | T2 · `D6` |
| **T4** | **Métricas de la ficha** en el panel (búsquedas, clics en llamar, web, cómo llegar) | T1 |
| **T5** | **El botón «Reservar» de Google Maps** apuntando a nuestra reserva, con UTM | T1 · `D7` |
| **T6** | **El horario de la web hacia Google** (primero comparar, luego escribir) | T1 · `D8` |
| **T7** | **Pedir reseña tras la visita** (correo con el enlace de Google), carril de correos | T1 · `D9` |

▶ **T1 y T2 son el encargo** («que salgan siempre»). T3–T7 son lo que la misma conexión deja al
alcance y se deciden una a una.

### 4.2 T1 · La conexión

1. **Pantalla** «Ficha de Google» en **Ajustes → Web** (`AdminSettingsHub::areas()`), **solo rol
   `admin`**. Estados que pinta, cada uno con lo que hay que hacer: *sin configurar* (faltan
   credenciales) · *lista para conectar* · *conectada* (cuenta, ficha, última sincronización y su
   resultado) · *permiso caducado o retirado* (botón «Volver a conectar») · *sin acceso a la API* (403 o
   cuota 0: el acceso aún no está aprobado).
2. **Flujo OAuth 2.0 de código de autorización** con `access_type=offline` y `prompt=consent` (sin ellos
   Google no entrega el token de refresco, o no lo vuelve a entregar al reconectar), **PKCE** y un `state`
   de **un solo uso guardado en el servidor**, atado a la sesión del admin que lo pidió — el patrón de
   `SocialLogin` (`#347`): *la intención y quién la pidió viajan en el reto del servidor, jamás en la URL
   de vuelta*.
3. **Un solo ámbito**: `https://www.googleapis.com/auth/business.manage`. No hay uno más estrecho de solo
   lectura; por eso el panel pide confirmación antes de escribir nada en Google (T3, T5, T6).
4. **Elegir la ficha**: `accounts.list` (Account Management) → `locations.list` (Business Information,
   `readMask` = `name,title,storefrontAddress,metadata`). Se guarda el nombre de recurso de la ficha y de
   `metadata`: **`placeId`** (sustituye al ajuste manual `social.google_place_id`), **`mapsUri`** y
   **`newReviewUri`** (el enlace «escribir una reseña», T7).
5. **Dónde viven las credenciales** (`SEC-11`):

   | Dato | Dónde | Por qué |
   |---|---|---|
   | ID y secreto del cliente OAuth | ajustes, **claves protegidas** de `SetSetting` (`--force`), nunca legibles en el panel | el precedente de `GoogleAuth` (`#353`) |
   | **Token de refresco** | ajustes, **cifrado** con `Crypt` (la `APP_KEY`) | es una credencial de ESCRITURA sobre la ficha del negocio: en claro, un volcado de la base la regala |
   | Token de acceso | solo en memoria/caché hasta su caducidad (≈1 h) | se regenera con el de refresco |

   ⚠️ **Rotar la `APP_KEY` obliga a reconectar** (el token cifrado deja de leerse): se escribe en
   `INSTALACION-CLIENTE.md` y el panel lo detecta como *permiso caducado*.
6. ❗❗ **Robustez del permiso — la trampa que mata esto en silencio.** Un token de refresco deja de
   funcionar si: la app OAuth está **«En prueba»** (caduca a los **7 días**), el dueño lo **retira**, **no se
   usa en 6 meses**, se supera el **tope de 100 tokens vivos** por usuario y cliente, o un administrador de
   Workspace **restringe** el servicio. ▶ Por eso: la app va **«En producción»** (§7·5) · la sincronización
   diaria lo mantiene en uso · y **`invalid_grant` no se reintenta**: marca la conexión como caducada,
   avisa al admin (panel + correo) y **deja servir lo ya sincronizado** hasta la purga de 30 días.
7. **Desconectar**: revoca el token en Google (endpoint `revoke`), lo borra de la base y **borra lo
   sincronizado** (reseñas, avatares, métricas). Queda rastro en `audit_logs` sin PII (`RGPD-02`).
8. **`business-profile:verify`** (futuro): lista cuenta, ficha, número de reseñas y el estado de cada API
   **sin imprimir ni una reseña ni un nombre**. Es el `redsys:verify-sandbox` de esta integración: no se da
   por buena sin haber hablado con Google de verdad (§6).

### 4.3 T2 · Las reseñas

1. **Sincronización** (`business-profile:sync` (futuro), programado **1× al día** y lanzable desde el
   panel): `reviews.list` con `pageSize=50` y `orderBy=updateTime desc`, **paginando hasta el final**.
   Una sola llamada por pasada y ficha —**no una por idioma**: esta API no traduce como Places—.
   ⚠️ Con `withoutOverlapping()` y **reintento con espera creciente** ante 429/5xx (la doc lo pide).
2. **Guardado — tablas propias, no la caché** (Redis está en `allkeys-lru`, `#137`, y evicta):

       google_business_reviews (futuro)   review_id (único) · star_rating · comment (íntegro)
                                          · reviewer_name · reviewer_anonymous · avatar_path?
                                          · created_on_google · updated_on_google
                                          · reply_comment · reply_updated · fetched_at
       google_business_summary (futuro)   average_rating · total_review_count · maps_uri
                                          · new_review_uri · fetched_at

3. **Las cuatro reglas de la política, hechas código**:
   - **≤ 30 días**: toda fila lleva `fetched_at`; el `Prunable` borra lo que pase de 29 días (un día de
     margen) y **entra en la lista de `routes/console.php`** — ⚠️ *un `Prunable` que no está en esa lista
     no se poda NUNCA* (lo midió `waiver-por-reserva.md`).
   - **Borrada en Google → borrada aquí**: la pasada que llega al final sin error reemplaza el conjunto;
     lo que no volvió, se borra. ⚠️ **Una pasada cortada a medias NO borra nada** (si no, un 500 en la
     página 3 vaciaría la sección).
   - **Sin manipular**: el texto se guarda y se publica tal cual; el recorte es visual (`line-clamp`) con
     enlace a la reseña en Google.
   - **Sin agregar**: la media y el total salen de `averageRating`/`totalReviewCount`. Jamás un `AVG()`
     nuestro.
4. **La nueva fuente**: `BusinessProfileSocialProof` (futuro) implementa `SocialProof` leyendo las
   tablas (1–2 consultas indexadas, memo por petición) y **sustituye a `GoogleSocialProof` en el binding
   de `AppServiceProvider`**. `FallingBackSocialProof` y `CmsSocialProof` no cambian.
5. ❗❗ **«A todos los visitantes» — la mitad que decide `D2`.** Hoy las reseñas de Google solo se ven con
   cookies de terceros aceptadas porque el avatar es una petición del navegador a
   `lh3.googleusercontent.com` (`RGPD-05`). Con esta API se pueden **guardar los avatares 30 días y
   servirlos desde nuestro dominio**: el visitante no hace ninguna petición a Google, la sección se ve sin
   consentimiento **y la CSP se ESTRECHA** (`img-src` deja de necesitar a Google). ⚠️ Los avatares se
   guardan **como llegan**, sin redimensionar (*«cannot be manipulated»*): el tamaño lo pone el CSS.
6. ⚠️⚠️ **El texto puede traer la traducción de Google DENTRO** — y no está documentado. La comunidad lo ha
   medido: el comentario llega a veces como `«(Translated by Google) … (Original) …»`, **en orden variable**
   y a veces sin la segunda marca, y las marcas se traducen al idioma de la cabecera `Accept-Language`.
   ▶ **Antes de escribir el analizador se MIDE con la ficha real** (§6·T2·1) pidiendo con `Accept-Language`
   de los tres idiomas del sitio, que según esa medida devuelve los originales sin traducción. La regla que
   se busca: **publicar el ORIGINAL**, que es lo que el autor escribió.
7. **Anónimos**: `isAnonymous` llega sin nombre ni foto. Se publican con un rótulo genérico traducido
   («Usuario de Google») y sin avatar — `D5`.
8. **Qué se enseña en la portada** (`D3`, `D4`): las N más recientes con texto, **con la respuesta del
   parque debajo** (`reviewReply`: es la señal de atención que más pesa en una reseña), la media y el total
   reales, y dos enlaces: «Ver las N reseñas en Google» (`mapsUri`) y «Escribir una reseña»
   (`newReviewUri`).
9. **Atribución**: se conserva la de `#494` —el logotipo de Google Maps, el rótulo de procedencia y el
   aviso de que Google no verifica las reseñas—. Esta API no la exige con la misma letra que Places, pero
   distinguir lo de Google de lo propio es honestidad, no un requisito que sobrar.
10. **Se retira Places** cuando la T2 esté verificada con la ficha real: fuera `GoogleSocialProof`, el
    comando `social-proof:refresh` y su línea del programador, la clave de Places del `.env` y el SKU que
    se paga. Con sus tests, clasificados por sujeto (`CONVENCIONES §3.quater`).

### 4.4 T3 · Responder desde el panel y enterarse de las nuevas

- Lista en el panel con filtro «sin responder»; **responder, editar y borrar la respuesta**
  (`updateReply`/`deleteReply`, máx. 4096 bytes). Permiso propio (`reviews.reply` (futuro)), rastro en
  `audit_logs` sin el texto. ⚠️ **Tope de Google: 10 ediciones por minuto y ficha**, sin posibilidad de
  subirlo.
- **Aviso de reseña nueva** (`D6`): **primero por sondeo** —la sincronización ligera de la primera página
  cada hora detecta lo nuevo— y **después, si compensa, Pub/Sub** (`NEW_REVIEW`, `UPDATED_REVIEW`): tema de
  Cloud Pub/Sub con permiso de publicación para `mybusiness-api-pubsub@system.gserviceaccount.com` y
  suscripción *push* a un endpoint nuestro que **verifica el JWT firmado de Google** antes de hacer nada.
  ▶ Por sondeo no hace falta infraestructura nueva; por Pub/Sub el aviso llega en segundos.

### 4.5 T4 · Métricas de la ficha

La Performance API da, por día: impresiones en **Búsqueda y Maps** (móvil y escritorio), **clics en
llamar** (`CALL_CLICKS`), **clics en la web** (`WEBSITE_CLICKS`), **peticiones de cómo llegar**
(`BUSINESS_DIRECTION_REQUESTS`), conversaciones y reservas por Reserve with Google (`BUSINESS_BOOKINGS`),
más las **palabras de búsqueda** con las que la encontraron, por mes.

⚠️⚠️ **Y aquí el límite de 30 días muerde de verdad**: la política cubre *todo el contenido* de las APIs,
así que **no se puede guardar un histórico largo de métricas**. El panel las pide al abrirse (con caché de
horas, nunca de más de 30 días) y enseña la ventana que Google devuelve. Lo que SÍ se puede cruzar sin
límite es lo NUESTRO: visitas llegadas desde Google (UTM, T5) y reservas cerradas.

### 4.6 T5 · El botón «Reservar» de Google Maps

La Place Actions API crea un enlace de acción de tipo **`MERCHANT`** marcado `isPreferred` hacia
`/entradas` con parámetros UTM, para medir en nuestra propia analítica cuánta venta llega de la ficha.
⚠️ Los tipos de acción son de citas y comida (`APPOINTMENT`, `ONLINE_APPOINTMENT`, `DINING_RESERVATION`,
`FOOD_*`, `SHOP_ONLINE`); **cuál encaja con entradas de un parque se comprueba en la ficha real** y lo
decide el owner (`D7`). Los enlaces de agregadores (`AGGREGATOR_3P`) no son editables.

### 4.7 T6 · El horario, una sola fuente de verdad

Hoy el horario vive en el panel (horario semanal, temporadas, fechas especiales) y **en la ficha de
Google por separado**: si alguien cierra un festivo en el panel y no en Google, Maps dice «abierto».
▶ **Primero comparar** (la pantalla dice *«tu ficha dice X, la web dice Y»*) y **solo después, con `D8`,
escribir** `specialHours`/`regularHours` desde el panel. ⚠️ Escribir en la ficha pasa por la moderación
de Google y tiene el mismo tope de 10 ediciones por minuto.

### 4.8 T7 · Pedir la reseña tras la visita (carril de correos)

Un correo tras la visita con el enlace `newReviewUri`. Es la palanca que más mueve la sección —el parque
tiene 2 reseñas— y la política de Google marca las líneas rojas:

- ✅ **A TODOS los clientes por igual**, satisfechos o no;
- ❌ **nada de premios, descuentos ni sorteos** a cambio;
- ❌ **nada de filtrar** («¿te gustó? → Google; ¿no? → a nosotros»): es *review gating* y está prohibido;
- ❌ **nada de presión en el parque** ni de pedir contenido concreto.

⚠️ **Y una pregunta legal que no es del agente** (`D9`): si ese correo cuenta como comunicación
comercial (LSSI) y exige el consentimiento de marketing, o puede ir como seguimiento del servicio.

### 4.9 Lo que también da y no se propone ahora

Publicaciones en la ficha (ofertas, eventos, con botón), fotos (subirlas y ver las de clientes), llamadas
perdidas (Business Calls, requiere historial de llamadas activo). Quedan anotadas; ninguna tiene
pantalla que las pida hoy.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **PERF-02** | La portada lee de nuestra base (1–2 consultas indexadas). Presupuesto de `HomePageTest` medido **antes y después** |
| **SEC-01** | Con `D2` (avatares desde nuestro dominio) la CSP **se estrecha**: `img-src` deja de nombrar a Google |
| **SEC-07** | `mapsUri`, `newReviewUri` y los enlaces de reseña son URL de un tercero: se sanean donde nace el dato |
| **SEC-11** | Secreto del cliente y token de refresco fuera del panel; el token, **cifrado**. ▶ Se propone ampliar la invariante: *«una credencial de un tercero con permiso de escritura se guarda cifrada»* (del owner, `CONVENCIONES §9.1`) |
| **RGPD-02** | Ni el texto de una reseña ni el nombre de su autor llegan al log ni a `audit_logs` |
| **RGPD-05** | Con `D2` las reseñas **dejan de depender del consentimiento** de terceros; sin `D2`, se conserva el de hoy |
| **RGPD** (nuevo tratamiento) | Nombre y foto de los autores son datos personales de terceros guardados hasta 30 días: interés legítimo, mención en la política de privacidad y borrado al desaparecer en Google — `D2` lo trae al owner |
| **SUITE** | La suite **no habla con Google**: todo con `Http::fake`, y un caso que demuestra que el falso explota si alguien llama desde el render |

---

## 6. Plan de verificación empírica

**T1** — casos: el `state` de un solo uso (reutilizado → rechazado; de otra sesión → rechazado) · sin
`refresh_token` en la respuesta → error dicho, no conexión a medias · `invalid_grant` → estado caducado +
aviso, **sin reintento** · el token guardado **no es legible** en la base (cifrado) ni en el panel ·
desconectar revoca y borra · solo `admin`. Y **`business-profile:verify` contra la ficha real**, con su
salida pegada en la decisión.

**T2** — ① **medición previa con la ficha real**: formato del comentario con y sin `Accept-Language`
múltiple (la trampa de §4.3·6), campos presentes, anónimos, respuestas; ② casos: paginación hasta el
final · pasada cortada **no borra** · reseña borrada en Google se borra aquí · purga a 30 días **con reloj
congelado** (`travel()`, nunca `time()`) · el `Prunable` está en la lista del programador · la media es
la de Google aunque las filas digan otra cosa · texto íntegro · anónimo sin foto · `SocialProofNeverHitsTheRenderPathTest`
re-apuntado a la nueva fuente · presupuesto de la portada · con `D2`, la portada sin consentimiento
**sí** pinta las reseñas y **ninguna** petición sale a Google (sonda de navegador contando peticiones);
③ arnés de mutación con control y por código de salida.

**T3–T7** — cada una con su guarda y su verificación contra la ficha real antes de darla por buena.

---

## 7. Guía de configuración para el owner — paso a paso

⚠️ **Nada de esto lo puede hacer un agente**: son pasos en tu cuenta de Google. Los pasos 1–6 se pueden
hacer YA, mientras se construye la T1.

1. **Comprueba la ficha.** Tiene que estar **verificada y activa desde hace más de 60 días**, tener **la
   web del parque** puesta, y tu cuenta de Google tiene que ser **propietaria o administradora** de la
   ficha. Sin esto Google no aprueba el acceso.
2. **Crea un proyecto de Google Cloud** en `console.cloud.google.com` → selector de proyectos → *Nuevo
   proyecto* (por ejemplo «PlayJump Business Profile»). ▶ **Recomendado: un proyecto APARTE** del de
   *Iniciar sesión con Google* y del de Places: el ámbito de esta API es **sensible**, y metido en el
   proyecto del login pondría la pantalla de consentimiento de tus clientes bajo esas reglas. Apunta el
   **número de proyecto** (panel del proyecto, *Project number*).
3. **Pide el acceso a la API**: formulario de contacto de la API de Google Business Profile → *Application
   for Basic API Access*, **con el correo que es propietario/administrador de la ficha** y el número de
   proyecto. Espera el correo de aprobación (Google no da plazo). **Cómo saber si ya está**: en la consola,
   *APIs y servicios → Cuotas*: con **0** consultas por minuto no está aprobado; con **300**, sí.
4. **Habilita las APIs** (*APIs y servicios → Biblioteca*): **My Business Account Management API** ·
   **My Business Business Information API** · **Google My Business API** (solo aparece tras la
   aprobación) · **Business Profile Performance API** · **My Business Place Actions API**. Más adelante,
   si se hace Pub/Sub (T3): **My Business Notifications API** y **Cloud Pub/Sub API**. **No hacen falta**:
   Lodging, Verifications ni Business Calls.
5. **Pantalla de consentimiento OAuth** (*Google Auth Platform*):
   - **Tipo de usuario: Externo.** Nombre de la app: el del parque. Correo de asistencia: el tuyo.
   - **Sin logotipo**: subir un logotipo obliga a verificar la marca.
   - **Dominio autorizado**: el de la web del parque. **Página principal** y **política de privacidad**:
     sus URL públicas (la del enlace «Privacidad» del pie).
   - **Acceso a datos → añadir ámbito**: `https://www.googleapis.com/auth/business.manage`.
   - ❗❗ **Estado de publicación: «En producción».** **No lo dejes en «En prueba»**: ahí Google caduca el
     permiso a los **7 días** y la sincronización se pararía sola cada semana.
   - ▶ **No hace falta pasar la verificación de Google**: la app solo la autorizas tú, y eso entra en la
     excepción de *uso personal* («un solo usuario o unos pocos que conoces»). La primera vez verás
     *«Google no ha verificado esta aplicación»* → *Configuración avanzada* → *Ir a …*. Si algún día la
     autorizan otras personas, entonces sí: verificación de 3–5 días hábiles (política de privacidad en el
     mismo dominio, dominio verificado en Search Console y un vídeo de demostración).
6. **Crea el cliente OAuth** (*Clientes → Crear cliente → Aplicación web*):
   - **URI de redirección autorizada**: la de producción, `https://<dominio del parque>/admin/google-business/vuelta`
     (futuro) — ⚠️ **te confirmo la ruta exacta al cerrar la T1**, antes de que la pegues.
   - Para desarrollo, la misma ruta sobre `http://localhost:8081`.
   - Guarda el **ID de cliente** y el **secreto**.
7. ❗❗ **Entrégalos por la vía segura, NO por un chat** (la clave de Places se pegó en uno y hubo que
   pedir rotarla): o los escribes tú en el servidor con `php artisan app:set-setting … --force` (te daré
   las dos órdenes exactas en la T1), o los pegas en la pantalla del panel, que los guarda sin volver a
   enseñarlos.
8. **Conecta** (cuando esté la T1): *Ajustes → Web → Ficha de Google → Conectar con Google* → eliges tu
   cuenta → aceptas → eliges la ficha del parque → primera sincronización. El panel dice qué ha traído.
9. **Después de verificar la T2**: en la consola de Places, **retira la clave** o déjala sin el SKU de
   reseñas — ya no se usa y deja de facturarse.

---

## 8. Decisiones del owner (con recomendación)

| | Pregunta | Recomendación |
|---|---|---|
| **D1** | ¿Un proyecto de Google por instalación (cada cliente el suyo) o uno central de JumpWeb verificado, al que cada parque solo le da permiso? | **Por instalación para empezar** (PlayJump), con el código listo para las dos. El central es mejor producto a escala, pero exige que JumpWeb pase la verificación y registre los dominios de todos |
| **D2** | ¿Servir los avatares desde nuestro servidor (30 días) para que las reseñas se vean **sin consentimiento de cookies**? | **Sí** — es lo que hace «a todos los visitantes» de verdad y estrecha la CSP. ⚠️ Añade un tratamiento de datos de terceros: requiere la línea en la política de privacidad |
| **D3** | ¿Cuántas en la portada y en qué orden? | **Las 6 más recientes con texto**, con la respuesta del parque debajo |
| **D4** | ¿Filtrar por estrellas en la portada? | **No filtrar.** La media real sale igual; enseñar solo las de 5 estrellas junto a una media de 3,8 se lee como trampa. Si se filtra, se dice («seleccionadas por el parque») |
| **D5** | ¿Publicar las reseñas anónimas? | **Sí**, con «Usuario de Google» y sin foto |
| **D6** | Aviso de reseña nueva: ¿por sondeo cada hora o Pub/Sub en segundos? | **Sondeo primero**; Pub/Sub solo si se echa de menos la inmediatez |
| **D7** | ¿Qué tipo de acción para el botón «Reservar» de Google? | Se comprueba en la ficha real antes de decidir |
| **D8** | ¿Que la web ESCRIBA el horario en Google, o solo que avise de las diferencias? | **Solo avisar al principio**; escribir cuando se haya visto que coincide |
| **D9** | El correo de «déjanos tu reseña», ¿solo a quien aceptó marketing o a todo cliente como seguimiento? | Consulta legal; **por defecto, solo con consentimiento de marketing** |

---

## 9. Riesgos y trampas conocidas

- **La app «En prueba»** → permiso de 7 días → sincronización muerta cada semana (§4.2·6, §7·5).
- **Un token de refresco sin uso 6 meses** caduca: la sincronización diaria lo evita, y `invalid_grant`
  se dice en vez de reintentarse.
- **La traducción mezclada en el texto** (§4.3·6): no está documentada; se mide antes de analizarla.
- **No hay entorno de pruebas** en esta API: toda verificación real es contra la ficha de verdad, y las
  escrituras (T3, T5, T6) **usan `validateOnly`** donde la API lo admite antes de escribir.
- **`Prunable` fuera de la lista del programador** no se poda nunca (§4.3·3).
- **Rotar la `APP_KEY`** deja ilegible el token cifrado → reconectar (§4.2·5).
- **Cuota**: 300 consultas/min por proyecto y **10 ediciones/min por ficha**, este último sin ampliación.

---

## 10. Revisión y decisión

- **Investigado por el agente** el 2026-09-11 contra la documentación oficial —políticas de Places y de
  Business Profile, `reviews.list`, requisitos de acceso, configuración básica, OAuth, avisos,
  métricas, enlaces de acción, límites de uso, verificación de ámbitos sensibles y caducidad de tokens— y
  contra el código del repo (`GoogleSocialProof`, el contrato `SocialProof`, `FallingBackSocialProof`,
  `GoogleAuth`, `SetSetting`).
- **Decidido por el owner** el 2026-09-11: ir por Business Profile, *«de manera profesional, al detalle
  y robusta»*.
- ⚠️ **Pendiente antes de escribir código**: las decisiones `D1`–`D9` (al menos `D1`–`D5` para T1–T2),
  **una revisión adversarial de esta spec** (`CONVENCIONES §5`) y **el acceso a la API aprobado** (§7·3).
- **Fuentes**: [políticas de Places](https://developers.google.com/maps/documentation/places/web-service/policies)
  · [políticas de Business Profile](https://developers.google.com/my-business/content/policies)
  · [datos de reseñas](https://developers.google.com/my-business/content/review-data)
  · [`reviews.list`](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews/list)
  · [recurso `Review`](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews)
  · [requisitos](https://developers.google.com/my-business/content/prereqs)
  · [configuración básica](https://developers.google.com/my-business/content/basic-setup)
  · [OAuth](https://developers.google.com/my-business/content/implement-oauth)
  · [avisos](https://developers.google.com/my-business/content/notification-setup)
  · [`NotificationSetting`](https://developers.google.com/my-business/reference/notifications/rest/v1/NotificationSetting)
  · [métricas diarias](https://developers.google.com/my-business/reference/performance/rest/v1/DailyMetric)
  · [enlaces de acción](https://developers.google.com/my-business/reference/placeactions/rest/v1/locations.placeActionLinks)
  · [fichas](https://developers.google.com/my-business/reference/businessinformation/rest/v1/locations)
  · [límites](https://developers.google.com/my-business/content/limits)
  · [verificación de ámbitos sensibles](https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification)
  · [caducidad de tokens](https://developers.google.com/identity/protocols/oauth2)
  · [política de contenido de Maps](https://support.google.com/contributionpolicy/answer/7400114?hl=en)
  · [reseñas sin traducción (medida de la comunidad)](https://ambience.sk/google-business-profiles-api-reviews-without-translation/).
