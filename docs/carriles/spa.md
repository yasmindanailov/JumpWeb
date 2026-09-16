# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **550–579** · Último usado: **`#571`** · Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-16.
> ⚠️ Escrito por el carril de plataforma en F1 (`DECISIONES #621`) desde tu bloque del estado del 13-09:
> **hazlo tuyo en tu siguiente cierre**. Desde entonces este fichero lo escribe SOLO el agente de este carril.
> Techo 24 KB. El contador de la suite va en el trailer del commit (`#618`), no aquí.

## Foto (cierre del 2026-09-13, noche)

- **El armazón completo y las seis paradas cerradas: las 25 pantallas del cajón están construidas**
  (`#550`→`#568`): la grieta 00 (el cuerpo al suelo del sistema) y la 01 (el cajón deja de pintar la acción
  con la marca), la tarjeta grande y la puerta de categoría, el pie, la banda de cinco fases, el día y la hora,
  la cesta, el paso de pagar y los cuatro desenlaces, las nueve pantallas de la cuenta, el suelo táctil y el
  documento legal como control, y el cajón que abre EN el producto.
- **`celebracion-e-invitacion.md`**: T1 (`#570`, la piel del formulario post-reserva y del justificante) y T2
  (`#571`, muchos invitados y el pegado de nombres, medido en navegador: 17 nombres, 20/20 en su sitio) **EN EL
  ÁRBOL, SIN DESPLEGAR**.
- ❗❗ **Defecto en producción desde `#444` (08-09), arreglado en el árbol**: el número de invitados del
  post-form NO se enviaba (el campo vive fuera del `<form>`; hoy `form="gf-form"`, verificado 20 → 19).
  **Pide despliegue**, que lleva cuatro líneas nuevas en el `client.css` de producción (`--done`, `--on-done`,
  `--done-ink`, `--err-ink`; ya en la rama `cliente/playjump`).
- Pendiente del ojo del owner: «Guardar» en el secundario (`#539`) y no en tinta.

## Por dónde retomar, en orden

1. **La T3** de `celebracion-e-invitacion.md`: la piel del justificante. Su hora pinta el FIN de la franja
   («17:00 – 18:00»), la trampa de `#426`: usar la duración efectiva.
2. T4 (dominio y contrato) → T5 (la página) → T7 (correos) → **T6, el aterrizaje, al final** (borde abierto
   en su §7·5: bajar invitados descarta las filas del final).
3. Lo que queda de la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas se ha
   visto en uno) · el **cuaderno de entrega** del cajón, como el de la portada · el **botón del sistema**
   (16/800 con borde), aplazado por `rediseno-desde-canvas.md`.

## Ficheros de este carril

`resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php` y
`lang/*/account.php` · `lang/*/guestform.php` y `lang/*/guardian.php` · `resources/views/reservation/**` y las
clases `.guardian__*` · `tests/Feature/Sidebar/**` y `tests/Feature/Architecture/Sidebar*` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO de sección. **Compartido, se avisa en el buzón
ANTES**: `CARRIL-SPA.md` §5. Lo del cliente va también en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas

- Techo del chunk **285** (medido 284,04): la poda obvia ya se midió y no paga.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de medir la suite, también tras un
  arnés de mutación (restaura el árbol, no el bundle). `npm install` poda `playwright-core`; Chromium y
  `socat` mueren al recrear el contenedor (se instala con la CLI del proyecto, no con `npx`).
- Commit por NOMBRE de fichero, nunca `git add -A`. El número sale de la banda y la entrada se añade al final
  de `docs/decisiones/500-599.md`.
- Las reglas de trabajo del owner: `CARRIL-SPA.md` §6.

## Buzón

### Para el carril de la web (emisor: SPA, 2026-09-13)
- Lo compartido que tocó `#570`: `landing.css` `:root` gana `--done`/`--on-done`/`--done-ink` (defecto `--ok`,
  no mueve nada fuera de estas páginas); `focused-layout.blade.php` carga `client.css`; este carril toma
  `.guardian__*`, `resources/views/reservation/**` y `lang/*/guestform.php`/`guardian.php`.
- Los mensajes de `#550`→`#566` están anotados como atendidos en `web.md`, con dos pendientes que dejaste a la
  web (retirar los bloques acotados de `.sidecart__panel` cuando vista esas superficies).

### Atendido
- `#539`/`#540` de la web (los botones del cajón al secundario): anota aquí cuándo los diste por atendidos.
