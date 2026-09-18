# [SPEC] Promo con precio anterior tachado y el recuadro de la oferta en la sección de entradas

> Estado: ⏸️ 📜 **APARCADA por el owner el 2026-09-18 sin implementar** (`DECISIONES #628`: *«no quiero spec, ni
> sistema ni nada; es una chapuza rápida, más adelante haremos un sistema de ofertas»*). En su lugar se hizo la
> chapuza declarada: el «antes» se deshace de la rebaja con el ajuste `promo.percent` y el recuadro es el ajuste
> `promo.banner.{idioma}` (`Setting::promoPercent()`, `WritesLandingValues::antes()`). **Lo que sigue valiendo de
> aquí es la medición de §1 y las opciones de §3**: es el punto de partida del sistema de ofertas cuando llegue.
> Última actualización: 2026-09-18.

## §0 · Antes de tocar

- **La regla que ordena todo**: el precio que se COBRA sigue siendo `prices.amount_cents`, la única fuente
  (`#61`). El precio anterior es PRESENTACIÓN: un dato más de la fila de precio que nunca entra en un cálculo
  de dinero. El recuadro de la oferta es un AJUSTE por instalación (texto i18n, interruptor y fecha de fin),
  no copy del producto: nada de «20 %» en código.
- **Por dónde empezar**: §4.1 datos → §4.2 panel → §4.3 web → §4.4 API y cajón → §6 verificación.
- **Trampas que aplican antes de tocar**: `EditCatalog::afterSave()` olvida `cta.min_price_cents` (`PERF-05`);
  el precio anterior no mueve el mínimo, pero la edición pasa por ahí igual · `RateCards` calcula «Ahorras»
  con `displayPriceCents()`: NUNCA contra el precio anterior · el cajón (`resources/js/sidebar/**`) es del
  carril del SPA: la API expone el campo y el SPA lo pinta; buzón antes · `home.blade.php` y `landing.css`
  son del carril de la web: buzón antes · el contrato añade campos → `info.version` 1.1.0 en el mismo commit,
  `/release` v1.1.0 y despliegue DE NOCHE (`#594`, guarda 8) · un anuncio de rebaja pide el precio previo REAL
  (el más bajo de los 30 días anteriores): la promo del 17-09 nació sin él y sin fecha de fin (`ENTORNOS.md`
  §6) y este diseño la completa · la fecha de fin apaga la PRESENTACIÓN (tachado y recuadro); los precios los
  sube el owner en el panel, o un guion inverso desde `audit_logs`.
- **Estado**: ⬜ borrador (2026-09-18), a revisar por el owner (§0 y §4) antes del primer commit de código.
- **Dinero**: no toca ningún fichero del `CRITICAL_RE`; `INVARIANTES.md` §1 releída, ninguna cambia (§5).

## 1. Contexto y problema — MEDIDO (2026-09-17/18)

- **Lo que hay en producción desde el 17-09 a las 22:35**: las 5 entradas (`ticket_types` 100–104) con sus 9
  filas de `prices` al 80 % y el badge «−20 % online», aplicado como DATO con copia previa y rastro en
  `audit_logs` (`ENTORNOS.md` §6). La tarjeta enseña el precio nuevo y el badge; **no enseña el precio
  anterior ni hay fecha de fin**. El owner decide el 18-09 (00:10): «hay que poner el precio original tachado»
  y «un recuadro en la propia sección de las entradas con el copy».
- **Un descuento por canal en el resolutor tocaría el núcleo**: `grep -rn 'priceCents(' app` da llamadas en
  `OrderCreator`, `AddonResolver`, `ItemEditPricing`, `AgeFamilySealer`, `PostFormAddons`,
  `AddonDateReconciler` y `CreateManualOrderPage`, y `CartPricer`, `AvailabilityReader`, `CatalogReader` y
  `SlotOffer` leen la relación `prices` directamente. Nueve de ellos están en el `CRITICAL_RE` del `pre-push`.
- **La taquilla cobra de la misma tabla**: `CreateManualOrderPage` usa `RateResolver::priceCents()` y no deja
  cambiar el precio a mano. Por eso el badge dice «online» y no «solo online».
