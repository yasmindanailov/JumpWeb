# [SPEC] La instancia y la landing fuera — el producto expone HECHOS, el cliente pinta su web (F5 del programa)

> Estado: ✅ **APROBADA — el censo medido y las tres decisiones contestadas por el owner** (`#639`) ·
> Última actualización: 2026-09-19 · Decisión asociada: `DECISIONES #639`.
> Carril: **plataforma**. Origen: `specs/producto-e-instancias.md` §4.1→§4.4 y `#610`→`#616`; el principio del
> menú de hechos es `#631` y sus tres rasgos `#632`. Hermana: `specs/cajon-empaquetable.md` (F4, cerrada).

## §0 · Antes de tocar

- **Regla que ordena todo**: la landing SALE del producto. El producto se queda con el dominio, el panel, el
  cajón empaquetado y **una API pública de HECHOS**; quien diseña una landing usa lo que quiera de ese menú y
  **todo es opcional** (`#631`). Nada de presentación vuelve al producto.
- **Empieza por** §1 (el censo) → §4.1 (el menú) → §4.2 (lo que sale del panel) → §7 (lo del owner).
- **Trampas, antes de tocar**:
  - ⚠️⚠️ **`settings` mezcla el secreto de Redsys con el correo de contacto** (71 filas, medidas en §1.3):
    la API publica una **lista blanca por recurso**, nunca la tabla. Un volcado filtra `redsys_secret_key`.
  - ⚠️⚠️ **`contact.phone` y `theme.*` los leen los CORREOS** (§1.2): no pueden bajar al paquete de la
    instancia, porque ahí el CSS del cliente no llega. Mismo motivo que `#209` dio para el color de acción.
  - ⚠️ **Una URL que cambia es SEO perdido y no falla nada**: el sitemap se compara antes y después (11 URLs,
    capturadas en §1.5).
  - ⚠️ El criterio «cero marca del cliente en el código» **no se puede leer como un `grep` a secas**: 166 de
    sus 167 apariciones son citas de artboards en comentarios (§1.4).
  - ⚠️ **Dos formas de foto a propósito** (`#645`): el producto la SUBE a `uploads`, la zona guarda ruta a
    `public/`; las dos salen como URL absoluta por su `imageUrl()`.
- **Estado**: **MENÚ SERVIDO** (`#640`→`#646`, contrato 1.9.0): `site`, horario, normas, legales, precios,
  la ficha de producto y zona, y la cifra de prueba social —**sin reseñas: esperan a su fuente** (`#646`)—.
  ▶ Sigue la **T2**, el paquete de la instancia. **Un recurso público nuevo se escribe con su lista blanca o
  no se escribe**, y lo no rellenado no viaja — tampoco lo rellenado y BORRADO, que en BD es `''`.
- **Invariantes que toca**: `RGPD-05` (prueba social sin avatares), `PERF-02` (la lectura pública se cachea),
  `SEC-01` (rutas nuevas dentro del grupo `api`). Dinero y aforo: ninguno; esta fase no toca el embudo.

## 1. Contexto y problema — MEDIDO (2026-09-19)

Todo lo de esta sección se midió sobre el árbol en `ce7671d2`, con la BD de desarrollo.

### 1.1 Qué es hoy «la landing»

- `resources/views/home.blade.php` son **1.631 líneas** y las ocho páginas de `resources/views/pages/`
  otras **1.511**: 3.142 en total, sin contar componentes.
- **Ocho rutas sirven la portada**, y seis de ellas solo para tener algo detrás del cajón abierto:
  `/`, `/registro`, `/registro/google`, `/login`, `/recuperar-contrasena`, `/mi-cuenta`,
  `/mi-cuenta/pedidos`, `/entradas`.
- La portada consume, por nombre de variable: `site` (16 usos), la prueba social (`socialProof`,
  `socialRating`, 18), el horario (`schedule`, `scheduleLede`), `faqs`, las tarjetas de zona (`card`, 49), el
  mosaico de atracciones (`celda`, `rideMosaic`), las tarjetas de cumpleaños, los precios «desde»
  (`ratesFrom`, `partyFrom`) y las reseñas de Google.
- Un **composer global `View::composer('*')`** inyecta ese `site` en TODAS las vistas con **una sola lectura
  de la tabla `settings`** por petición. Es la pieza que hay que sustituir por el menú.

### 1.2 Quién lee qué (lo que decide dónde puede vivir cada dato)

- **Los CORREOS leen exactamente cuatro cosas** del negocio: `Setting::businessName()`, `theme.brand`,
  `theme.action` y **`contact.phone`**. Medido sobre `resources/views/vendor/mail/html/*` y `app/Mail`.
- **NINGÚN correo usa las redes sociales.** `contact.instagram`, `contact.tiktok` y
  `social.feed_embed_url` solo aparecen en el composer global, en la página de ajustes del panel y en
  `home.blade.php`. Es el censo que §4.3 de `producto-e-instancias.md` pedía para decidir si «redes» sale.

### 1.3 La tabla `settings`: 71 filas, y lo que hay dentro

