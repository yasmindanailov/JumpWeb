# [SPEC] La instancia y la landing fuera — el producto expone HECHOS, el cliente pinta su web (F5 del programa)

> Estado: ✅ **APROBADA — el censo medido y las tres decisiones contestadas por el owner** (`#639`) ·
> Última actualización: 2026-09-19 · Decisión asociada: `DECISIONES #639`.
> Carril: **plataforma**. Origen: `specs/producto-e-instancias.md` §4.1→§4.4 y `#610`→`#616`; el principio del
> menú de hechos es `#631` y sus tres rasgos `#632`. Hermana: `specs/cajon-empaquetable.md` (F4, cerrada).

## §0 · Antes de tocar

- **Regla que ordena todo**: la landing SALE del producto. El producto se queda con el dominio, el panel, el
  cajón empaquetado y **una API pública de HECHOS**; quien diseña una landing usa lo que quiera de ese menú y
  **todo es opcional** (`#631`). Nada de presentación vuelve al producto.
- **Empieza por** §1 (censo) → §4.1 (el menú y su receta) → §4.2 (lo que sale del panel).
- **Trampas, antes de tocar**:
  - ⚠️⚠️ **`settings` mezcla el secreto de Redsys con el correo de contacto** (71 filas, medidas en §1.3):
    la API publica una **lista blanca por recurso**, nunca la tabla. Un volcado filtra `redsys_secret_key`.
  - ⚠️⚠️ **`contact.phone` y `theme.*` los leen los CORREOS** (§1.2): no pueden bajar al paquete de la
    instancia, porque ahí el CSS del cliente no llega. Mismo motivo que `#209` dio para el color de acción.
  - ⚠️ **Una URL que cambia es SEO perdido y no falla nada**: el sitemap se compara antes y después (§1.5).
  - ⚠️ **Dos formas de foto a propósito** (`#645`): el producto la SUBE a `uploads`, la zona guarda ruta a
    `public/`; las dos salen como URL absoluta por su `imageUrl()`.
- **Estado**: **MENÚ SERVIDO** + **T2a→T2c, T3·1 y T4 hechas**; en marcha la **vía A**, y de sus cuatro
  platos **LOS CUATRO SERVIDOS ✅** (`#671`→`#674`, contrato **1.15.0**): dudas, servicios, bar y juegos.
  ▶ Queda un quinto que no estaba en la lista: los **tramos de grupo**, que son dinero (§4.1 y §4.6).
  ▶ **Un recurso nuevo se escribe con su lista blanca o no se escribe**; lo no rellenado no viaja, ni lo
  BORRADO (`''` en BD). ❗ Y el filtro de «vacío» va **después** del respaldo de idioma, o el recurso sale
  vacío entero en `en`/`fr` (`#671`, §4.1).
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

#### `GET /faqs?lang=` — LAS DUDAS ✅ (`#671`, 2026-09-23) · el primero de los cuatro que faltaban

El primer plato de la tanda que abre la **vía A** (§4.6): los cuatro que le faltan al menú son exactamente
los cuatro recursos que la T3 no pudo sacar del panel. Las dudas son la pieza más simple de las cuatro
—`faqs` son cuatro columnas: `question`, `answer`, `position`, `is_active`— y por eso van primero: afinan la
plantilla antes de entrar en atracciones, que es la gorda.

**Lo que decide este plato, y no es código**: qué duda es PUBLICABLE. Una pregunta sin respuesta publica
algo que el negocio no contesta, así que **una duda a medias no viaja**. Hoy las dos portadas —la del
anfitrión y la de la instancia— pintan el acordeón entero sin mirar y **solo `StructuredData::faqPage()`
salta las inservibles**, o sea que el `FAQPage` y lo que se ve YA divergen. Aquí la regla baja al DATO: lo
que sale por la API es el conjunto publicable y una landing puede pintarlo entero sin comprobar nada. De ahí
que `question` y `answer` sean **`required` sin excepción** — este plato no necesita entrada en
`OPTIONAL_BY_DESIGN`, y eso no es casualidad: es la misma decisión vista desde el contrato.

