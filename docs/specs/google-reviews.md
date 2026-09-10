# [SPEC] Reseñas de Google en la landing — la prueba social que el parque NO controla

> Estado: ✅ **EJECUTADA ENTERA** — mitad `a` en `DECISIONES #490` y mitad `b` en `#491`
> (2026-09-10) · Última actualización: 2026-09-10.
>
> ❗❗❗ **§4.4.bis TENÍA UNA AMBIGÜEDAD Y ESTÁ RESUELTA, y la corrección va delante del texto**:
> afirmaba «sin consentimiento la cabecera no se pinta» **sin argumentarlo** —su razón escrita era no
> inventar la cifra, que es otra cosa—. ▶ **La CIFRA no necesita consentimiento**: la trae nuestro
> servidor, el visitante no hace ninguna petición a Google, no lleva autor ni foto y una media de un
> negocio no es dato personal. **Las RESEÑAS sí**, porque R3 obliga a la foto del autor y cargarla
> **sí** es una petición del visitante (`RGPD-05`). Las dos cosas se gobiernan por separado en
> `FallingBackSocialProof`.
>
> ❗❗❗ **LO PRIMERO, PORQUE CAMBIA LA SECCIÓN: el parque tiene UNA reseña en Google.** Verificado
> contra la API el 2026-09-10 (§6·5, la salida está en `DECISIONES #490`): HTTP 200, `rating: 5`,
> `userRatingCount: 1`. Con una, la chapa diría «5,0 · 1 reseña» —que resta— y una segunda de 1
> estrella publicaría un 3,0 al día siguiente. ▶ `[DECIDIDO owner, 2026-09-10]`: **umbral de 10
> reseñas**; por debajo, la sección va entera con las opiniones propias y Google no se toca.
>
> ✅ **Y esa misma verificación cierra dos de los tres datos que faltaban**: el `place_id` es el del
> parque y el campo `reviews` trae la atribución completa —`displayName`, `photoUri`, `uri`—, con el
> avatar en `lh3.googleusercontent.com`, exactamente donde §3.3 lo midió. Falta el **tope de gasto**.
>
> ❗❗ **«QUE LA CHAPA SE VEA SIEMPRE» NO ES IMPLEMENTABLE**, y el owner lo preguntó con razón: no es
> mentir, es que **R2 prohíbe almacenarla** más allá de una caché corta. Lo que sí se consigue es que
> esté puesta **prácticamente siempre**, refrescando **cada hora** (~720 llamadas/mes, gratis).
>
> ⚠️⚠️ **Y hay una AMBIGÜEDAD en esta spec que juega a favor del owner y hay que resolver en la `b`**:
> §4.4.bis afirma «sin consentimiento la cabecera no se pinta» **sin argumentarlo** —su razón escrita
> es no inventar la cifra, que es otra cosa—. La cifra la trae **nuestro servidor**, no tiene autor ni
> foto y no es dato personal, así que **puede que no necesite consentimiento**. Se decide con su
> argumento delante, no por inercia.
>
> ⚠️⚠️ **Empieza por §1.3.** Esta integración tiene **cinco restricciones DURAS** que no son
> negociables con Google y que cambian el diseño, no lo decoran. Tres de ellas chocan de frente con
> invariantes que este repo ya tiene endurecidos (`SEC-07`, `RGPD-05`, `PERF-02`). Diseñar sin
> tenerlas delante produce una integración que funciona en local y es insostenible en producción.
>
> ⚠️ **Nace de un encargo del owner** (2026-08-25), al dimensionar la tanda B de
> `specs/landing-white-label.md`: los testimonios del segundo cliente **no se teclean, se traen de
> Google**.
>
> ❗❗ **Lo que hay que saber si solo se leen tres líneas:**
> 1. **Google es la fuente de verdad y el parque NO elige qué sale en su portada** (§1.4). Está
>    decidido y asumido, pero es una cesión de control real.
> 2. **De las dos opciones de respaldo del owner, el «snapshot de Google» NO se puede hacer**: la
>    política lo prohíbe y no hay excepción de 30 días (§3.2). El respaldo son reseñas propias.
> 3. **El respaldo NO es para cuando Google falle**: es lo que ve **todo visitante que no acepta
>    cookies de terceros**, cada día (§3.3). Si se documenta como plan de emergencia, el parque lo
>    dejará vacío.