| Qué son | Ejemplos | Cuántas |
|---|---|---|
| **Secretos** | `redsys_secret_key`, `auth.google_client_secret`, `redsys_merchant_code`, `redsys_terminal` | 5 |
| **Hechos públicos** (identidad, contacto, dirección, legal, tema, SEO) | `business.*`, `address.*`, `contact.*`, `legal.jurisdiction`, `theme.*`, `seo.og_image` | 22 |
| **Operación** (no son de la landing) | `maintenance.*`, `reservations.*`, `sales.*`, `payment.*`, `packs.*`, `puerta.*`, `mixed_party.*`, `waiver.mode` | 40 |
| **Copys de la landing que se va** | `landing.zones_access.es`, `bar.*`, `promo.*` | 4 |

▶ **El secreto y el correo de contacto están en la misma tabla**: por eso `#631` exigió lista blanca por
recurso. Un `settings` completo en la API es una filtración, no un menú.

### 1.4 «Cero marca del cliente en el código»: 167 apariciones, **una** viva

Medido separando comentario de código (se blanquean `/* */`, `//`, `#` y `{{-- --}}` antes de contar):

- **166 en comentarios**, y son la PROCEDENCIA DEL DISEÑO: `Pago y Desenlaces PJP`, `Landing PJP Modos`,
  `Precios PJP 10a`, `Correos PJP 1a`… Son las referencias al canvas del owner que explican por qué una pieza
  es como es. Borrarlas no quita marca: quita el porqué.
- **1 en código vivo**: `resources/js/site/salta.js` → `const CLAVE_RECORD = 'pjp-salta-record'`, la clave de
  `localStorage` del minijuego.

### 1.5 Lo que el visitante ve hoy (la línea base del criterio de salida)

- **Sitemap: 11 URLs** — `/`, `/aviso-legal`, `/condiciones`, `/contacto`, `/cookies`, `/cumpleanos`,
  `/normas`, `/precios`, `/privacidad`, `/servicios`, `/waiver`.
- La huella de maquetación (`scripts/huella-maquetacion.mjs`) cubre **34 pantallas** a 390 y 1280.

### 1.6 Lo que sale del panel, con su volumen real

| Recurso | Filas hoy | Qué es |
|---|---|---|
| `attractions` | 23 | presentación; arrastra el complemento por atracción (0 de 23 en uso) |
| `faqs` | 12 | presentación |
| `testimonials` | 3 | presentación; hoy resuelto como prueba social (`#616`) |
| `landing_services` | 3 | presentación |
| `bar_images` | 2 | presentación |
| `offers` | **0** | el widget flotante se retira (`#631`): «oferta» es un hecho de precio |

### 1.7 `zones`: 26 columnas, y cuatro que no lee nadie

| Campo | Quién lo lee hoy | Qué es |
|---|---|---|
| `slug`, `name` | el cajón (API) y la landing | **hecho** |
| `height_min_cm`, `height_max_cm` | `ZoneCards`, `RuleBoard` (normas) y la landing | **hecho** (regla de acceso) |
| `age_range`, `description` | `ZoneCards`, `RateTable`, `/atracciones` | **hecho opcional** (texto) |
| `color`, `color_secondary`, `accent`, `image` | solo la landing | **presentación** |
| `show_in_landing` | cuatro controladores públicos | interruptor de la landing |
| `opens_at`, `closes_at`, `ignores_venue_closure`, los tres de aforo | `OperatingSchedule` y el motor de aforo | **operación**: se quedan |
| **`subtitle`, `age_label`, `area_sqm`, `rides_count`** | **NADIE fuera del formulario del panel** | **muertos** |

## 2. Objetivo y criterios de éxito

1. **El visitante no nota nada.** Sitemap idéntico (las 11 URLs de §1.5) y la huella de maquetación de las 34
   pantallas sin diferencias entre el antes y el después de cada tanda.
2. **La landing de PlayJump vive en su repo** (`instancia-playjump`), se despliega con el producto y no
   contiene código del producto.
3. **El producto no sabe de ningún cliente**: la única aparición viva de §1.4 desaparece, y ningún dato de
   presentación de la instancia queda en `main`.
4. **La API pública sirve el menú de hechos** con lista blanca por recurso, caché pública y `ETag`, y
   **ningún recurso puede leer un ajuste fuera de su lista** (guarda nueva).
5. **El panel pierde los seis recursos de §1.6** y sus secciones de texto, y no pierde nada operativo.

**Fuera**: rediseñar la landing (es del owner y de su canvas); Business Profile (`#524`, su propia spec); la
app (F6); tocar el embudo, el aforo o el dinero.

## 3. Opciones consideradas

| | Elegida | Descartada, y por qué |
|---|---|---|
| Cómo sale la landing | **Vía B primero** (la instancia trae un paquete de vistas que el producto renderiza desde una ruta configurada) y vía A (estática contra la API) después | **Solo vía A ya**: exige los endpoints públicos terminados el primer día y deja al cliente sin web mientras tanto. Ya `[DECIDIDO]` en `#611`; aquí no se reabre |
| Los datos de la landing | **Hechos por la API**, presentación a mano en la instancia | **Landing que teclea precios y horario**: envejece sola — el defecto que `#488` cazó en las dudas |
| El menú | **Lista blanca por recurso** | **Volcado de `settings`**: filtra el secreto de Redsys (§1.3) |

