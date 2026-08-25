# [SPEC] Reseñas de Google en la landing — la prueba social que el parque NO controla

> Estado: **⬜ borrador — para revisión del owner** · Última actualización: 2026-08-25 ·
> Decisión asociada: `DECISIONES «#N»` al aprobarse.
>
> ⚠️⚠️ **Empieza por §1.3.** Esta integración tiene **cinco restricciones DURAS** que no son
> negociables con Google y que cambian el diseño, no lo decoran. Tres de ellas chocan de frente con
> invariantes que este repo ya tiene endurecidos (`SEC-07`, `RGPD-05`, `PERF-02`). Diseñar sin
> tenerlas delante produce una integración que funciona en local y es insostenible en producción.
>
> ⚠️ **Nace de un encargo del owner** (2026-08-25), al dimensionar la tanda B de
> `specs/landing-white-label.md`: los testimonios del segundo cliente **no se teclean, se traen de
> Google**.

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

## 3. ❗ Las TRES decisiones que necesita el owner, y no puede tomar el agente

Van primero porque **el diseño de §4 cambia entero según se respondan**.

### 3.1 `[DECISION-PENDIENTE]` ¿Google es la ÚNICA fuente, o el parque conserva voz?

| | Qué implica |
|---|---|
| **A · Solo Google** | Lo que §1.4 describe, con todas sus consecuencias. Cero mantenimiento editorial |
| **B · Solo CMS** | El parque elige y ordena. Cero coste, cero dependencia. Pierde la autoridad de «en Google» |
| **C · Google para la CIFRA, CMS para las tarjetas** | La cabecera «4,8 · 320 reseñas» sale de Google (barato: campos `rating`+`userRatingCount`, **SKU más económico que `reviews`**) y las tarjetas las elige el parque. ⚠️ **Riesgo declarado**: un visitante puede leer las citas y no encontrarlas en Google |
| **D · Las dos, separadas y rotuladas** | «Destacadas por el parque» + «Últimas en Google». Honesto y completo; **es el doble de superficie** y hay que decidir qué manda |

▶ **Recomendación del agente: C.** Da la autoridad de la cifra (que es lo que el mockup enseña
grande) por el SKU barato, conserva el control editorial de la portada, y deja la puerta abierta a
traer las 5 de Google más adelante sin rehacer nada. Es además lo único que **no** depende de
resolver §3.2.

### 3.2 `[DECISION-PENDIENTE]` ⚠️ La caché, que es el nudo legal y económico

R2 prohíbe cachear «más allá de las excepciones permitidas», y la sección de política **no documenta
ninguna excepción temporal para `reviews`**. Eso deja tres caminos, y **ninguno es gratis**:

| | Qué pasa |
|---|---|
| **Sin caché** | Cumple la letra. **Cada visita a la home es una llamada al SKU más caro.** El coste escala con el tráfico y un pico de visitas es un pico de factura |
| **Caché corta (p. ej. 15 min) en Redis** | Es lo que hace todo el mundo, y `#137` ya dejó Redis como requisito duro **solo para caché**. ⚠️ Pero es una interpretación de la política, no una autorización escrita: **exige leer los Service Specific Terms y decidirlo con conocimiento** |
| **Snapshot diario servido desde nuestro almacenamiento** | El más barato y el más rápido. **Es exactamente lo que R2 prohíbe.** Se descarta salvo que Google lo autorice por escrito |

⚠️⚠️ **Y hay una trampa medida en este repo que aplica aquí de lleno** (`DECISIONES #137`): Redis
está con **`allkeys-lru`**, así que **puede EVICTAR una clave antes de su TTL**. Fue el motivo por el
que el pase de Redsys se sacó de la caché. Traducido: **la caché de reseñas se puede evaporar en
cualquier momento**, y el diseño tiene que tratar «no está» como el caso normal, no como el fallo.

▶ **Lo que el agente NO puede resolver y necesita del owner**: si se acepta la caché corta, hace
falta la lectura de los *Google Maps Platform Service Specific Terms* y, si hay duda, confirmación de
Google. **Esto es cumplimiento, no ingeniería.**