---

## 1. Contexto y problema — MEDIDO, no supuesto

### 1.1 De dónde viene

El mockup del segundo cliente tiene una sección **«Opiniones · Lo dicen ellos»** con tres tarjetas
—`{{ o.texto }}`, `{{ o.nombre }}`, `{{ o.meta }}` y cinco estrellas— y una cabecera que dice
literalmente **«4,8 sobre 5 · 320 reseñas en Google»**.

▶ Medido sobre el mockup: de **todos** los datos numéricos que pide, la valoración agregada es **el
único que el dominio no sabe calcular**. Los tres `statsParque` (`7.000` m², `13` juegos, `2` zonas)
salen de `zones`, y packs, entradas, ofertas y horarios ya tienen casa.

### 1.2 Qué hay hoy en el repo

    Google Maps embebido      `address.maps_url` + `Content\Services\MapsEmbed` (allowlist de host)
    JSON-LD                   `Content\Services\StructuredData` (resultados enriquecidos)
    Consentimiento            categoría «mapa»: el iframe nace con `data-src`, no `src`
    API keys de Google        NINGUNA
    Llamadas a Places API     NINGUNA

▶ **Es construcción desde cero.** Lo único reutilizable es el patrón: el repo ya trata a Google como
**tercero con implicaciones RGPD** y lo bloquea hasta que el visitante consiente.

### 1.3 ⚠️⚠️ Las CINCO restricciones DURAS, verificadas contra la documentación oficial

Ninguna es opinión. Cada una se comprobó contra `developers.google.com/maps/documentation/places`
el 2026-08-25 y **cada una elimina alguna opción de diseño**.

| | Restricción | Qué elimina |
|---|---|---|
| **R1** | **Máximo 5 reseñas**, ordenadas **por relevancia**, sin parámetro de orden ni de selección | ❌ El control editorial. **El parque NO elige qué sale en su propia portada** |
| **R2** | **«You must not pre-fetch, cache, or store Places API content beyond the allowed exceptions.»** El **`place_id` es la única excepción** exenta indefinidamente | ❌ Guardar las reseñas en BD. Y deja la caché en un terreno que hay que decidir (§3) |
| **R3** | **Atribución obligatoria**: *«You must always credit the author when displaying photos or reviews»* — **avatar, nombre y enlace al perfil** | ❌ Servir solo el texto. Y choca con `img-src 'self' data:` (§5) |
| **R4** | **Prohibido alterar el contenido del usuario**: nada de recortar, corregir ni re-capitalizar | ❌ Truncar una reseña larga para que quepa en la tarjeta |
| **R5** | Campo `reviews` ⇒ SKU **Enterprise + Atmosphere**, el tier más caro de Place Details | ❌ Que el coste sea fijo: **escala con el tráfico de la home** |

⚠️ **Y una sexta que aplica a este producto en concreto**: en **Francia** hay que mostrar el mes y
año de la visita junto a la reseña. La landing es **ES/EN/FR**, así que no es hipotético.

### 1.4 Lo que R1 significa en términos de producto, dicho sin adornos

La sección se llama **«Lo dicen ellos»** y va en la portada. Con Google como única fuente:

- el parque **no puede destacar** la reseña que mejor cuenta lo que hace;
- **no puede retirar** una injusta, un troll o una de la competencia;
- una reseña de **1 estrella** que Google considere «relevante» **se publica en su portada**, y el
  operador solo puede reportarla a Google y esperar;
- el contenido de la portada **cambia solo**, sin que nadie del parque lo decida ni se entere.