## 4. Diseño elegido

### 4.1 El menú de hechos

**✅ T1 HECHA (2026-09-19) · el mecanismo y el primer plato** (`#640`). `GET /api/v1/site` sirve identidad,
dirección, contacto, redes, legal y SEO de la instalación, cacheado en público con `ETag` y sin idioma en la
URL —ninguno de sus campos se traduce—. Contrato **1.3.0** (añade, no cambia).

**Lo que hace que esto sea un menú y no un volcado**: `Platform\Services\PublicFacts`. Un recurso declara su
lista (`SiteFactsResource::AJUSTES`, 19 claves) y lee por ahí; pedir una clave no declarada **revienta**, no
devuelve `null`. Encima hay una segunda red: **un secreto no sale ni declarándolo** —se niegan por FAMILIA
(`*secret*`, `*key*`, `*token*`, `*password*`, `redsys_*`), no por nombre—, porque una lista blanca protege
del olvido y no del copiar y pegar, y el coste de ese error concreto es la clave con la que se firman cobros.

**Y dos cosas que decidió la respuesta, no el diseño**:
- ⚠️ **Lo que la instalación no ha rellenado NO viaja** (tampoco si es un espacio en blanco): sin TikTok no
  hay clave `tiktok`. Es el «todo es opcional» de `#631` en el JSON, y evita que cada landing repita la misma
  condición de cadena vacía. Cada bloque va con `(object)`: un `array` vacío de PHP sale como `[]` y el tipo
  de un campo no puede depender de si alguien rellenó el panel (la trampa ya pagada en F4 · T1).
- ⚠️⚠️ **`address` es dónde está el parque y `legal.fiscal_address` el domicilio fiscal.** El panel avisa de
  que «puede diferir», y en la instalación real son **municipios distintos**; la primera versión los sirvió
  juntos y una landing que pintara su contacto habría mandado a sus clientas a la gestoría.

**Medido**: `SiteFactsTest` (6) y `PublicFactsBoundaryTest` (7, con la respuesta entera revisada contra dos
secretos sembrados), `ApiContractTest` en verde con el esquema estricto, y `scripts/mutar-menu-de-hechos.sh`
**8/8**.

**✅ T2 HECHA (2026-09-19) · el horario, en DOS rutas** (`#641`). `GET /api/v1/schedule` (semana, temporadas y
fechas especiales; cinco minutos de caché) y `GET /api/v1/schedule/now` (si está abierto; **un minuto**).
Contrato **1.4.0**.

**Por qué dos y no una**: los hechos del calendario cambian cuando alguien toca el panel; el estado en vivo
cambia **dos veces al día**, y justo en ese minuto es cuando importa. Juntos obligaban a elegir entre
recalcular el calendario en cada visita o decir «abierto» cinco minutos después de cerrar. Es el mismo motivo
por el que `boot` y `session` son dos rutas en F4 · T1: *lo que no se cachea igual, no va junto*.

**Y el hecho se calcula UNA vez**: nace `Content\Services\OpeningState`, y `HeroStatus` —el chip del hero—
pasa a consumirlo en vez de resolverlo por su cuenta. Antes lo calculaban los dos; nada fallaba, y el día que
se hubieran separado —un festivo, un cierre a media tarde— no lo habría visto ningún test. Ahora hay uno que
compara los dos y no les deja discrepar.

⚠️⚠️ **`weekday` es 0 = domingo** (Carbon y `Date.getDay()`), no la ISO. Es el campo que más daño hace en
silencio: una landing que asuma lo otro pinta la semana entera desplazada y el JSON sigue siendo válido. El
contrato lo dice y un test lo clava **contra una fecha real**, no contra la documentación.

⚠️ Y la misma regla que en `/site`: `closes_at` viaja solo si está abierto, `opens_at` solo si está cerrado.
Un cartel que dice «cerramos a las 21:30» con el parque cerrado es peor que no decir nada.

**Medido**: `ScheduleFactsTest` (8) y los 13 casos de `scripts/mutar-menu-de-hechos.sh`, **13/13**.

**✅ T3 HECHA (2026-09-19) · las NORMAS** (`#642`). `GET /api/v1/rules?lang=` sirve las normas ACTIVAS, ya
ordenadas por el recorrido de una visita —`before`, `gate`, `inside`, y dentro de cada uno la posición del
panel—, con su porqué y con `updated_at`. Contrato **1.5.0**.

**Primer plato que se traduce**, y por eso el idioma va en la URL como en `/sidebar/boot`: una respuesta
cacheable tiene que ser función de su URL. **No se manda el mapa de idiomas**: la cadena «idioma → respaldo →
la primera que haya» es del dominio, y repartirla entre landings da tres respaldos distintos.

