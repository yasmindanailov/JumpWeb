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
| 🏗️ Plataforma · producto e instancias | este ordenador (`~/proyectos/jumpweb/producto`, `#648`) | **670–699** | `carriles/plataforma.md` | **F0–F4 ✅**. **v1.2.0 etiquetada y SIN DESPLEGAR** — lo decide el owner. **F5**: menú de hechos SERVIDO (contrato 1.11.0) · **T2a, T2b y T2c HECHAS** (`#666`, `#667`): las NUEVE vistas viven en la instancia y el CSS huérfano está podado y con trinquete · **T3·1 y T4 hechas** (`#668`, `#669`; contrato de instancia **2**) · ❗ **dirección nueva del owner (21-09): landing nueva sobre la API (vía A)** → lo siguiente son los cuatro platos que faltan del menú, no la T5 |
| 🎨 Diseño de la web | este ordenador | 580–609 | `carriles/web.md` | todo en producción (13-09); sigue la T6 de copys |
| 🧩 Diseño del SPA (el cajón) | el otro ordenador | 550–579 | `carriles/spa.md` | T1–T2 de celebración en el árbol, sin desplegar; sigue la T3 |
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