❗❗❗ **LA TRAMPA QUE CASI ENTRA, Y LA CAZÓ UNA MEDIDA, NO UNA RELECTURA: el filtro de «vacío» va DESPUÉS
del respaldo de idioma.** `Translated::pick()` encadena con `??`, no con `?:`, así que resuelve el respaldo
por clave **ausente**. Las doce dudas de esta instalación están escritas **solo en español** y sus claves
`en`/`fr` **no existen** (medido fila a fila antes de escribir nada). Una versión que comprobara si el
idioma pedido está relleno **antes** de pasar por `tr()` habría dejado este recurso **vacío entero en inglés
y en francés** —doce dudas dentro, cero publicadas—, con el JSON válido y todos los casos en verde salvo el
que se escribió para eso. ▶ *Se filtra por lo que `tr()` DEVUELVE, que es el texto que el cliente recibe.*
▶ Y el caso `''` sigue vivo por el otro lado: el panel escribe cadena vacía cuando alguien rellena y BORRA
(§4.1.ter), y ahí `??` sí la entrega. Los dos casos van en la misma guarda.

⚠️ **El desempate por `id` no es cosmético.** `position` no es única —ni el panel ni la migración lo
impiden—, y sin segundo criterio el orden de un empate lo decide el motor: dos peticiones idénticas podrían
devolver dos cuerpos y dos `ETag` distintos **sin que nadie tocara el panel**. Medido: las doce van de 1 a
12 sin empates, o sea que hoy no se nota — que es justo por lo que conviene fijarlo antes.

⚠️ **`updated_at` es de lo SERVIDO y no de la tabla**: una duda que no se publica no puede fechar lo que sí.
▶ **No se publica el `FAQPage` de schema.org**, a propósito: es MARCADO, y el producto no le impone a una
landing qué estándar de datos estructurados usa. Lo que sí es conocimiento del producto —qué duda es
publicable— viaja ya aplicado dentro del dato, que es donde no caduca.

**Medido**: `FaqsFactsTest` (10 casos, 36 aserciones) · `scripts/mutar-menu-de-hechos.sh` **58/58** (ocho
mutantes nuevos, ninguno «NO SE APLICÓ») · Pint y Larastan sin tocar la línea base · y **en vivo** contra las
doce dudas reales: 200 en los tres idiomas con `ETag` distinto por idioma y estable entre peticiones, 304
con `If-None-Match`, 422 sin `lang` y con un idioma inventado. Contrato **1.12.0** (añade, no cambia).

#### `GET /services?lang=` — LAS SECCIONES DE SERVICIOS ✅ (`#672`, 2026-09-23) · la mitad EDITORIAL

Segundo de los cuatro. **El alcance se partió en dos a propósito**, y conviene el porqué: `LandingService`
separa lo EDITORIAL —texto, foto, fichas, orden— de lo COMERCIAL, que su propio docblock llama «cero
drift» porque **se lee en vivo de los productos vinculados**. Esta tanda publica la mitad editorial y
**referencia** la comercial; los **tramos de grupo** (`GroupRateTables`, hoy sin ninguna ruta que los
sirva) van en su propia tanda porque son DINERO y arrastran una decisión de registro que merece medirse
sola. `CONVENCIONES §10.5`: se parte para poder empujar pronto.

⚠️⚠️ **`price_table` NO VIAJA, Y TIENE DUEÑO.** Es un JSON de tramos **tecleado a mano**, y el owner ya
decidió que se jubila en favor de los precios del catálogo (`DEUDA.md`, `#534`, `[DECIDIDO owner]`).
Servirlo como hecho sería publicar un precio que el checkout podría no cobrar — el modo de fallo exacto
que §3 descartó al elegir «hechos por la API» frente a «una landing que teclea precios».
▶ **Y la medida conviene entera**: hoy en local la tabla tecleada **COINCIDE** con el catálogo (30/70/100
desde 15,00 €); la contradicción que fichó `#534` se midió contra **producción** (30/75/100 desde 12,00 €),
que no se puede comprobar desde aquí. *Que hoy coincidan no absuelve al diseño: la definición del problema
es que son dos fuentes que hay que mantener de acuerdo a mano.*