⚠️ **El orden es parte del dato** y costó una pasada: la primera versión ordenaba con `sortBy([cierre,
cierre])` y salía **al revés** —la visita contada de atrás adelante—, con el JSON válido y las diez normas
dentro. Lo vio una llamada real, no un test. Ahora es una clave compuesta y el caso comprueba por NOMBRE.

⚠️ **Una norma desactivada no vuelve por la API**: quien la retiró en el panel cree que ya no está.

▶ **Y se cerró un punto ciego del contrato**: `ApiContractTest` no bajaba a los `items` de una lista —un
esquema `type: array` no es `object` y salía por el primer `return`—, así que **un campo de más en cada
elemento pasaba el contrato entero**. Los tres esquemas nuevos del menú son listas de objetos y se habrían
colado. Con su control propio, porque una comprobación más permisiva no la caza ninguna mutación.

**Medido**: `RulesFactsTest` (8), `scripts/mutar-menu-de-hechos.sh` **17/17**.

▶ **T2b (`#653`, 20-09)**: `/rules` publica además **`summary`** —los nombres en el orden de la visita,
separados por ` · ` y cortados en palabra entera a lo que cabe en el fragmento de un buscador—: la
`<meta description>` de la página, ya escrita, por lo mismo que `/site` publica `address.written`. Sin normas
no viaja. Contrato **1.11.0**. La regla vive en `Platform\Services\MetaDescription` y la vista la usa.

**✅ T4 HECHA (2026-09-19) · los TEXTOS LEGALES** (`#643`). `GET /api/v1/legal/documents` (índice: título,
fecha y versión firmada si la hay) y `/legal/documents/{clave}?lang=` (el texto en secciones). Contrato
**1.6.0**.

⚠️⚠️ **El cuerpo viaja INTERPOLADO**, y esto es lo que justifica la tanda: el texto guardado lleva
`:legal_name`, `:legal_nif`, `:legal_address` y `:legal_email`, que el producto sustituye AL RENDERIZAR. En
crudo, una landing publicaría «El responsable del tratamiento de tus datos es :legal_name» en su política de
privacidad — el peor sitio posible para un marcador sin resolver.

⚠️⚠️ **Viaja la PÁGINA, no el documento firmado, y son dos cosas distintas.** Medido: para `condiciones` y
`waiver` existe además una versión publicada que es **la página interpolada y congelada** al publicarla, y
que es a lo que la gente se obliga. En la instalación de referencia el justificante tiene **once** secciones
como página y **una** como versión. Servir la versión quitaría diez secciones de explicación; servir la
página como si fuera lo firmado publicaría texto que nadie firmó. Así que viaja la página y la versión va al
lado como dato (`signed_version`); el texto firmable sigue en `/legal/waiver`.

⚠️ **No cuelgan de `/legal/{clave}`**: `/legal/waiver` ya existe y dice otra cosa —el régimen del
justificante—, y una ruta genérica ahí la habría ensombrecido.

▶ Y una corrección que trajo Larastan: `published_at` es `NOT NULL`, así que el contrato lo **exige**. El
primer borrador lo declaró opcional «por si acaso», y un «por si acaso» contra el esquema es una mentira que
cada cliente tiene que programar.

**Medido**: `LegalDocumentsTest` (8), `scripts/mutar-menu-de-hechos.sh` **21/21**.

▶ **T2b (`#653`, 20-09)**: el documento con cuerpo publica además **`summary`** —el primer párrafo, ya
interpolado, sin etiquetas y acotado como `Rules.summary`—; el índice no, porque sale de las secciones y el
índice no las lleva. Sin ningún párrafo no viaja.

**✅ T5 HECHA (2026-09-19) · los PRECIOS por tarifa** (`#644`). `GET /api/v1/prices?lang=` da, por producto,
lo que cuesta en cada tarifa, con el rótulo que escribe el panel («Lunes a jueves», «Viernes, fines de
semana, vísperas y festivos»). Contrato **1.7.0**.

⚠️ **No duplica `/catalog/products`, aunque lo parezca.** Aquél publica el **«desde»** para abrir la ficha en
el embudo; esto es el desglose por tarifa, que no vivía en ningún sitio público. Un hecho, un sitio: el
«desde» no se repite aquí, y hay un caso que lo vigila —si algún día apareciera, habría dos fuentes del
mismo número y divergirían, porque el catálogo lo calcula con los tramos de volumen (`#324`) y esto no—.

⚠️⚠️ **El «precio de antes» de la promo NO entra en el contrato.** `#628` es una chapuza declarada con fecha
de caducidad; meterla obligaría a romper el contrato después para desmontarla. Lo traerá el mecanismo de
ofertas que `#631` decidió («oferta» = hecho de precio), con su forma pensada.

⚠️ **Una tarifa en la que un producto no se vende se CALLA**, no viaja con `0`: un cero es un precio, y una
landing pintaría «gratis los festivos».

▶ Y dos cosas que enseñó la tabla al escribir el test: `prices` es **polimórfica** y su `priceable_type` usa
el **alias del morphMap** (`ticket_type`, no el FQCN) —con la clase entera la fila se escribe y el producto
sale sin precios—; y lleva **columna `currency`**, así que la moneda sale del dato y no de una constante
escrita a mano.

