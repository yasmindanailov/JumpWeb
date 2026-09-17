# Changelog — JumpWeb (el producto)

> Versionado semántico sobre el producto entero (`docs/decisiones/600-699.md`, `#613` y `#624`).
> **MAYOR**: una instancia tiene que ACTUAR para actualizar · **MENOR**: capacidad nueva sin acción ·
> **PARCHE**: arreglo. Cada versión lleva dos mitades: lo que tiene que saber una **instancia** y lo
> **interno**. Producción despliega solo etiquetas (guarda 8 de `scripts/deploy.sh`); staging despliega
> `main`. Una versión se corta con la skill `/release`.

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
