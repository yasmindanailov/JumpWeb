# Carril · Diseño de la web

> Máquina: **este ordenador** · Banda: **580–609** (agotadas 470–499 y 520–549) · Último usado: **`#594`** ·
> Specs: `contenido-y-copys.md` §0 · `rediseno-desde-canvas.md` §0 y §5.5 · `pasada-de-vestido.md` §0 ·
> Actualizado: 2026-09-16 (escrito por el carril de plataforma en F1 desde el bloque del estado del 13-09;
> hazlo tuyo en tu siguiente cierre).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB.

## Foto (cierre del 2026-09-13, noche)

- **Todo en producción**: la web nueva con el contenido de la sesión (`#590`, séptimo despliegue) y después
  `#591`→`#594`: las reseñas siempre puestas (refresco cada 30 min en un idioma, caché de 35; ~11 USD/mes;
  tope diario de la consola 100; la IP del servidor admitida en la clave), sin cookies la sección no desaparece
  (`POLICY_VERSION = 2026-09-13` vuelve a pedir permiso a todos), tres titulares nuevos, y **Redsys en `live`
  desde el 13-09** (TPV real probado de punta a punta: `R-VPCOHW` cobrado y devuelto por REST).
- **Contenido y copys**: las decisiones del owner tomadas y T1–T5 aplicadas (`#586`→`#589`); T1+T2 con el
  script de contenido, fuera del repo.
- **Las siete páginas del inventario del canvas, construidas**; `/servicios` (grupos) PAUSADA por el owner
  (`#534`: la lógica está medida y escrita, cero código; espera su artboard).
- **La pasada de vestido**: primera tanda (el reparto del color, `#537`→`#542`) y segunda (las piezas de
  fachada sobre la portada, `#543`→`#549`) hechas y en staging; las dos variantes siguen siendo prototipo local.

## Por dónde retomar, en orden

1. **La T6 de contenido**: en/fr de lo que escribe el panel (dudas, normas, nota de acceso).
2. El **título de la pestaña** de la portada, que dice «Murcia» (`landing.footer.tag`).
3. La pasada de copys página a página: `/cumpleanos`, `/servicios`, `/normas`, `/contacto` y `/bar`.
4. **Google Business Profile** (`#524`, spec aprobada, código sin empezar; `[owner]`: las reseñas van después
   del diseño).
5. **Las bandas** (`Bandas PJP`): decididas por el owner, sin construir — la gorda al final de la página, las
   finas antes del cierre, el reparto del canvas tal cual; después la banda de arriba con sus cuatro tonos.
6. La pasada de vestido: los tres ejes que quedan (iconos · piezas de fachada · imagen y movimiento) y el censo
   pieza × pantalla; el owner decide si las variantes bajan a `landing.css` y si el laboratorio se retira.
7. `/servicios` → «Grupos», cuando llegue el artboard: no hay que volver a investigarla (`#534`).
- **Lo que bloquea y es del owner (datos, no diseño)**: la foto de la mesa de `/cumpleanos` · la carta y la
  foto del local de Cantina · el aparcamiento (la sección 07 y la web publicada dicen cosas distintas) · los
  festivos de Lorca · el horario en conflicto · qué pasa con `/entradas` · su pregunta de la «i» en la card del
  QR (lo que hay ahí es un párrafo de ayuda).
- **Fuera del plan de cinco fases y sin carril asignado** (medido el 12-09 en `doc/pendiente.md` del canvas):
  el PANEL (~25 decisiones del owner abiertas), la APP móvil, las INVITACIONES (hoy en
  `celebracion-e-invitacion.md`, carril del SPA) y las BANDAS.

## Ficheros de este carril

`resources/views/pages/**` · `resources/views/components/site/**` · `resources/views/home.blade.php` ·
`public/css/landing.css` (salvo el `:root`) · `lang/*/landing.php` y `lang/*/site.php` ·
`tests/Feature/Site/**` y `tests/Feature/Landing/**` · los bloques de la web de `public/css/site.css`.
**Compartido, se avisa en el buzón ANTES**: los tokens (`:root` de `landing.css`: uno del cajón mueve la web
entera), `resources/views/components/layout.blade.php`, `resources/js/app.js`, `package.json`, y las listas
globales de las guardas (`MotionBudgetTest`, `ShapeScaleTest`, `TouchTargetTest`,
`InteractionColourIsNotAZoneTest`, `ActionFillTest`). El reparto completo con el SPA: `CARRIL-SPA.md` §5.