**Medido**: `PricesFactsTest` (6), `scripts/mutar-menu-de-hechos.sh` **24/24**.

**✅ T6 HECHA (2026-09-19) · la FICHA de producto y de zona** (`#645`, de `#632` P1). `GET /catalog/zones`
pasa a servir descripción y foto de cada zona, y el producto estrena foto: en la lista
(`GET /catalog/products`) y, con su descripción, en el detalle. Contrato **1.8.0**.

⚠️⚠️ **La foto del producto se SUBE al hueco de la instalación, y ésa es la decisión del owner.** Va al disco
`uploads` (`public/uploads`), que está gitignorado y excluido del `rsync --delete` del despliegue: la clienta
cambia su foto desde el panel sin commitear al repo del producto. Es literalmente lo que `#632` quería
evitar, y lo que se estaba haciendo mal — medido: **35 imágenes del cliente versionadas en `main`**
(`public/images/attractions/`), a las que apuntan las 3 zonas con foto. **La zona NO se convierte hoy**:
mover esos ficheros es tocar material del cliente y va con la tanda en la que la landing se va. Las dos
formas conviven sin que el contrato se entere, porque `Zone::imageUrl()` y `TicketType::imageUrl()` las dos
sacan **URL absoluta** — el día que la zona se mude no cambia ni un byte de lo publicado.

⚠️⚠️ **El reparto es asimétrico, y está medido.** La FOTO viaja en la lista y la DESCRIPCIÓN solo en el
detalle: un catálogo se recorre mirando fotos —la app de F6 no tiene landing y pinta tarjetas con esto— y la
prosa se lee al abrir. Las cifras del 19-09: `/catalog/products` son **4.079 bytes** con 24 productos (9 con
zona); una descripción son ~340 bytes y una URL ~60. Es la regla que `CatalogProductDetail` ya había escrito
para `guardian_authorization`: cada campo de la lista se paga en todas las filas.

⚠️ **Y por eso la ficha de zona es un DTO aparte** (`CatalogZoneDetail`, que COMPONE `CatalogZone`) en vez de
engordar la zona anidada en cada producto: eso habría repetido las descripciones de 4 zonas a lo largo de 9
productos, ~47 % más de payload en la primera pantalla del flujo, para un dato que el cajón no pinta. Medido
después del cambio: la lista sigue en **4.079 bytes exactos**, y `/catalog/zones` pasa de 210 a 689.

▶ Tres cosas que costaron una pasada cada una: `ticket_types` **no tenía columna de imagen** (la zona sí, y
también `description`) · `FileUpload` **descarta al hidratar el fichero que no existe en el disco**, así que
una prueba del campo del panel necesita `Storage::fake` con el fichero puesto o el campo sale vacío · y el
arnés cazó que «rellenado y BORRADO» (`''` en el JSON de traducciones) no estaba cubierto: sin ese caso,
cambiar el `?:` del lector por un `??` publicaría `"description": ""` con todo en verde.

**Medido**: `CatalogTest` (+4), `CatalogEditTest` (+1, el campo del panel), guarda nueva de divergencia en
`ApiContractTest`, `scripts/mutar-menu-de-hechos.sh` **34/34**.

**✅ T7 HECHA (2026-09-19) · la CIFRA de prueba social, y el menú queda servido** (`#646`, de `#616`).
`GET /api/v1/social-proof` publica media, recuento, fuente y enlace a la ficha. Contrato **1.9.0**.

⚠️⚠️ **Las RESEÑAS no se publican todavía, y es una decisión, no un olvido.** `#616` las quería aquí «con
autor, enlace al perfil, marca de traducción y fuente, sin avatares», pero la fuente está a mitad de cambio:
`specs/google-business-profile.md` —aprobada, código no empezado— **sustituye a Places** y dice que el
contrato `SocialProof` CAMBIA (§4.3·9): la reseña gana la respuesta del parque, las fotos y la marca de
anónimo, y aparecen el enlace a la ficha, «Escribir una reseña» y **la línea del filtro**, que no es
estética —la ley Ómnibus 2019/2161 considera engañoso enseñar solo las buenas sin decirlo—. Publicarlas hoy
sería repartir contenido de Places a otro repo y atarse a una forma que ya sabemos que cambia, y este repo
no rompe un contrato público para desmontar algo (`#644`). ▶ **Llegan en la T2 de esa spec, DENTRO de este
mismo sobre**, como clave hermana de `rating`: por eso la cifra viaja envuelta y no suelta en la raíz, y por
eso la ruta pide el contrato `SocialProof` y nunca `GoogleSocialProof` —el día que se cambie el binding,
esta ruta no se toca—.

⚠️ **La cifra sí puede ir sola, y el repo ya lo tenía argumentado**: la trae nuestro servidor, no lleva autor
ni foto y una media de un negocio no es dato personal, así que no pide consentimiento (es lo que
`FallingBackSocialProof` razona al separarla de las opiniones). De ahí que sea pública y cacheable, y **la
única ruta del menú sin `?lang=`**: una media, un recuento y una URL son los mismos en los tres idiomas.