⚠️ **`nav_subtitle` y `show_in_nav` tampoco viajan**: llevan sin consumidor desde `#521`, conservadas a la
espera de que el owner confirme que no vuelven. *Estar en la tabla no convierte a un campo en contrato.*

▶▶ **`products` son IDENTIFICADORES y no hay un `purchasable` al lado.** Los ids se resuelven en
`/catalog/products` —un hecho, un sitio— y solo viajan los que la cesta puede vender de verdad (`#226`:
pack activo, vendible, zona activa, precio positivo). **La lista vacía YA dice «solo-contacto»**, así que
un booleano sería el mismo hecho publicado dos veces, con opción a contradecirse. Por eso `products` es
`required` aunque venga `[]`: si fuera opcional, «ausente» y «vacía» significarían lo mismo y la landing
tendría que tratar dos casos para una realidad.

⚠️ **Una sección sin TÍTULO no viaja** —el slug es un ancla y un ancla sin encabezado es una sección en
blanco—, y **una ficha necesita sus dos mitades**: misma familia que la duda sin respuesta de `#671`.

❗ **Lo que destapó publicarlo, y no es de este carril**: en vivo salen **3 fichas en español y 2 en
inglés y francés** — falta «Grupo · De 30 a 100 alumnos» en las dos—, y **«Horario» dice cosas distintas
según el idioma**: «Todos los días, de 8:00 a 21:30» en español frente a «Outside opening» / «Hors
ouverture». No es el filtro: son los datos del panel. Avisado al carril de la WEB, que lleva la T6 de
contenido. *Publicar un dato como hecho es la forma más barata de descubrir que no lo era.*

**Medido**: `ServicesFactsTest` (13 casos, 44 aserciones) · `scripts/mutar-menu-de-hechos.sh` **68/68**
(diez mutantes nuevos, ninguno «NO SE APLICÓ») · Pint y Larastan sin tocar la línea base · y **en vivo**:
200 en es/en con `ETag` distinto por idioma, y ni `price_table` ni `nav_subtitle` en el cuerpo. Contrato
**1.13.0** (añade, no cambia).

#### `GET /bar?lang=` — EL BAR ✅ (`#673`, 2026-09-23) · el primero que lee `settings` sin lista blanca

Tercero de los cuatro, y el que **estrena un camino que la receta no había usado todavía**: es el primer
plato cuyo contenido vive en `settings`, y **no declara lista blanca** — delega en `BarPage`.
▶ **No es un rodeo de `PublicFactsBoundaryTest`, es lo que su propio docblock prescribe**: *«un hecho
público se pide por `PublicFacts` con la lista de ese recurso; **lo demás lo sirve un servicio de dominio,
que es quien sabe qué significa su ajuste**»*. `BarPage` es ese servicio: sabe que el NOMBRE gatea la
publicación, que `bar.free_entry` solo admite `yes`/`no` y cuál es el respaldo de idioma. Declarar sus
diez claves en el recurso para poder usar `PublicFacts` habría duplicado esas tres reglas, cambiando una
fuente única por dos que hay que mantener de acuerdo. ▶ **Y eso deja una regla para los platos que
vengan**: si el ajuste ya tiene servicio de dominio, se delega; la lista blanca es para el recurso que
lee `settings` a pelo, que es el caso de `/site`.

⚠️ **El NOMBRE gatea la clave `bar` entera** (`BarPage::isPublished()`: «sin él no hay titular, y sin
titular no hay página»). Una instalación sin bar **no emite un sobre vacío, no emite nada** — el mismo
patrón que `rating` en `/social-proof`. `updated_at` sí viaja siempre.

⚠️ **`free_entry` sale como BOOLEANO**, no como la cadena `yes`/`no` que guarda el panel: ese vocabulario
es un detalle de almacenamiento y un cliente no tiene por qué aprendérselo. Sin configurar —o con un valor
que el dominio no reconoce— **la clave falta, y su ausencia no significa `false`**: significa que no se
afirma nada. Emitirla siempre obligaría a elegir un valor por defecto, y los dos mienten sobre la mitad de
los bares.

