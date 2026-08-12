# SISTEMA — Ofertas (widget «caja de regalo» informativo)

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Estado heredado: ✅ implementado.** Feature **puramente informativa** (sin dinero/carrito/aforo):
> widget flotante (caja de regalo animada) que aparece **solo si hay ofertas activas**; al pulsarlo
> abre un modal con **carrusel** de ofertas (título + imagen), gestionadas desde el panel (CMS).
> **NO confundir** con la feature «ofertas por cantidad» (precio por tramos, toca dinero): quedó
> diferida en el repo origen y NO está portada. Los comentarios del código heredado citan
> `PLAN-OFERTAS-WIDGET.md` y decisiones `#NNN` del repo origen (histórico, no portado).

## 1. Alcance heredado
- CRUD de ofertas (sin máximo, orden libre) desde el panel Filament.
- Cada oferta = **título (i18n es/en/fr)** + **imagen** + `is_active` + `position`. **Sin CTA**:
  el modal muestra solo título e imagen, sin botón/enlace.
- Widget en **todo el sitio público** (igual que cookie-banner y book-bar). Excepción: el
  post-form de invitados (`focused-layout`) NO lo lleva.
- **Fuera de alcance** (ampliable): CTA/enlaces, cupones/pago, fechas de vigencia, segmentación.

## 2. Modelo de datos — tabla `offers`
Migración `2026_07_08_000001_create_offers_table` (molde = `create_landing_services_table`
recortado):

| Columna | Tipo | Notas |
|---|---|---|
| `title` | json nullable | i18n `{es,en,fr}` (cast `array`, `tr('title')`) |
| `image` | string nullable | ruta relativa en el disco `uploads` (`ofertas/<uuid>.webp`) |
| `position` | unsignedInteger default 0 | orden del carrusel (drag&drop en el panel) |
| `is_active` | boolean default true | gobierna la visibilidad del widget |

Modelo `app/Models/Offer.php`: `HasTranslations`, `$guarded=[]`, constante `IMAGE_DISK='uploads'`,
scopes `active()` / `ordered()`, `imageUrl()` (`asset('uploads/'.$image)`, sin symlink). Sin slug,
sin `ticket_type_id`, sin CTA.

**Limpieza de huérfanos en `booted()`** (FileUpload NO borra ficheros solo): evento `updating` —
si `image` cambia, borra el fichero viejo del disco; evento `deleted` — borra el fichero de la
oferta. `Storage::delete()` sobre ruta inexistente es no-op seguro.

## 3. Panel (Filament) — clon de `LandingService`
`app/Filament/Resources/Offers/`: `OfferResource` + `Schemas/OfferForm` + `Tables/OfferTable` +
`Concerns/InteractsWithOfferForm` + `Pages/{List,Create,Edit}Offer`.
- Grupo de navegación «Contenido» (`__('admin.nav_groups.contenido')`), tras FAQ.
- **Gating**: permiso existente **`content.manage`** en los 5 `can*` + `shouldRegisterNavigation`
  (sin permiso nuevo → no toca `PermissionCatalog`/`PermissionSeeder` ni su test de paridad).
- Formulario: `position` + Toggle `is_active` + `FileUpload::make('image')` (§4) + Tabs es/en/fr
  con `TextInput("title.{$locale}")->required($locale==='es')`.
- Tabla: `ImageColumn` + título `tr()` + badge `is_active`;
  `defaultSort('position')->reorderable('position')` + `TernaryFilter(is_active)`.
- Concern `prepareOfferData`: `compactTranslations(['title'])` (idiomas vacíos→null) + defaults
  NOT NULL (`is_active`/`position`, por la deshidratación de Toggle a false).
- **Audit**: `AuditLogger::log('content.offer_created|updated|deleted', …)` en Create/Edit/Delete.

## 4. Imágenes — primer upload real del proyecto (patrón heredado)
- **Disco propio** en `config/filesystems.php`:
  `'uploads' => ['driver'=>'local', 'root'=>public_path('uploads'), 'url'=>APP_URL.'/uploads', 'visibility'=>'public']`.
  Servido **nativo desde `public/uploads`, SIN symlink `public/storage`** (en el origen el symlink
  estaba roto en prod y el deploy lo borraría; el disco directo es más robusto).
- **FileUpload**: `->disk('uploads')->directory('ofertas')->visibility('public')->image()
  ->maxSize(2048)->acceptedFileTypes(['image/webp','image/jpeg','image/png'])`. Persiste
  `ofertas/<uuid>.webp` en `offers.image`.
- **`.gitignore`**: `/public/uploads` (las subidas no se versionan; viven solo en el servidor).
- ⚠️ **Gotcha de despliegue heredado**: si el deploy sincroniza con `rsync --delete`, DEBE llevar
  `--exclude='/public/uploads'` en TODOS los rsync, o cada deploy borraría las subidas del panel.
  En instalación nueva el symlink `storage:link` NO hace falta para este sistema.