⚠️ **Sin cifra sostenible el sobre va VACÍO**, nunca con un `0` que una landing pintaría como «0,0 sobre 5».
▶ Y la trampa que costó una pasada: ese vacío salía **`[]` y no `{}`**. Es la trampa de la receta —un array
vacío de PHP sale como lista— pero en la RAÍZ, donde el `(object)` por bloque de los hermanos no llega:
`JsonResource::resolve()` hace `(array)` sobre lo que devuelva `toArray()`, así que el tipo solo se puede
fijar en la entrega. Un caso escrito con `json()` no lo habría visto: hay que mirar el cuerpo crudo.

▶ **Dos obligaciones de quien consuma la cifra**, escritas en el contrato: acreditar la fuente donde se
enseñe, y **no** emitirla como `aggregateRating` de JSON-LD —son opiniones de un tercero sobre el negocio,
no una valoración que el negocio declare—.

**Medido**: `SocialProofFactsTest` (4), `scripts/mutar-menu-de-hechos.sh` **38/38**.

### 4.1.bis · La RECETA de un plato nuevo

Siete tandas destilan esto. Vale para **cualquier** recurso público que se añada después, dentro de F5 o
fuera de ella, y está aquí —y no en un fichero de carril— porque una receta no caduca con la tanda:

- La **lista blanca se declara EN el recurso** y se lee por `PublicFacts`; `Setting::` a pelo lo prohíbe
  `PublicFactsBoundaryTest`, que barre los 37 recursos de la API **por carpeta, no por nombre**.
- **Lo que la instalación no rellenó NO viaja**, ni como `""` —y «rellenado y BORRADO» es `''`, que cuenta
  como no rellenado—. Cada objeto se emite con `(object)`: un array vacío de PHP sale `[]`, y el tipo de la
  respuesta no puede depender de si alguien rellenó el panel. ⚠️ En la RAÍZ eso no basta: `resolve()` hace
  `(array)` de lo que devuelva `toArray()`, así que el tipo del sobre vacío se fija en la ENTREGA (`#646`).
- **Se traduce → `?lang=` obligatorio**, y el respaldo lo resuelve el servidor, nunca el mapa de idiomas.
  Lo que no se traduce **no lo lleva** (la cifra de prueba social es el caso).
- **Se cachea según CAMBIE**: lo que toca el panel, 5 min; lo que cambia solo (el «abierto ahora»), 1 min.
- **Lo apagado en el panel no se sirve**: una norma, una página o un producto desactivados están retirados.
- **Contrato OpenAPI en el mismo commit** (sube el MENOR) + caso en `ApiContractTest` + su mutación en el
  arnés. ⚠️ `ApiContractTest` exige `required` en TODO campo: lo opcional se declara en
  `OPTIONAL_BY_DESIGN` **con su porqué**, y la comprobación baja también a los `items` de las listas.

### 4.1.ter · Las TRAMPAS que costaron una pasada cada una

Están aquí y no en un fichero de carril por el mismo motivo que la receta: no caducan con la tanda, y quien
añada un recurso público dentro de cinco meses las necesita igual.

- **`weekday` es `0 = domingo`**, no ISO. Un día desplazado no falla: publica la semana entera corrida.
- **Ordenar con `sortBy([cierre, cierre])` sale AL REVÉS.** Úsese una clave compuesta (`'%d-%05d'`), que
  ordena igual en cualquier versión y se lee de un vistazo.
- **El cuerpo de un legal lleva marcadores** (`:legal_name`, `:legal_nif`…) y hay que **interpolarlo**: en
  crudo, una landing publicaría «El responsable del tratamiento es :legal_name» en su política de privacidad.
- **`prices` es polimórfica y su `priceable_type` es el ALIAS del morphMap** (`ticket_type`, no el FQCN):
  con la clase entera la fila se escribe y el producto sale sin precios.
- **`prices` tiene columna `currency`**, así que la moneda sale del dato y no de una constante a mano.
- ⚠️⚠️ **Un objeto que puede salir VACÍO se fija en la ENTREGA**, no con `(object)` por bloque:
  `JsonResource::resolve()` hace `(array)` de lo que devuelva `toArray()`, así que la raíz vacía sale `[]` y
  no `{}`. Y un caso escrito con `json()` **no lo ve**: hay que mirar el cuerpo crudo.
- ⚠️⚠️ **«Rellenado y BORRADO» no es `null`, es `''`** (el panel deja la cadena vacía dentro del JSON de
  traducciones). El arnés cazó que sin ese caso, cambiar un `?:` por un `??` publica `""` con todo en verde.
- **`FileUpload` descarta al hidratar el fichero que no existe en el disco**, así que probar un campo de
  subida pide `Storage::fake` con el fichero puesto — o el campo sale vacío y la prueba no prueba nada.

▶ **El MENÚ DE HECHOS queda servido.** Lo que sigue en F5 es la **T2**: el paquete de la instancia.