### 3.3 `[DECISION-PENDIENTE]` El avatar del autor: CSP y consentimiento

R3 exige mostrar la foto del autor, que vive en `lh3.googleusercontent.com`. Medido en el código:
la directiva `img-src` de `Http\Middleware\SecurityHeaders::contentSecurityPolicy()` vale hoy

    img-src 'self' data:      ← la bloquea

Y cargarla es **una petición del visitante a Google**, que es exactamente lo que `RGPD-05` gestiona
hoy con la categoría «mapa» (el iframe nace con `data-src` hasta que se consiente).

| | Qué implica |
|---|---|
| **Ampliar `img-src`** a `https://*.googleusercontent.com` | Cumple R3. ⚠️ Abre la CSP a un comodín de subdominio de Google, y **sigue siendo una petición a un tercero** → hay que decidir si entra en el consentimiento |
| **Bloquear hasta consentir**, como el mapa | Coherente con `RGPD-05` y con la política de cookies ya publicada. Sin consentimiento, la sección se ve **sin avatares** — que **incumple R3** |
| **Proxear las fotos** por nuestro servidor | Resuelve CSP y consentimiento de una vez. ⚠️ **Es «store» de contenido de Places (R2)** y además nos pone a servir imágenes de terceros |

▶ **Recomendación del agente**: si se elige la opción **C** de §3.1, **este problema desaparece**: la
cifra agregada no lleva autor ni foto, así que no hay R3 que cumplir ni tercero que cargar. Es la
razón más fuerte a favor de C.

---

## 4. Diseño elegido — **pendiente de §3**

> ⚠️ Este apartado se completa **cuando el owner responda §3.1–§3.3**. Lo que sigue es lo que **no
> cambia** con ninguna de las respuestas, y por tanto ya se puede fijar.

### 4.1 La forma, sea cual sea la fuente

**Un contrato de dominio, no una llamada suelta.** Es lo que ha ordenado todas las integraciones de
este repo (`Booking\Contracts\*`, `Payments\Contracts\*`) y lo que hace que la landing no sepa de
dónde vienen los datos:

    Content\Contracts\SocialProof            (futuro)  ← lo que la landing pide
      ├─ rating(): ?Rating                             la cifra agregada (valor, recuento, enlace)
      └─ testimonials(): Collection                    las tarjetas, vengan de donde vengan

    Content\Services\GoogleSocialProof        (futuro)  ← implementación Places
    Content\Services\CmsSocialProof           (futuro)  ← implementación CMS
    Content\Services\NullSocialProof          (futuro)  ← sin configurar: todo vacío, cero llamadas

▶ **Por qué así y no un `GoogleReviewsService` a secas**: `#136` fijó que la landing consume DATOS,
no proveedores. Con el contrato, cambiar de fuente —o combinarlas, §3.1·D— es una línea del
contenedor y **no toca ni una vista**. Y `ModuleContractsTest` ya vigila que un contrato tenga
consumidor real.

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
3. **Degradación, los CUATRO caminos** (futuro): sin `place_id` · con la API caída (timeout) · con
   error de cuota (429) · con la clave inválida (403). **En los cuatro: 200 y sección ausente.**
   ⚠️ No vale probar solo el camino feliz y uno de error: los cuatro tienen tratamiento distinto y
   el de cuota es el que va a pasar de verdad.
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
- ⚠️ **Lo que el agente NO ha podido verificar y hace falta**:
  1. si los *Service Specific Terms* permiten la caché corta de §3.2 — **es cumplimiento, no
     ingeniería**;
  2. el coste real por 1.000 llamadas del SKU Enterprise + Atmosphere, que cambia con la tarifa
     vigente y el volumen;
  3. si el segundo cliente tiene **ficha de Google verificada** y su `place_id`.
- **Pendiente del owner**: las tres decisiones de §3.
- **Entrada final**: `DECISIONES «#N»`.
