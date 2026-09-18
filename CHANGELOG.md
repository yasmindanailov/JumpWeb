# Changelog — JumpWeb (el producto)

> Versionado semántico sobre el producto entero (`docs/decisiones/600-699.md`, `#613` y `#624`).
> **MAYOR**: una instancia tiene que ACTUAR para actualizar · **MENOR**: capacidad nueva sin acción ·
> **PARCHE**: arreglo. Cada versión lleva dos mitades: lo que tiene que saber una **instancia** y lo
> **interno**. Producción despliega solo etiquetas (guarda 8 de `scripts/deploy.sh`); staging despliega
> `main`. Una versión se corta con la skill `/release`.

## v1.1.0 · 2026-09-18

Tres cosas: el precio de antes tachado y el recuadro de una oferta en la sección de tarifas, encendidos
por dos ajustes de la instalación (`#628`, chapuza declarada hasta el sistema de ofertas); la piel del
justificante del menor invitado con el arreglo de su barra de firmar, que estaba rota en producción desde
el octavo despliegue (`#572`, T3 del carril del SPA); y los cimientos de la invitación digital de
cumpleaños, apagados (`#573`→`#576`, T4·1–T4·4).

### Para las instancias

- **Nada que hacer para actualizar.** Sin los ajustes, la web es la de v1.0.0 con el justificante vestido.
- **Una migración nueva** (`create_party_invitations`: dos tablas de la invitación digital) que el
  despliegue corre solo; los dos interruptores de la invitación nacen APAGADOS y nada cambia en el embudo
  hasta que el panel los encienda (T5/T6, todavía sin construir).
- Para enseñar una rebaja YA aplicada a los precios del catálogo: `promo.percent` (por ejemplo `20`)
  en la tabla `settings` (grupo `promo`, sin pantalla en el panel) → la card de la portada y `/precios`
  tachan el precio de antes (`precio × 100 / (100 − pct)`). Y `promo.banner.{es,en,fr}` → el recuadro
  con el copy encima del carril. Se apaga borrando las filas; **al subir los precios, borrarlas el
  mismo día**, o el tachado mentiría.
- El contrato de la API no cambia (`info.version` sigue en 1.0.0); el cajón no pinta el «antes».

### Interno

- `Setting::promoPercent()`, `WritesLandingValues::antes()`, `RateCards` y `RateTable` con el tercer
  argumento, `site.promo_banner` en el payload compartido, `rate-rail.blade.php`, `pages/pricing.blade.php`,
  `landing.css` (`.rate-card__was`, `.rate-table__was`, `.rates__promo`), `landing.rates.was` en tres idiomas y
  cuatro casos en `RateRailSectionTest` (mutación vista: 3 en rojo con el porcentaje a 0).
- La spec del mecanismo queda aparcada en `docs/archivo/promo-precio-anterior.md`.
- Del carril del SPA (`carriles/spa.md`): T3, la piel del justificante (`GuardianSkinTest`, 10 casos) y la
  barra de firmar a su sitio; T4·1–T4·4, las tablas, `PartyInvitations` con su lock de una fila (16 padres →
  entra 1, medido en InnoDB), `show_in_invitation` en el pivote de complementos (`ProductAddon.php`, del
  `CRITICAL_RE`: sus pushes fueron con `VERIFY_CONC=1`), el cuarto sumando del suelo de plazas y la
  excepción del firmador. Sin cambios en el contrato OpenAPI.

## v1.0.0 · 2026-09-17

La línea base: lo que corre en producción desde el 2026-09-16 (`1272cb93`, octavo despliegue). Es la
primera etiqueta de versión del repo; los ocho despliegues anteriores se identificaron por hash
(`docs/ENTORNOS.md` §6).

### Para las instancias

- **Nada que hacer**: v1.0.0 describe lo que ya está instalado. Es el punto desde el que se mide todo
  lo que una instancia tenga que tocar a partir de ahora.
- El paquete de una instancia son **cuatro piezas** fuera del repo (`docs/INSTALACION-CLIENTE.md`):
  `public/css/client.css` · `public/img/client-*` · el kit de ilustración · la variable `THEME_FONTS`
  del `.env`. Ninguna viaja por `rsync`: se suben aparte y sobreviven al `--delete`.
- `client.css` en v1.0.0 incluye los cuatro tokens que pidió el formulario de celebración
  (`--err-ink`, `--done`, `--on-done`, `--done-ink`, `#569`–`#571`). Una instancia que no los defina
  hereda los del producto.
- El contrato de la API es `openapi/v1.yaml`, `info.version` **1.0.0**. Sube en MENOR al añadir; una
  ruptura sería `/api/v2`.
- Desde esta versión el servidor dice qué corre: `storage/app/version` (`versión hash fecha`), escrito
  por el despliegue a partir del primero que siga a esta etiqueta.

### Interno

- Todo el historial hasta `1272cb93` (921 commits): la base heredada y endurecida (`#1`), los módulos
  de dominio, la API v1 y el checkout orquestado, el cajón en Vue 3 + Pinia, el panel Filament, la web
  rediseñada desde el canvas con Redsys en `live` (`#594`), los correos, Google auth, y el formulario
  post-reserva vestido con el arreglo del número de invitados (`#569`–`#571`).
- El tracker de fases es `docs/00-REFACTOR.md`; el porqué de cada pieza, `docs/DECISIONES.md` por número.