Una familia de rutas públicas bajo `/api/v1` (grupo `api`, `SEC-01`), cacheables y con `ETag` (`PERF-02`),
cada una con su **lista blanca declarada en el propio recurso**. El censo dice qué hay que servir para que la
landing de hoy se pueda pintar sin el producto: identidad y contacto (`site`), horario con estado en vivo y
festivos, normas, documentos legales por clave, precios «desde», la ficha de producto y de zona (título,
descripción, imagen) y la prueba social **sin avatares** (`RGPD-05`).

⚠️ **Todo es opcional para quien pinta la landing** (`#631`): el menú no impone estructura, y el cajón no
depende de que la landing lea nada.

### 4.2 Lo que sale del panel, y lo que NO

Salen los seis recursos de §1.6 y las secciones de texto de la landing y del bar. Se quedan los trece
operativos (`producto-e-instancias.md` §4.3), y **`ParkRules` y `Pages`**, porque normas y los cinco
documentos legales los consume la landing POR LA API: son hechos, no presentación.

### 4.3 `zones` adelgaza

Los cuatro campos muertos de §1.7 se retiran con su columna. La presentación (`color`, `accent`, `image`)
sigue el mismo camino que la landing; los hechos (alturas, edades, descripción) salen por el menú.

### 4.4 Las redes

Medido: ningún correo las usa (§1.2), así que **pueden salir del panel**. Dónde acaban es decisión de
producto y está en §7·D1.

### 4.5 El criterio «cero marca», leído como se puede cumplir

Se acota a **código vivo**: literales que viajan al navegador, a la BD o a un correo. Las citas de artboard
en comentarios se conservan —son la trazabilidad del diseño, y el repo las exige por convención—. La medición
de §1.4 es el instrumento, y queda como guarda.

### 4.6 El orden de las tandas (propuesto)

1. **T1 ✅** · el mecanismo (`PublicFacts`) y el primer plato (`/site`); el resto del menú, recurso a recurso.
2. **T2 · el paquete de la instancia**: repo desde plantilla ✅ (T2a, `#647`), la landing actual mudada tal
   cual (vía B) vista a vista —✅ las OCHO de `pages/`, que ya no existe (`#654`→`#660`); queda `home`—
   y el sitemap comparado.
2.bis. **T2c ✅ · el CSS** (`#665` la abrió, `#667` la cierra). Nació para mudar **208 clases sin
   consumidor** al paquete; la medida con el anfitrión real dijo otra cosa y la tanda cambió de forma. El
   detalle, en **§4.6.bis**. ⚠️ El mecanismo de «reglas por instancia» **no se construye**: con 17 clases no
   lo justifica, y la vía A —el destino (§3)— se las lleva con sus páginas.
3. **T3 · el panel adelgaza**: los seis recursos y las secciones de texto.
4. **T4 · `zones` adelgaza** y cae la última marca viva.
5. **T5 · v2.0.0**: el contrato de instancia cambia, así que la versión sube de MAYOR.

### 4.6.bis · La T2c, medida: 208 huérfanas eran 17 (`#667`, 2026-09-21)

**La premisa de `#665` había caducado, y por lo mismo que la de `#664`**: aquellas 208 clases se midieron
apuntando el controlador a un anfitrión de **nueve líneas**. El anfitrión real de `#666` **sostiene 174 de
ellas** —y la instancia pinta esas mismas 174 en su portada—, así que no eran deuda: eran mecanismo
compartido. Medido sobre las **1.540** clases de `landing.css` + `site.css`:

| | Clases |
|---|---|
| Las pinta **solo el producto** | 891 |
| Las pintan **los dos** | 471 |
| Las pinta **solo la instancia** | **17** → declaradas en `MATERIAL_CONSUMIDO_POR_LA_INSTANCIA` |
| No las pinta **nadie** | **128** → 119 podadas, 9 aparcadas por el owner |

❗❗❗ **UN CENSO DE CSS HUÉRFANO QUE BUSCA EL NOMBRE LITERAL MIENTE, Y AQUÍ MINTIÓ TRES VECES** (161 → 139
→ 128). En esta casa una clase se compone de **tres formas**, y cada una costó una pasada:
1. **concatenación en JS/Vue** — `:class="'orders__status--' + row.status"` (toda la familia `.orders`);
2. **concatenación en PHP** — `'jj-spinner--'.$size`;
3. **interpolación Blade DENTRO del atributo** — `class="visit__dot--{{ $heroStatus['face'] }}"`.
▶ **La tercera la destapó un control contra el DOM servido**, no el escaneo: de las «muertas», seis
aparecían en el HTML real. Y enseñó algo más — el control veía `visit__dot--open` y no `visit__dot--later`
**porque a esa hora el parque estaba cerrado**. *Una medida que solo ve la rama de hoy no ve las otras*, así
que las familias con modificador compuesto se quedan ENTERAS.

▶ **La poda, con dos controles antes de aplicarla.** El primero —ninguna clase viva puede desaparecer—
salió ROJO con tres, y al abrirlo apareció la causa: **12 comentarios de esas hojas llevan llaves dentro**
(citan CSS en la prosa) y desincronizaban el parser. Es la trampa que `ReadsSiteStylesheets` ya tenía
escrita: *los comentarios se BLANQUEAN conservando longitud, no se borran*. Reescrita así, el control salió
verde (0 vivas perdidas, llaves equilibradas) y el resultado fue **−9,9 KB en `landing.css`, −8,0 en
`site.css` y −2,4 en `cajon.css`** —que es generada y se rehace—, con **huella 0 diferencias en 38
pantallas**.