⚠️⚠️ **Las DIMENSIONES viajan y son media razón de ser del plato.** `BarImage` las mide **contra el disco**
al guardar, y existen para que el navegador reserve el hueco: la carta es la imagen más grande de la web y
sin `width`/`height` la página SALTA al cargarla. Una landing que no las reciba recupera ese salto y no
sabe por qué.
❗❗ **Y esto puso en rojo el primer caso que las comprobaba, con razón**: el test tecleaba `width` al crear
la fila y el `saving()` del modelo lo **sobrescribía con `null`**, porque el fichero no existía en el
disco de pruebas. Un caso que hubiera aceptado ese `null` no habría tenido sujeto. ▶ El fixture pone un
PNG real (GD) **antes** de crear la fila y **no teclea las dimensiones**: las compara con lo que el modelo
midió, con un control que aserta primero que midió algo. Su hermano `BarPageTest` ya tenía escrita esa
receta; conviene leerla antes de escribir un caso sobre una imagen.

▶ **Una imagen SIN `alt` SÍ viaja, y aquí la regla se aparta de la de `#671` a propósito.** Una duda sin
respuesta no publica nada útil; una carta sin texto alternativo **sigue siendo la carta**, porque el
contenido ES la imagen. Esconderla no arregla la accesibilidad: quita el menú. El `alt` es obligatorio en
el formulario, así que el hueco solo aparece en filas metidas por SQL, y el contrato lo declara opcional
para que quien pinte sepa que tiene que contemplarlo.

❗❗ **`updated_at` mira LAS DOS FUENTES o miente.** El bar vive mitad en `settings` y mitad en
`bar_images`; una fecha calculada sobre una sola diría «sin cambios» justo después de que alguien
reescribiera la otra, y quien la use para decidir si su copia sigue valiendo se quedaría la vieja. Cuenta
además las imágenes **retiradas**: tener menos cartas también es un cambio del bar. ▶ Va en `BarPage` y no
en el recurso porque «cuándo se tocó el bar» es conocimiento del dominio, y **el arnés lo prueba con dos
mutantes, uno por fuente** — con uno solo, la mitad de la regla quedaría sin ejercer.

▶ **El pie va DENTRO de la foto del local**: sin foto no hay nada que pie. Y `Bar.venue` es copia INLINE
de `BarImage` —OpenAPI 3.0 no compone un `$ref` con una propiedad extra bajo `additionalProperties:
false`, que es la trampa de `#27` por cuarta vez—, así que lleva **guarda de divergencia** en
`ApiContractTest`, como manda la casa.

**Medido**: `BarFactsTest` (12 casos, 52 aserciones) · `scripts/mutar-menu-de-hechos.sh` con once mutantes
nuevos · Pint y Larastan sin tocar la línea base · y **en vivo**: 200 en es/en con `ETag` por idioma, la
carta a 1240×1754 y el local a 1600×900 medidos del fichero real. Contrato **1.14.0** (añade, no cambia).

#### `GET /attractions?lang=` — LOS JUEGOS ✅ (`#674`, 2026-09-23) · **el menú queda COMPLETO**

Cuarto y último de la vía A. `#632`·P3 ya lo había decidido midiendo: **las 23 atracciones son
PRESENTACIÓN** —nombre, descripción, foto, chapa y edad en TEXTO—, sin aforo ni venta, y las
restricciones que de verdad importan viven en Normas. Así que el plato es editorial entero.

