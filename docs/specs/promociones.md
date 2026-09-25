# [SPEC] Promociones — ofertas con fecha y regalos, en su propia página del panel y en los sitios que dibuja el mockup

> Estado: 🟦 **T1 en el árbol** (modelo, panel, hecho, regalos migrados, Kids y Jump; §8) — le falta el OJO del owner ·
> Última actualización: 2026-09-25 noche · Decisiones: `#684` (el sistema,
> owner 24-09) y `#770` (las cuatro respuestas del owner, 25-09). Carril: **plataforma**. Fuente del diseño: el mockup
> (`#767`), `components/marketing/OfferTag.jsx` y su guía, y los sitios del §1.2. Hermanas: `isla-y-landing-nueva.md`
> (las páginas y la isla), `instancia-y-landing-fuera.md` (el menú de hechos).

## §0 · Antes de tocar

- **Regla**: una promoción es un TEXTO en es/en/fr, vinculado a un **producto**, una **zona** o **toda la instalación**, y
  sale SOLA en todos los sitios de su objetivo que dibuja el mockup (§1.2). Dos clases: **oferta** (fecha de fin
  OBLIGATORIA; la cara única `OfferTag`, encima del precio que cambia) y **regalo** (permanente; donde hoy salen los
  regalos). Varias vigentes sobre lo mismo: todas, apiladas, la que acaba antes primero.
- **No es dinero**: una promoción no cambia ningún precio. Un descuento de verdad sigue siendo hecho de precio
  (`promo.percent`, `#628`) y va por `CRITICAL_RE`; su TEXTO («−20 % online hasta el 30») es una promoción.
- **Nunca en el aviso general** (`SiteNotice`: «una oferta nunca va aquí»).
- **`gifts` no se rompe**: los recursos de la API siguen dando `gifts`, calculado desde los regalos (§4.4): el cajón,
  el post-form y cualquier landing no notan el cambio.
- **Empieza por** §1.2 (el censo) → §8 (lo construido) → §4.3 (dónde se pinta).
- **Trampas**: la fecha es la del PARQUE (`DisplayTime`), días y no instantes; una OFERTA sin texto en un idioma no
  sale en él y un REGALO cae al respaldo; la isla dice la oferta solo en sus 3 últimos días; una página NUEVA que pinte
  ofertas pide el hecho `promotions` en la declaración de páginas del paquete; en local, la hoja de la instancia se COPIA
  (`cp -r ../instancias/playjump/publico/instancia public/`).

## 1. Contexto — MEDIDO (2026-09-25)

### 1.1 Lo que hay

- `#684` (owner, 24-09): un sistema de promociones en su propia página del panel, texto + sitio + fechas, «el operador
  las gestiona todas en un sitio»; el aviso de arriba es para noticias, cada oferta en la página de su producto.
- `ticket_types.gifts` (`#589`): los REGALOS del producto (traducibles, lista), en la ficha del panel; los sirven
  `CatalogAddonResource`, `ResolvedAddonsResource` y `PostFormAddonResource`, y los pintan la portada de hoy,
  `/cumpleanos`, `/servicios` y el post-form.
- La promo de precio `#628` (`promo.percent`, `promo.banner.*`): en producción, cambia precios; aparte.

### 1.2 El censo del mockup: dónde sale una oferta (y su objetivo natural)

| Página | Sitio | Componente del mockup | Objetivo |
|---|---|---|---|
| Portada | Cabecera, varias ofertas a la vez | `OfferTag` suelta | instalación |
| Portada | «¿Qué plan es el tuyo?» | `PlanDoor offer` | zona (Kids, Jump) · producto (el pack) |
| Portada | Cumpleaños y Jump en su bloque | `PromoSplit offer` · `PriceTag offer` | producto · zona |
| Portada | El selector de plan de la isla | opción `offer` | zona · producto |
| Kids/Jump | Cabecera (pieza 1) | `DayRates offer` | zona |
| Kids/Jump | Precio (pieza 3), encima de la tabla | `offer` de la pieza | zona · producto (su fila) |
| Kids/Jump · Cumpleaños | La isla, 3 últimos días | situación `oferta` | la de la página |
| Cumpleaños | Cabecera | `PriceTag offer` | producto (el pack) |
| Visítanos | «Qué traer» (pieza 4) | `ProofList` item `offer` | producto (complemento) |

Y los REGALOS: el pack en la portada (`PromoSplit gift`, «Entrada gratis de 1 hora para el cumpleañero…») y los
«Regalos» de lo que incluye; hoy, `gifts`.

## 2. Objetivo

Que el operador cree, programe y quite ofertas y regalos en una sola página, en tres idiomas, y que salgan solos y
coherentes en todos los sitios del mockup de su objetivo, con la cara del sistema, sin tocar precios.
**Criterios**: una oferta de Kids con fin el 30-09 sale en la cabecera, el precio y la puerta de Kids, y en la isla del
27 al 30; el 1-10 ya no sale en ningún sitio; un regalo de un pack sale donde salía `gifts`, y la API da el mismo `gifts`.

## 3. Opciones — decididas por el owner (`#770`)

1. Dónde sale: **según su objetivo** (no sitio a sitio). 2. Objetivos: **producto, zona e instalación**. 3. Los regalos:
**pasan a Promociones** (se recomendaba dejarlos en el producto; el owner prefiere un solo sitio). 4. Varias a la vez:
**todas, apiladas** (se recomendaba solo la más urgente).

