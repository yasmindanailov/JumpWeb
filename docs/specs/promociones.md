# [SPEC] Promociones — ofertas con fecha y regalos, en su propia página del panel y en los sitios que dibuja el mockup

> Estado: ⬜ **borrador aprobado en sus decisiones** · Última actualización: 2026-09-25 · Decisiones: `#684` (el sistema,
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
- **Empieza por** §1.2 (el censo) → §4.1 (el modelo) → §4.3 (dónde se pinta).
- **Trampas**: la fecha es la del PARQUE (`DisplayTime`); sin texto en un idioma, no sale en ese idioma (no se
  inventa); la isla dice la oferta solo en sus 3 últimos días (situación `oferta`).

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
`kind` (`oferta` | `regalo`) · `text` (json es/en/fr) · `target_type` (`installation` | `zone` | `product`) · `target_id`
(nulo en instalación) · `starts_at` (nulo = ya) · `ends_at` (obligatorio en `oferta`; nulo en `regalo`) · `is_active` ·
`position`. Vigente = activa y hoy (del parque) dentro de sus fechas. Borrar la zona o el producto borra sus promociones.

### 4.2 El panel — «Promociones», página propia
Listado con su estado (programada, vigente, acabada), crear con el texto en tres pestañas, la clase, el objetivo
(selector de producto o de zona) y las fechas; la validación exige fin en una oferta.

### 4.3 Dónde se pinta
Un hecho público nuevo, `promotions` (API `/promotions`, contrato menor), con las vigentes en el idioma pedido:
`{kind, text, ends_at, target: {type, id}}`. La página lo pide (`PageFacts`) y su modelo las reparte por los sitios del
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

## Anexo · fila del enrutador
`| Promociones · ofertas y regalos · la etiqueta de oferta · dónde sale | docs/specs/promociones.md §0 |`