## 5. Landing — datos, widget y rendimiento
**Datos (site-wide, 1 query/request):** `AppServiceProvider::activeOffers()` dentro de
`buildSharedViewData()` (payload memoizado por request; clave `'offers'`), calcado de
`navServices()`: guardado por `tableExists('offers')`,
`Offer::active()->ordered()->get(['id','title','image'])`. **Sin `Cache` TTL** → las ediciones del
panel se ven al instante. **Resiliencia de deploy zero-downtime:** la query va en `rescue(…,
collect(), false)` — entre el rsync del código nuevo y `php artisan migrate` la tabla puede no
existir; sin `rescue` sería un 500 en TODAS las páginas (el composer corre en cada vista).

**Widget** `resources/views/components/site/offers-widget.blade.php`, inyectado en
`components/layout.blade.php` junto a cookie-banner/mobile-book-bar, envuelto en
`@if(($offers ?? collect())->isNotEmpty())` → **sin ofertas activas = 0 DOM, 0 animación**
(requisito clave, testado).
- **Caja de regalo**: SVG inline + CSS (clases `.offw*` en `public/css/site.css`, ~2-3 KB).
  Diseño cerrado heredado: lazo «A», colores fijos (caja roja `#E0392C` + lazo crema), **badge con
  el nº de ofertas**, sin halo ni burbuja. Animaciones **solo `transform`/`opacity`** (GPU); idle =
  zarandeo periódico; confeti solo al abrir. El CSS procede de un mockup del repo origen (no
  portado); lo integrado en `site.css` es autosuficiente.
- **Modal + carrusel**: `Alpine.data('offersWidget', count)` en `resources/js/app.js` (NUNCA
  `<script>` suelto por el morph de Livewire), patrón de índice + `go(n)` con vuelta infinita;
  shell `.modal`/`.modal__panel` + `a11yPanel` (focus-trap/Escape) + `body.no-scroll`, `x-cloak`,
  `x-data` propio (fuera del `x-data="landing"`).
- **Animaciones de apertura** (one-shot, GPU): (1) destello de la caja al pulsar (`#burst`, clase
  `.is-burst`); (2) modal «volando y creciendo desde la caja»: el componente calcula el centro de
  la caja con `getBoundingClientRect()` (vía `x-ref`) y fija CSS vars `--ox/--oy` (origen) y
  `--dx/--dy` (desfase caja→centro); recalcula en `resize`. Único coste extra:
  `backdrop-filter: blur` del scrim solo con modal abierto. `prefers-reduced-motion` desactiva
  estallido y vuelo (aparición simple).
- **Capas (z-index), sistema heredado A–E**: modal=150 · sidecart=160 · book-bar=90 · cookie=1000
  · flash=200; la caja flotante bottom-right en z-index que no choca con el banner de cookies.
- **Móvil**: `Alpine.store('offers')` (como auth/purchase/cookies) → la `.book-bar` cede con
  `! $store.offers.open`; la caja se coloca por encima de la book-bar (offset vertical).
- Assets: `npm run build` **dentro de Sail**. Sin dependencias externas.

## 6. i18n
- Contenido: `title` JSON por idioma, `tr('title')`.
- Panel: bloque `admin.offers` en `lang/{es,zh_CN}/admin.php` (idiomas del panel heredados).
- Widget (aria-labels, «Anterior/Siguiente», título del modal): `lang/{es,en,fr}/offers.php`.

## 7. Seguridad
- Título con `{{ $offer->tr('title') }}` (auto-escape XSS; nunca `{!! !!}`). Testado con
  `<script>` en el título.
- Imagen: validación mime/tamaño del FileUpload; `@if($offer->image)` antes de pintar;
  `loading="lazy"` / carga on-demand al abrir el modal.
- Widget nunca en el DOM sin ofertas activas (defensa + rendimiento).

## 8. Tests heredados
- `tests/Feature/Admin/Offers/OfferResourceTest.php` — gating admin/staff, i18n compactado,
  `title.es` requerido, validación de imagen, audit en create/update/delete.
- `tests/Feature/Landing/OffersWidgetTest.php` — panel→landing (aparece/desaparece por
  `is_active`/borrado), orden = `position`, título por locale, **no render sin ofertas activas**,
  XSS escapado.
- Moldes usados (útiles para features análogas): `LandingServiceResourceTest`,
  `LandingServiceTest`, `ZoneImageTest`.

Relacionados: `docs/sistemas/SERVICIOS-CMS.md` (entidad CMS de la que se clonó) ·
`docs/ARQUITECTURA.md` · `docs/SEGURIDAD.md` · `docs/TESTING.md`.