## 4. Diseño

### 4.1 El modelo — `promotions` (Platform)
`kind` (`offer` | `gift`) · `text` (json es/en/fr) · el objetivo en `zone_id` o `ticket_type_id` (las dos nulas =
instalación) · `starts_on` (nulo = ya) · `ends_on` (obligatorio en una oferta) · `is_active` · `position`. Vigente =
activa y hoy (del parque) dentro de sus fechas. Borrar la zona o el producto borra sus promociones (§8).

### 4.2 El panel — «Promociones», página propia
Listado con su estado (programada, vigente, acabada), crear con el texto en tres pestañas, la clase, el objetivo
(selector de producto o de zona) y las fechas; la validación exige fin en una oferta.

### 4.3 Dónde se pinta
Un hecho público nuevo, `promotions` (API `/promotions`, contrato menor), con las vigentes en el idioma pedido:
`{id, kind, text, ends_on?, target: {type, zone? | product?}}`. La página lo pide (`PageFacts`) y su modelo las reparte por los sitios del
§1.2 según el objetivo; la cara es `OfferTag` (componente de la instancia, apiladas); la isla recibe su `offer` en los 3
últimos días de la que acabe antes.

### 4.4 Los regalos, sin romper `gifts`
Migración: cada línea de `ticket_types.gifts` pasa a una promoción `regalo` de ese producto (sus tres idiomas);
`TicketType::giftLines()` pasa a leer las promociones vigentes de clase regalo. Los recursos que sirven `gifts` no cambian
de forma. La ficha del producto pierde su campo de regalos (se gestionan en Promociones).

## 5. Impacto en invariantes
Ninguno de dinero (`PAY-*`): una promoción es texto. `SEC`: el texto se pinta escapado (texto plano). La migración de
`gifts` se ensaya en staging con la v2.0.0 (copia fiel de las líneas en sus idiomas).

## 6. Verificación
Modelo (vigencia en la fecha del parque, clases, objetivos), la migración de `gifts` (idéntica por idioma), la API (solo
vigentes, idioma, sin texto no sale), el panel, y en vivo Kids/Jump con una oferta de Kids y otra de la instalación.

## 7. Revisión y decisión
Aprobadas las cuatro respuestas del owner (`#770`, 25-09). Pendiente de su ojo el panel y la primera oferta en vivo.

## 8. Lo construido — T1 (2026-09-25 noche)

- **Técnico, decidido por el estándar** (sin pregunta: no cambia el tipo de producto): la clase en inglés como el resto
  de columnas (`offer` | `gift`); el objetivo en DOS claves foráneas con `cascadeOnDelete` y no una pareja polimórfica
  (la base de datos borra lo de una zona o un producto borrados); `starts_on`/`ends_on` como DÍAS del parque; el texto
  SIN fecha y la fecha la escribe la página («Hasta el :fecha, :texto», `comun.oferta_hasta` de la instancia): así la
  fecha es siempre la real y una sola; el orden del apilado, la que acaba antes y, entre iguales, juntas por objetivo.
- **Producto**: `Booking\Models\Promotion` (reglas en `saving`: una oferta sin fin o con dos objetivos no se guarda),
  `PromotionBoard::current()` (vigentes y con su objetivo vivo), `GET /api/v1/promotions?lang=` y el hecho de página
  `promotions` (contrato **1.29.0**), «Ajustes → Venta → Promociones» (`catalog.manage`, estado vigente/programada/
  acabada/apagada y los idiomas que faltan), auditoría `catalog.promotion_*`, alias de morfo `promotion`.
- **Los regalos**: la migración copió las 10 líneas de producción de `ticket_types.gifts` (6 packs, 2 menús) a regalos y
  retiró la columna (`down()` la rehace); `giftLines()` las lee con carga anticipada en el catálogo, las fichas, el
  post-form y la página de servicios; `gifts` de la API, IDÉNTICO medido en local (productos y complementos). La ficha
  del producto los ENSEÑA y dice dónde se cambian. Cambio de conducta, a propósito: cada regalo cae a su respaldo por
  separado (antes, una lista entera por idioma).
- **Kids y Jump** (instancia): `<x-instancia::offer-tag>` (`.pj-oferta`, la cara del `OfferTag`) encima de las tarifas de
  la cabecera (instalación + su zona), encima de la tabla de la pieza 3 (además, las de sus productos), la píldora del
  cierre (`deadline`, apiladas) y la isla (`offer`, 3 últimos días). En vivo a 1280 y 390, consola limpia.
- **Pendiente**: los sitios de la portada, Cumpleaños y Visítanos (§1.2), con sus páginas; el TACHADO y la línea del
  descuento de la calculadora, que son DINERO (el mecanismo de precio de `#631`, fuera de esta spec).
- Tests: `PromotionResourceTest` (8), `PromotionsFactsTest` (6), `GiftsToPromotionsMigrationTest` (2),
  `ProductGiftsTest` (+1), `CatalogEditTest`, `ApiContractTest`, `AdminNavigationTest` (25 tarjetas), `pagina.test.js` (+1).

## Anexo · fila del enrutador
`| Promociones · ofertas y regalos · la etiqueta de oferta · dónde sale | docs/specs/promociones.md §0 |`