- **Dónde se pinta el precio de una entrada**: `components/site/rate-rail.blade.php` (solo lo usa
  `home.blade.php`, sección 02; sus datos los arma `RateCards`, con `badge`, `price`, `special` y `saving`) ·
  `pages/pricing.blade.php` (`/precios`, `PricingController`, tabla propia con `prices.rateType`) · el cajón
  (`steps/CatalogStep.vue` y `catalog.js` pintan `from_price_cents` y `badge`; `TimeStep.vue` también toca
  precio) · la API `CatalogProductResource` (`from_price_cents`, `badge`; esquema `CatalogProduct` en
  `openapi/v1.yaml`).
- **Ajustes i18n ya existen** en `Settings.php` (claves `registration.label.es`, sección `landing_texts`), y
  la landing lee toda la tabla `settings` una vez por petición en `AppServiceProvider`.
- **Tests que cubren la superficie**: `RateRailSectionTest`, `Api/V1/CatalogTest` (contrato con Spectator),
  `Sidebar/SidebarDomContractTest`, `Catalog/ProductGiftsTest` (molde de un campo nuevo del producto).
- **Visto en local con el paquete del cliente** (17-09, 390 y 1280 px): el badge cabe en una línea junto al
  chip de zona y la tarjeta recalcula «Ahorras 4,80 €» sola.

## 2. Objetivo

1. En cada tarjeta de entrada con promo vigente, el **precio anterior tachado** junto al actual, para la tarifa
   normal y la especial: portada (sección 02), `/precios` y el cajón. Legible por lector de pantalla («Antes»).
2. Un **recuadro de la oferta dentro de la sección 02**, encima del carril, con título y cuerpo por idioma y,
   si la hay, la fecha de fin; gobernado desde Ajustes.
3. **Apagar la promo es un interruptor** (o llegar a la fecha de fin): desaparecen el recuadro y los tachados
   en las tres superficies sin tocar precios.
4. **Cero cambios en un camino de cobro**: ningún fichero del `CRITICAL_RE`, suite verde, contrato 1.1.0
   solo por adición.

**Fuera**: descuento por canal (taquilla a precio anterior), cupones, precios que cambian solos por fecha, la
retirada del badge (es dato del producto y lo quita el owner), el precio tachado en packs (mismo dato, se
haría después si se pide).

## 3. Opciones consideradas

- **A · Descuento por canal en `RateResolver`** (el catálogo guarda el precio original; la web cobra menos;
  taquilla no). Honesto con «solo online» y con fecha de fin natural. **Descartada**: ocho servicios del núcleo
  y cuatro lectores directos de `prices` (§1), verificadores de concurrencia, y el panel tendría que declarar
  canal. Es el diseño correcto si las promos se vuelven recurrentes; se anota como idea, no como deuda.
- **B · Precio anterior de PRESENTACIÓN por fila de precio + promo como ajuste** (elegida): la fila `prices`
  gana `compare_at_cents`; un ajuste `promo.*` enciende recuadro y tachados. Cero dinero. Coste: dos números
  por tarifa que mantiene el owner; taquilla cobra lo mismo que la web (ya es así).
- **C · Solo el recuadro con copy, sin tachado**. Descartada por el owner (18-09) y porque un anuncio de
  rebaja sin precio previo no se sostiene.

## 4. Diseño elegido

### 4.1 Datos
- Migración `add_compare_at_cents_to_prices` (futuro): `prices.compare_at_cents` `unsignedInteger` nullable.
  Regla de guardado: se persiste solo si es **mayor** que `amount_cents`; si no, `null`. Céntimos, como todo.
- Ajustes (tabla `settings`, misma lectura única por petición): `promo.active` (bool, defecto `false`) ·
  `promo.title.{es,en,fr}` · `promo.body.{es,en,fr}` · `promo.until` (fecha, nullable). **Vigente** =
  `active` y (`until` vacía o `hoy ≤ until`, en la zona horaria de la instalación).

### 4.2 Panel
- `CatalogForm`: junto a cada precio por tarifa, «Precio anterior (tachado)» en euros, con la ayuda «solo se
  enseña si hay promo vigente». `InteractsWithCatalogForm::upsertPrices` lo guarda con la misma normalización
  de coma decimal y lo audita en `catalog.prices_updated` (`compare_at: {from, to}`).
- `Settings.php`: sección nueva «Promoción» (`admin.settings.section_promo`) con el interruptor, título y
  cuerpo por idioma (pestañas es/en/fr como en `landing_texts`) y la fecha de fin.