## Trampas vivas

- **Se despliega DE NOCHE o con el parque cerrado** (`[DECIDIDO owner]`, `#594`: tres minutos de 503 con un
  pago real en curso).
- `artisan cache:clear` en producción borra también las reseñas: después va `social-proof:refresh`. Un `*/`
  dentro de un comentario PHP lo cierra y da un error de sintaxis.
- El kit de ilustración y el `client.css` **no viajan en el `rsync`**: se suben a mano y se validan
  (`php artisan kit:build --check`); staging abortó una vez en la guarda 7 por eso.
- La copia local del canvas caduca (el 12-09 iba ~50 artboards por detrás): se lee con `DesignSync` antes y
  durante cada tanda. `mockup_playjumppark/` es el ARCHIVO; la buena es `_v2/`.
- Tras un `pull` con commits del SPA, `npm run build:ssr` ANTES de la suite (35 rojos con el árbol limpio);
  los arneses que mutan `.vue` restauran el árbol y no el bundle.
- En local las reseñas son las tres de ejemplo (Google rechaza la IP de casa) y el refresco no se programa
  solo. Las capturas de página completa no están versionadas (`storage/app/captura.mjs`, gitignorado) y
  encontraron tres defectos que ninguna sonda vio: conviene versionarlas.
- Si cambias algo del cliente, cámbialo también en la rama `cliente/playjump`.

## Buzón

### Para el carril del SPA (emisor: web, 2026-09-12)
- `#540`: **ningún botón con fondo negro**, web y cajón. `.cartbar`, `.bk-cta`, `.acct__btn--primary`,
  `.btn--ink` y el salto al contenido pasan al cian del secundario; `.bk-cta--sells` se queda naranja, con el
  rótulo en blanco a 2,70 de contraste (desviación DECIDIDA, las otras dos están medidas en el CSS). Dos listas
  tuyas encogen en 3 (`SidebarActionRoleTest::CENSO_DE_MARCA`, `InteractionColourIsNotAZoneTest`). Quedan dos
  fondos oscuros que no son botones (`party-card`, `catalog-acc__head`): pendiente del owner.
- `#539`: `.btn--ink` pasa a `--interactive` (con `-hover`/`-press`, paso 0,88); revierte a sabiendas una
  decisión del owner de septiembre; tres guardas se mueven (`SingleButtonFamilyTest`,
  `InteractionColourIsNotAZoneTest`; nace `IdentityTintTest`); el rótulo es `var(--bg)`, no `--paper-bg`;
  `.btn--ghost` pasa a Azul Muro.
  ▶ Los retiro cuando anotes «atendido» en `spa.md`.

### Atendido (mensajes del SPA a la web, leídos al fusionar el 13-09)
- `#566`: `layout.blade.php` gana `urls.privacy`; `register` pierde su `array_replace`; `eyebrow` sale de
  `register` y `verify`; `.form__hint a, .form__hint button`. Sin acción pendiente.
- `#564`: `.addons-mini__badge--free` pasa a tinta; los siete colores del primer cliente salen del producto
  (`--warn`, `--refund`…); `SemanticFillTextTest` recorre las dos hojas. Sin acción pendiente.
- `#562`: `layout.blade.php` gana `urls.terms`; `.sidecart__panel .check input` acotado al panel.
  ▶ **Pendiente para la web**: ese bloque ENCOGE el día que vista el formulario de contacto, el post-form o el
  justificante.
- `#561`: el cajón adopta `.tabset`/`.tabset__tab` y depende de su `display: flex` y `flex: 1`
  (`SidebarTokenBudgetTest` lo asevera). `.zone-tab` sigue viva por `/servicios`: podable si la pierde.
- `#551`: `.btn--zone` ya no existe (`.btn--ink`); tres listas globales encogen; un comentario de
  `cookie-banner.blade.php` dejó de citar una variante concreta. Sin acción pendiente.
- `#550`: `landing.css` pierde `--fs-9`; 23 clases compartidas acotadas dentro de `.sidecart__panel`.
  ▶ **Pendiente para la web**: retirar ese bloque acotado cuando vista esas superficies.
