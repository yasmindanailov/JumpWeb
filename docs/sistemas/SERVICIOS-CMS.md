# SISTEMA — /servicios data-driven + entidad CMS `LandingService`

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Estado heredado: ✅ implementado (modelo A).** Los comentarios del código heredado citan
> `PLAN-SERVICIOS-DATA-DRIVEN.md` y decisiones `#NNN` del repo origen (histórico, no portado);
> este documento es su sustituto en JumpWeb.
> Vocabulario: «cumpleaños», «zona», «pack», «aforo» (vocabulario del sector origen; su
> generalización se decide en `00-REFACTOR.md` Fase 1/2).

## 1. Problema que resuelve
Sin este sistema, los packs tenían UN solo destino en la landing: la sección «Cumpleaños»
(`events-section`). No existía forma de anunciar un pack en otra superficie ni un hogar
data-driven para packs que no son de cumpleaños:
- Zona `show_in_landing`: solo controla la sección de zonas/atracciones, no la de packs.
- Producto `is_active`: o sale en Cumpleaños, o no sale en ningún sitio.
- `/servicios` era una vista estática (`Route::view` + placeholders en `lang/*/services.php`).

## 2. Modelo elegido (A: entidad única de clasificación)
La entidad CMS **`LandingService`** es la **fuente única de clasificación de superficie**:
- Un pack **con** un `LandingService` que lo referencie → se anuncia en `/servicios`.
- Un pack **sin** `LandingService` → es de cumpleaños (defecto).
- El diseño alternativo B (`zones.landing_section` como flag en la zona) quedó **DESCARTADO**:
  no existe esa columna. La zona sigue siendo solo el contenedor de **aforo**.

Regla codificada en `TicketType::scopeBirthdaySurfacePacks()`:
`ofType(PACK)->is_active->sellable()->inOperationalZone()->whereDoesntHave('landingService')`.
FUENTE ÚNICA para `HomeController` y `EventsController` (no divergen). Un pack «se mueve» a
Servicios en cuanto se le crea un `LandingService`, sin deploy.

## 3. Modelo de datos — tabla `landing_services`
Migraciones `2026_06_16_000001_create_landing_services_table` +
`2026_06_16_000002_add_price_table_to_landing_services`.

| Columna | Tipo | Notas |
|---|---|---|
| `slug` | string unique | = anchor de `/servicios#{slug}` (lo usa el nav) |
| `accent_word` | json nullable | palabra grande de acento (i18n) |
| `title` / `body` | json nullable | título / descripción de la sección (i18n) |
| `zone_label` | json nullable | badge «Zona …» (i18n) |
| `specs` | json nullable | lista `[{label,value}]` por idioma |
| `price_table` | json nullable | tabla de tarifas de grupo **SOLO INFORMATIVA** (ver §3.bis) |
| `nav_subtitle` | json nullable | subtítulo del item del dropdown del nav (i18n) |
| `image` | string nullable | ruta de imagen (`public/…`) |
| `ticket_type_id` | FK nullable **unique**, `nullOnDelete` | pack comprable; NULL = solo-contacto |
| `position` | unsignedInteger default 0 | orden |
| `is_active` | boolean default true | aparece en `/servicios` |
| `show_in_nav` | boolean default true | aparece en el selector «Servicios» del nav |

Detalles del FK: `unique` = relación 1:1 (`TicketType::landingService()` es `HasOne`) — un pack no
puede estar en dos secciones; NULL sí se repite (varias secciones de solo-contacto conviven);
`nullOnDelete` → si se borra el pack, la sección degrada a contacto sin romper.

Modelo `app/Domain/Content/Models/LandingService.php`: `HasTranslations`, `$guarded=[]`, casts json/boolean,
scopes `active()` / `inNav()` / `ordered()`, relación `ticketType()`, y `isPurchasable()`.

### 3.bis `price_table` — tarifas de grupo INFORMATIVAS
JSON estructurado (zonas → duraciones → tramos de cantidad con precio laborable/finde, en
**céntimos**) que el blade pinta como tablas con pestañas. **Solo presentación**: NO toca el flujo
de compra ni implementa precio por tramos en el dinero (esa feature quedó diferida en el repo
origen, no portada). NULL = la sección no muestra tabla (caso por defecto).

## 4. Propiedad del dato (invariante: cero drift)
**`LandingService` es EDITORIAL; lo COMERCIAL vive en el `TicketType` vinculado + su `Zone`:**
precio = `TicketType::prices`/`rateType` · complementos = pivote `product_addons` (`addons()`) ·
mín/máx = `min_qty`/`max_qty` · aforo = la `Zone`. La card de `/servicios` lo lee **en vivo** por
la relación. El `LandingService` **no guarda** precio, complementos ni cupo.