❗❗❗ **LO QUE MÁS IMPORTA DE ESTE PLATO ES QUÉ INTERRUPTOR GATEA LA ZONA, y son dos que se parecen.**
`zones.is_active` dice que **la zona OPERA**; `zones.show_in_landing` dice que **sale en la web**. Se
desacoplaron a propósito en `2026_06_07_000002`, y la medida de hoy enseña por qué: **la zona de
cumpleaños opera —vende packs— y NO está en la landing**. Gatear por `is_active` publicaría las
atracciones de una zona que el negocio retiró de su web, sin que fallara nada. ▶ Se gatea por
`show_in_landing`, que además es **el gate exacto que usa la página**: así la API y la web no pueden
discrepar sobre qué es público. El arnés lo prueba cambiando un interruptor por el otro.
⚠️ Y la ambigüedad no es solo conceptual: el `join` con `zones` dejó `is_active` **ambigua en SQL** y
SQLite lo cantó en rojo. Las dos columnas van cualificadas — *cuando dos tablas comparten el nombre de
una columna y significan cosas distintas, la que se elige por descuido puede ser la equivocada*.

⚠️⚠️ **LA LISTA VA PLANA, con el `slug` de su zona.** La web de hoy las agrupa por zona con pestañas,
pero **agrupar es estructura y el menú no impone estructura** (`#631`): una landing que las quiera en
una rejilla sin zonas no tendría que deshacer el agrupado. El orden entregado **ya es el de la web**
—zona por su posición y dentro cada juego—, así que agrupar por `zone` lo conserva sin ordenar nada.
▶ `zone` es una **referencia**: el nombre y la foto de la zona viven en `/catalog/zones`.

❗❗ **EL MOSAICO NO VIAJA, y es lo más fácil de confundir con un hecho.** `RideMosaic` elige **tres con
nombre y dos veladas** y las coloca en un patrón de celdas. Eso no es un dato del negocio: es una
decisión de MAQUETA —cuántas caben, cuáles se disuelven— que depende del diseño de quien pinta.
Publicarlo obligaría a toda landing a heredar el mosaico de ésta. Lo que el producto sabe, y lo que
viaja, es **qué juegos hay y en qué orden**. *El límite entre hecho y presentación no está en el tipo
del dato: está en quién tiene derecho a decidirlo.*

⚠️ **`is_special` y `ticket_type_id` no viajan.** Medido: `is_special` solo lo pinta la tabla del panel y
`ticket_type_id` quedó a **0 de 23** cuando `#668` retiró el complemento por atracción; su columna espera
la migración conjunta que baja con el MAYOR. Mismo criterio que `nav_subtitle` en `#672`.

▶ **Un efecto lateral que conviene contar**: declarar el genérico de `Attraction::zone()` —lo pidió
Larastan al leer `$juego->zone?->slug`— **retiró una entrada de la línea base en otro fichero**
(`AttractionTable`, un `Model::tr()` congelado), y el trinquete obligó a bajar `FROZEN_ERRORS` de **458 a
457** en el mismo commit. *Arreglar un tipo paga en sitios que no estabas mirando.*

**Medido**: `AttractionsFactsTest` (14 casos, 47 aserciones) · `scripts/mutar-menu-de-hechos.sh` con diez
mutantes nuevos · Pint ✓ y Larastan con la línea base **más corta** · y **en vivo**: los 23 juegos (15
`jump` + 8 `kids`), traducidos, sin `is_special` ni rastro del mosaico. Contrato **1.15.0**.

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

### 4.3 `zones` adelgaza — ✅ HECHA (`#669`, 2026-09-21)

Los cuatro campos muertos de §1.7 se retiraron con su columna (`subtitle`, `age_label`, `area_sqm`,
`rides_count`): re-medido antes de tocarlos, **solo vivían en el modelo, en el formulario del panel y en
un test del seeder**. La presentación (`color`, `accent`, `image`) sigue el mismo camino que la landing;
los hechos (alturas, edades, descripción) salen por el menú.

❗❗ **`rides_count` es el caso que conviene recordar**: coincidía con el recuento REAL de atracciones
activas (15=15, 8=8), así que **no mentía**. Era un contador a mano de algo que el producto ya calcula
donde lo publica —`ridesTotal` en la portada y la misma cuenta en `AttractionsController`—. *Un contador
copiado no está mal el día que se copia: está mal el día que alguien añade una atracción y nadie sube el
número.* ▶ Y lo delató quién lo vigilaba: `ZoneImageTest` **comparaba las dos fuentes**. Que hiciera
falta compararlas era el síntoma; hoy queda la que se publica.