▶ Esto **no es un defecto de la integración: es lo que la integración ES.** Está aquí escrito porque
es una decisión de producto del owner, y tiene que tomarse sabiéndolo. `#136` fijó la línea del
proyecto en «data-driven el DATO»; aquí el dato lo posee un tercero.

---

## 2. Objetivo

**Que la landing publique la prueba social real del parque desde Google, sin romper el presupuesto de
la home, sin guardar lo que no se puede guardar y sin que una caída de Google tumbe la portada.**

Criterios de éxito, todos medibles:

1. **La home no gana ni una consulta de BD ni una llamada HTTP en su render.** `PERF-02` la mide con
   presupuesto aseverado (`< 120` consultas): la integración entra **fuera del camino de render**.
2. **Cero reseñas en BD** (R2). Lo único persistido es el `place_id`, que la política exime.
3. **Con la API caída, agotada o sin configurar, la home sirve 200** y la sección desaparece — el
   patrón que el producto ya usa con `heroStatus`.
4. **La atribución es completa y no se puede desactivar** desde el panel (R3): quitarla es incumplir.
5. **Coste ACOTADO y conocido**: un número máximo de llamadas por día, con el techo declarado.
6. **White-label**: `place_id` por instalación desde el panel; la clave de API **nunca** en BD.

### Fuera de alcance, explícitamente

- ⛔ **Responder reseñas** desde el panel (es Business Profile API, otra API y otro permiso).
- ⛔ **Sincronizar el histórico** de reseñas: R1 da 5 y no hay paginación.
- ⛔ **Traducir las reseñas** nosotros (R4). Google ya devuelve `text` traducido y `originalText`.
- ⛔ Reseñas de otras plataformas (TripAdvisor, Facebook).

---

## 3. Las tres decisiones — **RESUELTAS por el owner el 2026-08-25**

### 3.1 ✅ [DECIDIDO owner] **Google es la FUENTE DE VERDAD; el CMS es el respaldo**

> «Google será la fuente de verdad, y nosotros tendremos fallback si algo falla, algunas reseñas
> escritas a mano, o un snapshot de las de Google.»

▶ Se asume lo que §1.4 describe: **el parque no elige qué reseñas de Google salen**. A cambio gana la
autoridad de la fuente, que es lo que el mockup enseña en grande.
▶ Y el respaldo son **reseñas propias escritas a mano en el CMS** (§3.2 explica por qué el snapshot
no puede ser).

### 3.2 ✅ [DECIDIDO, con una CORRECCIÓN medida] Caché corta SÍ · snapshot NO

⚠️⚠️ **De las dos opciones de respaldo que planteó el owner, una NO es viable.** Verificado contra
los *Google Maps Platform Service Terms* el 2026-08-25:

| | Veredicto |
|---|---|
| **Caché corta en memoria** | ✅ **PERMITIDA**, y explícitamente: hay excepción para *«temporary caching for immediate performance optimization»* — caché de corta duración, en memoria, refrescada con regularidad. Es lo que resuelve el coste de R5 |
| **Snapshot persistente de las reseñas de Google** | ❌ **PROHIBIDO.** «Reseñas y ratings» están en la lista de contenido **no cacheable**, y la excepción anterior es explícitamente **«NO para almacenamiento persistente»**. **No hay ningún límite de 30 días** que lo habilite: el `place_id` es la única excepción indefinida |
| **Reseñas escritas a mano en el CMS** | ✅ Sin restricción: es **contenido propio**, no contenido de Places |

▶ **Por eso el respaldo es el CMS y no un snapshot.** No es una preferencia de diseño: es la única de
las dos opciones que se puede sostener.

⚠️⚠️ **Y una trampa medida en este repo que aplica de lleno** (`DECISIONES #137`): Redis está con
**`allkeys-lru`**, así que **puede EVICTAR una clave antes de su TTL** — fue el motivo por el que el
pase de Redsys se sacó de la caché. Traducido: la caché de reseñas **se evapora cuando le toque**, y
el diseño trata «no está» como el caso NORMAL, no como el fallo.