**Comprabilidad — fuente única** `TicketType::isSellablePackForLanding()`: pack + `is_sellable` +
`is_active` + `zone_id` no nulo + zona `is_active` + precio > 0. La usan
`LandingService::isPurchasable()` (card de la landing) y el aviso del panel
(`LandingServiceForm::packWarning`) para no divergir. Motivo del requisito de zona: los packs
venden por aforo de ZONA; sin zona `maxQty()=0` → no debe anunciarse «Reservar».

## 5. Superficies (dónde se pinta)
- **`/servicios`** — `app/Http/Controllers/ServicesController.php` (invokable):
  `LandingService::active()->ordered()->with(['ticketType.zone','ticketType.prices.rateType','ticketType.addons.prices.rateType'])`.
  Vista `pages/services.blade.php`: conserva la maqueta editorial (row, badges, accent word, specs)
  leyendo de la entidad. Card con pack comprable → precio + complementos + CTA «Reservar»
  (deep-link al sidebar de compra: `$store.purchase.open()` + `Livewire.dispatch('show-packs')`);
  sin pack comprable → CTA «Pedir información» (/contacto).
- **Selector «Servicios» del nav** (`components/site/nav.blade.php`): **estático + data-driven** —
  items fijos «Cumpleaños» (→ `/cumpleanos`) y «Otros eventos», más los `LandingService` con
  `show_in_nav`, enlazando a `/servicios#{slug}`. Datos compartidos site-wide en
  `AppServiceProvider::navServices()`: `LandingService::active()->inNav()->ordered()` con solo
  las columnas que el nav necesita, guardado por `tableExists('landing_services')` (CI/instalación
  limpia). **`active()` ADEMÁS de `inNav()`**: un servicio oculto de /servicios no debe salir en el
  menú (anchor roto).
- **Sección Cumpleaños** (home + /cumpleanos): `TicketType::birthdaySurfacePacks()` (§2). El
  componente `events-section` no cambió; el filtro vive en los controladores.

## 6. Panel (Filament)
`app/Filament/Resources/LandingServices/` (`LandingServiceResource` + `Schemas` + `Tables` +
`Concerns` + `Pages`). Grupo «Contenido», gated por el permiso **`content.manage`** (los 5 `can*` +
`shouldRegisterNavigation`). Toggles `is_active` / `show_in_nav` independientes. Aviso
`packWarning` si el pack vinculado no es comprable.

## 7. Sidebar de compra — impacto CERO
El sidebar `Purchase` ya vende por TIPO+zona (`#[On('show-packs')]`, aforo por `zone_id`); su
pestaña de packs se etiqueta «Servicios» (`purchase.blade.php`, evento
`show-packs`→`catalog-open-services`). El `LandingService` es SOLO presentación de landing: en el
sidebar se venden TODOS los packs vendibles (con o sin `LandingService`). ⚠️ Naming: «Servicios»
en el sidebar = packs comprables; la entidad CMS se llama `LandingService` precisamente para no
solapar.

## 8. Seed
Seeder dedicado **`database/seeders/LandingServicesSeeder.php`**: permite sembrar/actualizar los
servicios en un entorno con datos sin resembrar todo (separado de `LandingContentSeeder`).

## 9. Casos borde (comportamiento verificado en la base heredada)
- Sección sin pack (`ticket_type_id` NULL) o con pack no comprable → card de solo-contacto.
- Pack borrado → `nullOnDelete` → la sección degrada a contacto (sin FK colgando).
- `is_active=false` → fuera de `/servicios` **y** del nav (aunque `show_in_nav=true`).
- 0 servicios activos → `/servicios` conserva hero editorial + banda «Otros eventos» (no rompe).
- Zona desactivada (`is_active=0`) → `isSellablePackForLanding()` false → card degrada a contacto;
  y el pack sale de todas las superficies de venta (`inOperationalZone`).
- Pack vendible sin `LandingService` y fuera de cumpleaños no existe como estado: sin entidad, es
  de cumpleaños por defecto.

## 10. Tests heredados
- `tests/Feature/Admin/LandingServices/LandingServiceResourceTest.php` — CRUD + gating + toggles.
- `tests/Feature/Landing/LandingServiceTest.php` — scopes/modelo.
- `tests/Feature/Landing/ServicesPageTest.php` — /servicios data-driven (editorial + card comprable
  + solo-contacto + degradación).
- `tests/Feature/Landing/LandingServicesSeederTest.php` — seeder dedicado.
- `tests/Feature/Landing/ServicePriceTableBackfillTest.php` — backfill de `price_table`.

Relacionados: `docs/ARQUITECTURA.md` (datos compartidos de vista) · `docs/MODELO-DATOS.md` ·
`docs/TESTING.md`. ⚠️ Aquí se citaba `OFERTAS-WIDGET.md`, otra entidad CMS clonada de ésta: se retiró en `#668` y su histórico está en `archivo/ofertas-widget.md`.
