# Estado del proyecto — índice de carriles

> Desde F1 (`DECISIONES #621`, 2026-09-16) el estado vive **en un fichero por carril**,
> `docs/carriles/<carril>.md`, y **cada agente escribe SOLO el suyo** (foto · por dónde retomar · ficheros ·
> buzón). Este índice no cambia al trabajar: lista los carriles y dónde está cada entorno. El contador de la
> suite va en el trailer del commit de cierre (`CONVENCIONES §8`, `#618`), en ningún documento. Techos:
> este fichero 4 KB, un carril 32 KB (`docs-check` check 10; `#724`, era 24). El histórico está en
> `git log -p docs/ESTADO.md` hasta el commit de F1.

## Carriles

| Carril | Máquina | Banda | Fichero | Estado |
|---|---|---|---|---|
| 🏗️ Plataforma · producto e instancias | este ordenador (`~/proyectos/jumpweb/producto`, `#648`) | **670–699** | `carriles/plataforma.md` | **F0–F4 ✅** · v1.2.0 etiquetada y **sin desplegar**: producción sigue en v1.1.0 hasta la v2.0.0 grande (`#670`) · **F5**: T2a→T2c, T3·1 y T4 hechas; la **vía A** con su **menú de hechos COMPLETO** (`#671`→`#677`, contrato **1.17.0**) · **la analítica** en marcha (`#678`, contrato **1.19.0**): **T1 ✅** (libro, emisor, correos, pedido manual, derechos; arnés 19/19; `SEC-13`) · **la analítica sigue en el OTRO ordenador** (T2→T5, traspaso en el buzón de plataforma) · aquí: **la LANDING NUEVA** (vía A; sistema de diseño completo y distinto del actual, puede traer lógica nueva; arranca la próxima sesión con el diseño delante) |
| 🎨 Diseño de la web | este ordenador | 580–609 | `carriles/web.md` | todo en producción (13-09); sigue la T6 de copys |
| 🧩 Diseño del SPA (el cajón) | el otro ordenador | 730–759 (`#729` agotó la 700–729) | `carriles/spa.md` | T1–T2 de celebración en el árbol, sin desplegar; sigue la T3 |
| 📧 Correos | este ordenador | 500–519 | `carriles/correos.md` | entero en el árbol; quedan cuatro ámbar y el ojo del owner |
| 🏦 Pasarela / producto | este ordenador | 4xx (libres `#455`–`#459`) | `carriles/pasarela.md` | go-live hecho el 13-09; staging es la validación del banco |
| Google auth | el portátil | `#34x` | cerrado (`specs/auth-con-google.md` §0) | en producción desde el 02-09 |

## Reglas de convivencia (`CONVENCIONES §10`)

- **Reclamar = empujar** tu fichero de carril: lo que no está en `origin/main`, el otro no lo sabe.
- **Un mensaje al otro carril va en el buzón del EMISOR**; el receptor anota «atendido» en el suyo y el emisor
  lo retira en su siguiente cierre. Cero escrituras cruzadas.
- **Lo compartido** (los tokens, `layout.blade.php`, `app.js`, `package.json`, las listas globales de las
  guardas) **se avisa en el buzón ANTES de tocarlo**. El reparto por fichero: cada carril lo lista.
- `git pull --rebase` antes de cada push · tras traer commits del SPA, `npm run build:ssr` antes de la suite ·
  el número de una decisión sale de la BANDA y se escribe al final de su centena en `docs/decisiones/`.

## Entornos

- **Producción** (`playjump.es`): `ENTORNOS.md` §6 · lleva `#591`→`#594` (2026-09-13) · Redsys en `live` desde
  el 13-09 · se despliega DE NOCHE o con el parque cerrado (`[DECIDIDO owner]`, `#594`).
- **Staging**: `ENTORNOS.md` §1 y §4 · validación del banco (`#453`): no re-sembrar.
- El tracker de fases: `00-REFACTOR.md` (sus marcadores mandan, `#10`).