### 3.3 ✅ [RESUELTO por construcción] El avatar: es lo que ata el respaldo al consentimiento

R3 exige mostrar la foto del autor, que vive en `lh3.googleusercontent.com`. Medido: la directiva
`img-src` de `Http\Middleware\SecurityHeaders::contentSecurityPolicy()` vale hoy

    img-src 'self' data:      ← la bloquea

Y cargarla es **una petición del visitante a Google**, exactamente lo que `RGPD-05` gestiona con la
categoría «mapa» (el iframe nace con `data-src` hasta que se consiente).

Las tres salidas posibles y por qué solo una cierra:

| | Veredicto |
|---|---|
| **Proxear las fotos** por nuestro servidor | ❌ Es «store» de contenido de Places, y la política nombra **las fotos** entre lo no cacheable |
| **Servir las reseñas SIN avatar** cuando no hay consentimiento | ❌ Incumple R3: la atribución con foto es obligatoria, no recomendada |
| **Ampliar `img-src` + servir las de Google SOLO con consentimiento** | ✅ **La única que cumple las dos normas a la vez** |

▶ **Y aquí es donde el respaldo deja de ser un plan B.** Sin consentimiento no se pueden servir las
reseñas de Google **en absoluto** —ni con avatar (CSP/RGPD) ni sin él (R3)—, así que el visitante que
no consiente ve **las del CMS**, que son contenido propio y no piden permiso a nadie.

❗❗ **Consecuencia que cambia el encuadre del trabajo: el respaldo NO es para cuando Google falle.**
Cubre **tres** casos, y el tercero pasa todos los días:

1. Google caído, cuota agotada o sin `place_id` configurado;
2. la caché evictada por `allkeys-lru` y la llamada aún en vuelo;
3. **el visitante no ha aceptado cookies de terceros** — que es un porcentaje real del tráfico, no
   una excepción.

⚠️ **Traducido para el operador**: las reseñas escritas a mano **no son un adorno de emergencia**; son
lo que ve una parte de sus visitantes cada día. El panel tiene que decirlo con esas palabras, o el
parque las dejará vacías creyendo que nunca se usan.
⚠️ **No medible todavía**: `cookie_consent_logs` tiene **1 fila** (desarrollo), así que el porcentaje
real de consentimiento **no se conoce**. Se mide en producción antes de dar por buena ninguna
suposición sobre cuánta gente ve una u otra fuente.

---

## 4. Diseño elegido

### 4.0 La regla que ordena todo lo demás

**Google manda cuando puede; el CMS responde siempre.** En una sola frase:

    ¿hay consentimiento de terceros?  ─no→  CMS
              │sí
    ¿hay place_id y clave?            ─no→  CMS
              │sí
    ¿la caché tiene reseñas frescas?  ─sí→  Google (0 llamadas)
              │no
    ¿Google responde a tiempo?        ─no→  CMS
              │sí
              └─→ Google, y a la caché corta

⚠️ **Ninguna de las cuatro salidas hacia el CMS es un error**, y ninguna se registra como tal: son
los cuatro estados normales del sistema. Un log de error por visitante sin consentimiento llenaría el
log de ruido y escondería los fallos de verdad.

### 4.1 La forma, sea cual sea la fuente

**Un contrato de dominio, no una llamada suelta.** Es lo que ha ordenado todas las integraciones de
este repo (`Booking\Contracts\*`, `Payments\Contracts\*`) y lo que hace que la landing no sepa de
dónde vienen los datos:

    Content\Contracts\SocialProof            (futuro)  ← lo ÚNICO que la landing conoce
      ├─ rating(): ?Rating                             cifra agregada: valor, recuento, enlace, fuente
      └─ testimonials(): Collection<Testimonial>       tarjetas: texto, autor, avatar?, fecha, enlace?

    Content\Services\GoogleSocialProof        (futuro)  ← Places, con caché corta
    Content\Services\CmsSocialProof           (futuro)  ← el respaldo, desde `testimonials` (futuro)
    Content\Services\FallingBackSocialProof   (futuro)  ← EL DE ARRIBA con el de abajo detrás

