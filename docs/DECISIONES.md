# Decisiones — JumpWeb (índice)

> Registro de decisiones: qué se decidió y por qué. **Se busca por número, nunca se lee entero.** Desde el
> 2026-09-16 (`DECISIONES #617`, F1 del programa «producto e instancias») las entradas viven **por centenas**
> en `docs/decisiones/`; este fichero es el índice y las reglas. Una cita «DECISIONES #N» sigue valiendo
> tal cual: `scripts/docs-check.sh` (check 6) comprueba que la entrada existe en cualquiera de las partes.

## Dónde está cada número

| Fichero | Números |
|---|---|
| `decisiones/000-099.md` | `#1` → `#99` |
| `decisiones/100-199.md` | `#100` → `#199` |
| `decisiones/200-299.md` | `#200` → `#299` (`#217` está dos veces) |
| `decisiones/300-399.md` | `#300` → `#354` (banda de Google auth, cerrada) |
| `decisiones/400-499.md` | `#400` → `#499` (`#430`, `#431` y `#432` están dos veces) |
| `decisiones/500-599.md` | `#500` → … (correos, SPA y web: bandas vivas) |
| `decisiones/600-699.md` | `#610` → … (plataforma: la centena viva) |

Recuento vivo: `grep -cE '^## #[0-9]+ ·' docs/decisiones/*.md` (al partir, el 2026-09-16, eran 536). Los
cuatro números repetidos son colisiones de la época sin bandas (`CONVENCIONES §10.6`); las dos entradas de
cada uno se conservan, adyacentes.

## Cómo se escribe una decisión

- **Cabecera** `## #N · AAAA-MM-DD · título`, al final del fichero de su centena. Cuerpo: qué se decidió,
  por qué, qué se descartó y qué cambia. **Techo: 1,5 KB por entrada** para toda decisión posterior a F0
  (número > 616 o fecha posterior al 2026-09-16); lo medido y lo largo va en su spec, y la decisión lo cita.
  El gate (check 10) lo mide. Las entradas anteriores no se reescriben.
- **El número sale de la BANDA del carril**, nunca del «siguiente libre» (`CONVENCIONES §10.6`): la banda y su
  «último usado» están en el fichero de cada carril, `docs/carriles/<carril>.md`. Mirar `origin/main` antes
  de empujar no basta: se probó y chocó trece veces.
- **Revertida o modificada** → en la entrada ANTIGUA, primera línea: «Sustituida por #N (fecha)». Sin marca,
  la decisión sigue vigente.
- **Decisión del owner** → `[DECIDIDO owner]` en el título; lo que espera su respuesta, `[PENDIENTE: owner]`.
- Cada decisión se refleja además con `[DECIDIDO]`+fecha en el documento afectado (`CONVENCIONES §5`).

## Bandas (para el número)

| Carril | Banda | Máquina |
|---|---|---|
| Plataforma · producto e instancias | 610–639 | este ordenador |
| Diseño de la web | 580–609 | este ordenador |
| Diseño del SPA (el cajón) | 550–579 | el otro ordenador |
| Correos | 500–519 | este ordenador |
| Pasarela / producto | 4xx (libres `#455`–`#459`) | este ordenador |
| Google auth (cerrado, en producción) | `#34x` | el portátil |

El «último usado» de cada banda vive en su fichero de carril, no aquí: este índice no cambia al decidir.