⚠️⚠️ **Los datos se pierden, y es parte de la decisión** (`#639`·D2): subtítulos traducidos, etiquetas de
edad y los metros cuadrados. No se migran porque no hay sitio al que migrarlos. El `down()` recrea la
forma, nunca el contenido.

⚠️ **Y el seeder era el consumidor escondido**: al aplicar la migración, la suite dio **604 errores** de
golpe —`LandingContentSeeder` seguía escribiendo las cuatro—. *El censo de una columna incluye quien la
SIEMBRA, no solo quien la lee.*

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
3. **T3 · el panel adelgaza** — **T3·1 ✅** (`#668`): salen los dos recursos con CERO uso; los otros
   cuatro esperan a tener plato en el menú o a la vía A. **Las «secciones de texto» no existen**:
   medido, ninguna de las 71 claves de `settings` está sin consumidor. Detalle en §4.6.ter.
4. **T4 ✅ · `zones` adelgaza** (`#669`): las cuatro columnas muertas, fuera (§4.3). ▶ Queda la última marca viva (`pjp-salta-record`, §4.5).
5. **T5 · v2.0.0**: el contrato de instancia cambia, así que la versión sube de MAYOR. ⚠️ **APARCADA
   el 21-09**: cortar la versión antes de estrenar la arquitectura nueva sería versionar un estado que
   va a cambiar entero.

❗❗❗ **EL ORDEN CAMBIA CON LA DIRECCIÓN DEL OWNER (21-09)**: va a hacer **una landing nueva que consuma
la API** —la vía A de §3, el destino—, así que lo siguiente son **los cuatro platos que le faltan al
menú: atracciones, dudas, servicios y el bar**. ▶ Y no es una tanda más: son **exactamente los cuatro
recursos que la T3 no pudo sacar del panel** (§4.6.ter), bloqueados PORQUE no tienen plato. Con ellos
servidos, la landing nueva los consume, el panel los sigue editando y la T3 se desbloquea sola.
✅ **CONTESTADO por el owner el 23-09**: se arranca por los PLATOS, no por el kit de widgets —que sigue
donde lo dejó `#632`·P2, esperando a que una segunda instancia lo pida—. Y con él llegó `#670`: **no se
despliega en piezas**, así que estos platos no van a producción sueltos; van dentro de la v2.0.0 grande.
✅✅ **LOS CUATRO PLATOS, SERVIDOS** (23-09): **dudas** (`#671`) → **servicios** (`#672`) → **el bar**
(`#673`) → **los juegos** (`#674`), de menos a más para que el último llegara con la receta rodada.
▶ **Con ellos, la T3 se desbloquea**: los cuatro recursos que no podían salir del panel ya tienen plato.
❗ **Y el menú tiene un quinto pendiente que no estaba en la lista de cuatro**: los **TRAMOS DE GRUPO**.
`GroupRateTables` los compone desde el catálogo y **ninguna ruta los sirve** (medido: su único llamante es
`ServicesController`). Salieron del alcance de `#672` a propósito —son dinero— y con ellos viene la
decisión de registro que `#661` dejó planteada: el contrato de VISTA quiere el importe **ya escrito**
(`groupFrom`, `address.written`) y el de API lo quiere en **céntimos** (`/prices`). Las dos tienen razón en
su superficie, así que la tanda decide si viajan los dos, y lo mide.

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

### 4.6.ter · T3·1: lo que no deja hueco, y la cadena que arrastra (`#668`, 2026-09-21)

**El owner eligió ir por grados**, con la medida delante: de los seis recursos de §1.6 solo dos tienen
CERO uso —el widget de ofertas (0 filas) y el complemento por atracción (0 de 23)— y los otros cuatro
tienen contenido vivo que **el menú de hechos no sirve** (no hay plato de atracciones ni de dudas).
Sacarlos hoy convertiría «editar la web» en «desplegar el repo de la instancia», que es justo el
criterio contrario al de §7·D1.