### 4.3 Web (carril de la web; aviso en el buzón antes)
- `TicketType::displayCompareAtCents()`: el `compare_at_cents` de la MISMA tarifa que `displayPriceCents()`.
- `RateCards`: `was` y `special_was` (formateados) solo si la promo está vigente y el dato existe; `saving` no
  cambia. `rate-rail.blade.php`: `<s class="rate-card__was">8,00 €</s>` delante del número, y el especial con
  su tachado; el recuadro `rate-rail__promo` (futuro) encima del carril, dentro de la sección 02, solo si
  vigente. `pages/pricing.blade.php`: el tachado en su tabla.
- CSS en `landing.css`: `.rate-card__was` (tinta suave del sistema, tachado, tamaño menor) y `.rail-promo`
  (tarjeta del sistema, como las demás pegatinas; nada nuevo de color). Móvil primero; el owner lo juzga EN
  VIVO en `localhost:8081` antes del commit.

### 4.4 API y cajón (carril del SPA; aviso en el buzón antes)
- `CatalogProductResource`: `from_compare_at_cents` (entero nullable; `null` si la promo no está vigente) y
  `promo_active` (bool). `openapi/v1.yaml`: los dos campos en `CatalogProduct` y `info.version` **1.1.0** en el
  mismo commit. El cajón pinta el tachado en `CatalogStep.vue` (y en `TimeStep.vue` si enseña precio) y
  reconstruye el chunk SSR.

### 4.5 La instancia PlayJump, tras desplegar (DATO, desde el panel o por guion con copia previa)
- `compare_at_cents` de las 9 filas = los `from` de `audit_logs` del 17-09 (800, 1000, 1200, 1500, 1800, 1200,
  1400, 1800, 2200), que son el precio real de los 30 días anteriores. `promo.active = true`, la fecha de fin
  que diga el owner, y el copy de §4.6. Fin de promo: `promo.active = false` y subir los precios en el panel.

### 4.6 Copy (dato de la instancia; el producto no lo lleva)
- ES · título «−20 % en todas las entradas online» · cuerpo «Compra tu entrada en la web, elige día y hora,
  y ahorra un 20 %.» · si hay fecha: «Hasta el D de mes.»
- EN · "20% off all tickets online" · "Book on the website, pick your day and time, and save 20%."
- FR · «−20 % sur toutes les entrées en ligne» · «Réservez sur le site, choisissez le jour et l'heure, et
  économisez 20 %.»
- Tipografía: menos tipográfico (U+2212) y espacio fino antes de «%» en ES y FR, sin espacio en EN, como el
  badge que ya está en producción.

## 5. Impacto en invariantes

Ninguna cambia. `PERF-04` (sin N+1): `compare_at_cents` viaja en la misma fila `prices` ya cargada. `PERF-05`:
la edición sigue pasando por `afterSave()`. `PAY-19`: una fiesta vendida no se mueve; aquí no se toca ningún
pack ni ningún pedido. El `CRITICAL_RE` queda intacto, y el `pre-push` lo demuestra.

## 6. Plan de verificación empírica

- `PriceCompareAtTest` (futuro): el panel guarda el precio anterior, normaliza a `null` si no es mayor, y lo
  audita; la API lo expone solo con promo vigente.
- `RateRailSectionTest`: tachado presente con promo vigente y dato; ausente con `promo.active=false`, con
  `until` pasada o sin dato; «Ahorras» idéntico antes y después; el recuadro solo si vigente.
- `Api/V1/CatalogTest` con Spectator contra `openapi/v1.yaml` 1.1.0; `SidebarDomContractTest` si el cajón lo
  pinta; un caso de `/precios`.
- Mutación (`/mutar`): quitar la condición «vigente» del recurso y del carril debe poner en rojo un test cada
  una; el arnés se guarda en `scripts/mutar-promo.sh` (futuro).
- Empírico: local con el paquete del cliente, 390 y 1280 px, el owner en vivo; tras `/release` v1.1.0 y el
  despliegue de noche, `curl` de `/api/v1/catalog/products` con los campos nuevos y la portada con los
  tachados; `VERIFY_CONC` no aplica (ningún fichero crítico).

## 7. Revisión y decisión

Pendiente del owner: §0 y §4 (en especial la opción B frente a la A, la fecha de fin y el copy). Al aprobar:
estado ✅, `/decision` con el siguiente número de la banda de plataforma, y la casilla en el tracker.