▶ **Por qué así y no un `GoogleReviewsService` a secas**: `#136` fijó que la landing consume DATOS,
no proveedores. Con el contrato, la vista **no sabe de dónde vienen** ni tiene un `@if` por fuente, y
`ModuleContractsTest` ya vigila que un contrato tenga consumidor real.

⚠️⚠️ **Y el respaldo es un DECORADOR, no un `if` repartido por la vista.** `FallingBackSocialProof`
recibe los otros dos y aplica la cascada de §4.0 en **un solo sitio**. Es deliberado: un respaldo
escrito como condicional en la plantilla acaba con una rama sin cubrir —y la rama sin cubrir de un
respaldo es, por definición, la que solo se ejecuta cuando algo va mal—.

▶ **Cada `Testimonial` lleva de dónde viene** (`source`: `google` | `cms`). No es metadato ocioso:
**la atribución que R3 exige depende de ello** y la vista tiene que poder pintar el avatar y el
enlace solo cuando toca. Que el dato lo diga evita que la vista lo deduzca.

### 4.2 ⚠️ FUERA del camino de render, sin excepción

`PERF-02` documenta que la home llegó a hacer **~1.900 consultas por GET anónimo** y que hoy tiene
presupuesto aseverado. **Una llamada HTTP síncrona en el render es peor que aquello**: no son
milisegundos de BD local, es la latencia de un tercero **en el camino crítico de la portada**.

▶ **La landing lee de un almacén local y nunca llama a Google.** Quien llama es un **comando
programado** (`social-proof:refresh` (futuro)), y eso encaja con lo que ya existe: el repo tiene
scheduler con cinco tareas registradas.
❗❗ **PERO**: `DECISIONES #115` dice que **el scheduler NO corre en staging** —crontab instalado, sin
demonio cron— y sigue **pendiente del owner**. Una integración que depende del scheduler **hereda ese
bloqueo**: en staging habría que dispararla a mano. Hay que decirlo antes, no descubrirlo después.

### 4.3 Degradación: «no hay datos» es el caso NORMAL

Con Redis en `allkeys-lru` (§3.2), sin configurar, con la cuota agotada o con Google caído, el
resultado es el mismo y **no es un error**: la sección **no se pinta**. Es el patrón que el producto
ya usa —`heroStatus` vale `null` y la vista no pinta el chip; `#144` lo acaba de aplicar a las
métricas de zona— y el que impide que un tercero pueda tumbar la portada.

⚠️ **Lo que NO se hace**: pintar la sección con un esqueleto de carga, un «cargando reseñas» o unas
estrellas vacías. Un hueco que promete algo que no llega es peor que no estar.

### 4.4 Dónde viven las credenciales

| Dato | Dónde | Por qué |
|---|---|---|
| **API key** de Google | **`.env`**, nunca en BD | `SEC-11`: los secretos no son legibles ni editables desde el panel. ⚠️ El secret de Turnstile **sí** vive hoy en BD y es una **contradicción abierta** documentada en `INSTALACION-CLIENTE.md` §0 — no se repite aquí |
| **`place_id`** | Ajuste del panel (`social.google_place_id`) | Es la única cosa que la política **exime** de las restricciones de caché (R2), y cambia por instalación |
| El enlace a la ficha | Derivado del `place_id` | ⚠️ Pasa por `safeExternalUrl` como el resto (`SEC-07`) |

### 4.4.bis El respaldo del CMS: `testimonials` (futuro)

La tabla que la tanda B iba a construir de todos modos, con los campos **medidos del mockup**
(`{{ o.texto }}`, `{{ o.nombre }}`, `{{ o.meta }}` + cinco estrellas):

    testimonials (futuro)   text (json i18n) · author · meta · rating · position · is_active · timestamps

Sigue el patrón exacto de `faqs`/`park_rules` —campos i18n en JSON, `position`, `is_active`— y el
recurso del panel el de `FaqResource` (permiso `content.manage`, grupo «Contenido»).