❗❗ **Y LA PODA DESPERTÓ DOS GUARDAS QUE VIGILABAN REGLAS SIN SUJETO.** Ninguna se rompió: las dos llevaban
tiempo mirando CSS que ya no pintaba nada.
- `TagSystemTest` devolvió `.tag--tinta` en el mismo minuto: es una **variante del SISTEMA** de etiquetas,
  declarada junto a las otras tres tenga o no consumidor. *Un sistema declara sus variantes antes de que
  existan sus consumidores; eso no es una regla muerta.* Vuelve a la hoja y entra en la deuda declarada.
- `SemanticFillTextTest` exigía **cinco** importes «pendientes» y dos eran marcado del cajón **Livewire**
  que la Fase 4 retiró. Se recortó a tres **tras comprobar que esos tres los pinta el cajón de hoy**: la
  regla —lo pendiente va en tinta, el color es para lo ya cobrado— conserva sus sujetos vivos.

▶ **El TRINQUETE** (`LandingCssHasNoOrphansTest`) es lo que queda encendido: mira **todo** el CSS de la
landing —la guarda que había solo veía las familias de FACHADA, que es exactamente lo que `#665` llamó «la
deuda no es la lista, es lo que la lista no mira»— con una **deuda declarada de 15 entradas, cada una con
su motivo, que solo puede ENCOGER**. Sus tres casos se vieron matar a su mutante, y uno de ellos vigila lo
que suele pudrir una línea base: que no queden en ella entradas **ya resueltas**.
⚠️ Usa el trait compartido `ReadsSiteStylesheets` en vez de releer las hojas: *cuando algo está en dos
sitios, la salida no es retirarlo de uno, es que haya UNA definición* — y así hereda sus tres trampas ya
pagadas (`client.css` fuera por ser de una instalación, `cajon.css` fuera por ser copia generada).

▶ **Lo que NO se hizo, y con su motivo**: el mecanismo de «hoja de reglas por instancia». Con 17 clases no
se justifica una hoja más en el `<head>` de todas las páginas, y **profundizaría en la vía B** cuando §3
dice que la vía A es el destino. Esas 17 siguen en el producto, declaradas, hasta que la vía A se lleve las
hojas con sus páginas.

## 5. Impacto en invariantes

`RGPD-05`: la prueba social viaja sin avatares. `PERF-02`: el menú es cacheable y anónimo. `SEC-01`: todo bajo
el grupo `api`. **Guarda NUEVA**: ningún recurso público lee un ajuste fuera de su lista blanca. `AFORO-*` y
`PAY-*`: ninguno — esta fase no toca el embudo.

## 6. Plan de verificación empírica

- **Sitemap antes y después**, idéntico (11 URLs; la línea base está en §1.5).
- **Huella de maquetación** de las 34 pantallas sin diferencias en cada tanda.
- **Las siete páginas públicas en 200** tras cada tanda.
- **Mutación** de la lista blanca: pedirle a un recurso un ajuste que no es suyo tiene que poner rojo la guarda.
- **El grep de §1.4** en 0 apariciones vivas al terminar la T4.

## 7. Revisión y decisión

**2026-09-19 · las tres contestadas por el owner, las tres por la recomendada** (`#639`), preguntadas con el
censo de §1 delante:

- **D1 · Las redes sociales → SE QUEDAN EN EL PANEL** y viajan como hecho en `site.social`. Medido que ningún
  correo las usa, pero el criterio que mandó fue otro: cambiar un Instagram tiene que ser editar un campo, no
  desplegar el repo de la instancia. *Descartado*: bajarlas al paquete de la instancia.
- **D2 · Los cuatro campos muertos de `zones` → SE RETIRAN CON SU COLUMNA.** Un campo que el panel pide y que
  no sale a ningún sitio se vuelve a rellenar creyendo que sirve. Es migración, así que confirma el MAYOR de
  la v2.0.0. *Descartado*: dejarlos por si la landing nueva los quisiera.
- **D3 · «Cero marca del cliente en el código» → SE LEE COMO CÓDIGO VIVO** (§4.5): lo que viaja al navegador,
  a la BD o a un correo. Queda una aparición —`pjp-salta-record`— y cae en la T4. *Descartado*: aplicarlo al
  pie de la letra, que habría borrado 166 comentarios con la trazabilidad del diseño sin quitar una marca.

▶ **Con `#639` se agota la banda 610–639**; el carril sigue en **640–669** (`docs/DECISIONES.md`).
▶ **Lo siguiente es la T1** de §4.6: el menú de hechos, recurso a recurso, con su lista blanca y su guarda.

## Anexo · fila del enrutador

`| Instancia · la landing fuera · el menú de hechos · API pública de lectura | docs/specs/instancia-y-landing-fuera.md §0 |`