⚠️ **Y `faqs` no es solo presentación**, lo que conviene recordar cuando le toque: tres mecanismos del
producto cuelgan de esa tabla —el JSON-LD `FAQPage`, la chapa «Quizá ya está contestado» de `/contacto`
y el `lastmod` del sitemap—.

❗ **La tercera pieza del alcance no existía.** §4.2 hablaba de «las secciones de texto de la landing y
del bar»; censadas las **71 claves de `settings`** con el método de `#667` (incluida la composición
`'bar.name.'.$locale`), **ninguna está sin consumidor**. *Una tanda se acota midiendo, no leyendo su
propio plan.*

▶ **El contrato de instancia sube a 2 — la primera vez.** `offers` era una de las siete claves que el
composer global pone en toda vista, así que salía del contrato de vista de **las nueve páginas a la
vez**. Lo predijo la propia lista el día que nació (`#649`), y el aviso no es retórico: el paquete
declara su `contrato` en `instancia.json` y el producto lo escribe en el log al arrancar. Los dos
manifiestos —plantilla y PlayJump— van al día en el mismo cambio.

❗❗❗ **PODAR POR CLASE NO PODA UNA FEATURE.** El guion de poda de `#667` retiró las clases del widget y
dejó **vivo** todo lo que no es una clase: sus `@keyframes`, sus tokens (`--offw-spring`,
`--offw-accent`) y los selectores que mezclaban una clase suya con otra genérica
(`.offw-gift.gm-1 .all`). Lo destaparon tres guardas a la vez —tokens sin declarar, excepciones sin
sujeto, bucles infinitos sin su regla—. *Una feature se poda por su BLOQUE*: 118 líneas de una vez, con
la cabecera de sección como límite.

❗❗ **Y UNA RETIRADA ARRASTRA SU CADENA. Ésta tenía cinco eslabones**, y solo el primero era obvio:
1. **El panel entero cayó**: `AdminSettingsHub` importaba `OfferResource` y lo listaba. **112 rojos**
   de golpe. ⚠️ El censo previo buscó `\bOffer\b`, que **no casa con `OfferResource`** — *al retirar una
   clase se busca su nombre y sus derivados, no solo el modelo*.
2. **Una clase de estado sin consumidor**: `body.book-bar-visible` la escribía la barra flotante en cada
   cambio de visibilidad, y su ÚNICA regla era la que apartaba el lanzador del widget. Se fue con él.
3. **Seis guardas** lo usaban de sonda o en sus listas: `ScrollLockOwnerTest` (un superpuesto menos),
   `ShapeScaleTest` (tres excepciones y una aguja del escaneo), `MotionBudgetTest` (dos bucles),
   `ArmazonContractTest` (el lanzador que se apartaba), `AdminNavigationTest` (23 → 22 tarjetas) y
   `ModuleBoundariesTest` (la costura `Attraction → TicketType`). **Las líneas base encogieron**, que es
   lo único que se les permite.
4. **La sonda de las at-rules se cayó por TERCERA vez** (era `.nav__links`, luego `.book-bar-visible`) y
   no había cuarta: medido, **cero** clases del armazón se declaran solo dentro de un `@media`. *Una
   sonda atada a un dato real caduca con el dato*, así que ahora prueba el INSTRUMENTO —que el recorrido
   desciende en las at-rules— con un CSS de prueba, y una segunda mitad comprueba que el corpus real las
   tiene: sin ella, descender perfectamente sobre un corpus vacío pasaría por verde.
5. **Un test que el censo no vio**: `AttractionComplementPanelTest` no nombra ninguna clase retirada,
   solo la COLUMNA `ticket_type_id`. *El censo de una feature incluye su columna.* Se partió por lo que
   afirma cada caso (§4.5.bis): los dos del aviso se fueron y los de «Destacada» se quedan.

⚠️ **Lo que NO se borra: las tablas.** `offers` y la columna `attractions.ticket_type_id` siguen ahí. Las
migraciones destructivas van juntas en la tanda que sube el MAYOR de la versión (T4/T5), y además una
tabla vacía no molesta a nadie: en producción no se puede comprobar hoy que lo esté.

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