⚠️ **Su ayuda en el panel NO puede decir «por si Google falla»**, porque sería falso: por §3.3 esto
es lo que ve **todo visitante que no acepta cookies de terceros**, cada día.

⚠️ **`rating` aquí es del testimonio, no la cifra agregada.** La cifra («4,8 sobre 5 · 320 reseñas»)
**solo** existe si viene de Google: inventarla a mano sería atribuir a Google un número que Google no
ha dado. Sin consentimiento o sin API, **la cabecera no se pinta** y las tarjetas del CMS salen solas.

### 4.5 La restricción de la que nadie se acuerda: R4 y el diseño de la tarjeta

**No se puede truncar una reseña.** El mockup tiene tarjetas de altura fija en una rejilla de tres.
Una reseña de Google puede tener 900 caracteres.

▶ Sin `line-clamp` (que recorta visualmente sin alterar el texto y **es aceptable**, porque el
contenido servido está completo), la rejilla se rompe. Con él, hay que dar acceso al texto completo
—el `googleMapsUri` de cada reseña— que **la política también recomienda**. Es un detalle de
maquetación que se convierte en requisito legal, y por eso está escrito aquí.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **PERF-02** (presupuesto de consultas de la home) | ⚠️⚠️ **El más expuesto.** Ninguna llamada externa en el render (§4.2). El presupuesto aseverado (`< 120`) es la red, y hay que **medirlo antes y después** |
| **SEC-07** (ninguna URL externa editable llega a un `href` sin `safeExternalUrl`) | ⚠️ El enlace a la ficha y el del perfil del autor **son URLs de un tercero** que van al DOM. Pasan por el mismo sink |
| **SEC-01** (`SecurityHeaders` cubre panel y API) | ⚠️ Ampliar `img-src` (§3.3) **relaja la CSP del sitio entero**, no solo de esa sección. Es una decisión, no un ajuste |
| **SEC-11** (secretos fuera del panel) | La API key va en `.env` (§4.4) |
| **RGPD-05** (consentimiento atómico de terceros) | ⚠️⚠️ Cargar avatares desde `googleusercontent.com` es **una petición del visitante a Google**. O entra en el consentimiento —y sin él se incumple R3— o se elimina el avatar eligiendo §3.1·C |
| **RGPD-02** (`audit_logs` sin PII) | El log del refresco registra **cuántas** reseñas llegaron y si hubo error, **nunca su texto ni su autor** |
| **RGPD-01** (`anonymize()`) | **Ninguno**: las reseñas son de terceros ajenos a nuestros titulares y no se persisten |
| **SUITE** | La suite **no puede llamar a Google**: la implementación se dobla, como ya se hace con Redsys en la auditoría de dinero |

---

## 6. Plan de verificación empírica

Sin esto no puede llegar a ✅ (`/dod` §3.bis).

1. **`SocialProofNeverHitsTheRenderPathTest`** (futuro) — **prohíbe el MECANISMO**: con el cliente
   HTTP falseado para **explotar si alguien lo llama**, `GET /` sigue dando 200 y pinta la sección.
   Si alguien mete una llamada en el render, el test cae. ⚠️ **Con su guarda-de-la-guarda**: un caso
   que compruebe que el fake SÍ explota cuando se le llama, o el test estaría verde sin mirar nada.
2. **Presupuesto de consultas de la home, medido ANTES y DESPUÉS** contra `HomePageTest`. El número
   no puede subir. Es la red de `PERF-02`.
3. **La CASCADA de §4.0, sus CINCO salidas** (futuro), y ninguna se da por buena sin ejercitarla:
   sin consentimiento · sin `place_id` · con la API caída (timeout) · con cuota agotada (429) · con
   clave inválida (403). **En las cinco: 200, y se sirven las del CMS.**
   ⚠️ **Y la sexta, que es la que se olvida**: sin consentimiento **Y** sin testimonios en el CMS →
   200 y **sección ausente**, sin hueco ni esqueleto.
   ⚠️ No vale probar el camino feliz y un error: las salidas tienen tratamiento distinto, y la de
   consentimiento es la que ocurre a diario.
