# [SPEC] La instancia y la landing fuera — el producto expone HECHOS, el cliente pinta su web (F5 del programa)

> Estado: ✅ **APROBADA — el censo medido y las tres decisiones contestadas por el owner** (`#639`) ·
> Última actualización: 2026-09-19 · Decisión asociada: `DECISIONES #639`.
> Carril: **plataforma**. Origen: `specs/producto-e-instancias.md` §4.1→§4.4 y `#610`→`#616`; el principio del
> menú de hechos es `#631` y sus tres rasgos `#632`. Hermana: `specs/cajon-empaquetable.md` (F4, cerrada).

## §0 · Antes de tocar

- **Regla que ordena todo**: la landing SALE del producto. El producto se queda con el dominio, el panel, el
  cajón empaquetado y **una API pública de HECHOS**; quien diseña una landing usa lo que quiera de ese menú y
  **todo es opcional** (`#631`). Nada de presentación vuelve al producto.
- **Empieza por** §1 (el censo, medido) → §4.1 (el menú) → §4.2 (lo que sale del panel) → §7 (lo que decide el owner).
- **Trampas, antes de tocar**:
  - ⚠️⚠️ **`settings` mezcla el secreto de Redsys con el correo de contacto** (71 filas, medidas en §1.3):
    la API publica una **lista blanca por recurso**, nunca la tabla. Un volcado filtra `redsys_secret_key`.
  - ⚠️⚠️ **`contact.phone` y `theme.*` los leen los CORREOS** (§1.2): no pueden bajar al paquete de la
    instancia, porque ahí el CSS del cliente no llega. Mismo motivo que `#209` dio para el color de acción.
  - ⚠️ **Una URL que cambia es SEO perdido y no falla nada**: el sitemap se compara antes y después (11 URLs,
    capturadas en §1.5).
  - ⚠️ El criterio «cero marca del cliente en el código» **no se puede leer como un `grep` a secas**: 166 de
    sus 167 apariciones son citas de artboards en comentarios (§1.4).
- **Estado**: censo hecho y las tres decisiones de producto contestadas (`#639`, §7). Sin código: **lo
  siguiente es la T1 de §4.6**, el menú de hechos con su lista blanca.
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

1. **T1 · el menú de hechos**, recurso a recurso, con su lista blanca y su guarda.
2. **T2 · el paquete de la instancia**: repo desde plantilla, la landing actual mudada tal cual (vía B) y el
   sitemap comparado.
3. **T3 · el panel adelgaza**: los seis recursos y las secciones de texto.
4. **T4 · `zones` adelgaza** y cae la última marca viva.
5. **T5 · v2.0.0**: el contrato de instancia cambia, así que la versión sube de MAYOR.

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