3.bis **La atribución NO viaja cuando la fuente es el CMS** (futuro): un testimonio propio no puede
   salir con el avatar ni el enlace de Google. Es el defecto que un decorador mal escrito produce
   —hereda la vista de la otra fuente— y que la suite verde no vería.
4. **La atribución no se puede apagar** (futuro): si se sirve una reseña, el HTML lleva autor y
   enlace. Es un requisito legal, así que se asevera como tal y no como estilo.
5. **`VERIFY` manual en el SANDBOX de Google, una vez, con el `place_id` real**, y su salida pegada
   en la decisión. Igual que `redsys:verify-sandbox`: **una integración externa no se da por buena
   sin haber hablado con el tercero de verdad.**
6. **Medida de coste real**: llamadas/día × SKU, con el techo declarado y comprobado en la consola de
   Google tras 24 h. Un coste que escala con el tráfico **se mide, no se estima**.

---

## 7. Revisión y decisión

- **Medido por el agente** el 2026-08-25 contra la documentación oficial de Places API (estructura
  de `Review`, máximo de 5, política de caché y de atribución) y contra el código del repo (la CSP de
  `SecurityHeaders`, el consentimiento de `RGPD-05`, el presupuesto de `PERF-02` y Redis en `#137`).
- **Decidido por el owner** el 2026-08-25: **Google es la fuente de verdad, con respaldo propio**
  (§3.1). ⚠️ **Y una de sus dos opciones de respaldo se corrigió con la política delante**: el
  «snapshot de las de Google» está prohibido y no hay excepción de 30 días (§3.2). El respaldo son
  reseñas escritas a mano, que además es lo único que funciona sin consentimiento (§3.3).
- ✅ **Resuelto sobre la marcha, sin necesidad de decisión**: el avatar (§3.3). De las tres salidas
  posibles solo una cumple R3 y `RGPD-05` a la vez, así que no había nada que elegir — y de ahí sale
  el hallazgo que reencuadra el trabajo: **el respaldo se usa a diario, no en emergencias**.
- ⚠️ **Lo que el agente NO ha podido verificar, y hace falta ANTES de implementar**:
  1. el **`place_id`** del segundo cliente y si su ficha de Google está **verificada** — sin eso no
     hay nada que traer;
  2. una **clave de API** de Google Maps Platform con Places habilitado, para la verificación §6·5:
     una integración externa **no se da por buena sin haber hablado con el tercero de verdad**
     (misma regla que `redsys:verify-sandbox`);
  3. el **coste real** por 1.000 llamadas del SKU Enterprise + Atmosphere con la tarifa vigente, y el
     **techo de gasto** que el owner quiere poner en la consola de Google. Un coste que escala con el
     tráfico **se acota antes de encenderlo, no después de la primera factura**.
- ❗ **Bloqueo heredado que conviene no descubrir tarde** (§4.2): el refresco va por comando
  programado, y `DECISIONES #115` dice que **el scheduler no corre en staging**. Allí habrá que
  dispararlo a mano hasta que el owner active las tareas en el panel del hosting.
- ✅ **APROBADA por el owner el 2026-09-10**, y con la mitad `a` construida (`DECISIONES #490`): la
  sección, el contrato `Content\Contracts\SocialProof`, `CmsSocialProof`, la tabla `testimonials` y
  su recurso del panel. **La vista lee el contrato**, así que la `b` entra cambiando el binding.
- ⚠️ **Lo que sigue pendiente del owner, y es de la consola de Google**: el **tope de 50
  peticiones/día** (dijo que no había puesto ninguno), **añadir la IP del servidor** a la restricción
  de la clave —la que hay es la de su conexión, verificado midiendo nuestra IP de salida— y **rotar
  la clave**, que se pegó en un chat.
- **Entrada final**: `DECISIONES #490` (mitad `a`).
