# Paquete del cliente · PlayJump Park (rama `cliente/playjump`)

Esta rama **no es código**. Es el material de la instalación **PlayJump Park** que, por la regla
número uno del proyecto (`DECISIONES #1`: *este repo es el PRODUCTO, sin marca de ningún cliente*),
**no puede vivir en `main`**: su paquete de tema, su marca, su kit de ilustración, la copia del canvas
de Claude Design, las fuentes originales y los datos de su catálogo.

Existe para que **otro ordenador** (otro agente de Claude Code) tenga exactamente lo mismo que este.
`[DECIDIDO owner, 2026-09-11]`: una rama aparte del mismo repositorio, no dentro de `main`
(`DECISIONES #530`).

## ⛔ Dos reglas que no se rompen

1. **Esta rama NUNCA se fusiona con `main`.** Tiene historia propia (rama huérfana); un `merge`
   pediría `--allow-unrelated-histories`, y si ves eso, **para**.
2. **Nada de esto se añade a `main`.** En `main` todas estas rutas están en el `.gitignore`. Si un
   `git status` en `main` te enseña alguna de ellas como *para añadir*, algo va mal: **no hagas
   `git add -A`** y avisa.

## Qué hay

| En la rama | Va a (en tu clon de `main`) | Qué es |
|---|---|---|
| `public/css/client.css` | `public/css/client.css` | **El paquete de tema**: colores por superficie, `--money`, `--marker`, radios, aire, columna… (`docs/INSTALACION-CLIENTE.md` §4) |
| `public/img/client-*` | `public/img/` | Logotipo (y sobre tinta), la «A» del logotipo, favicon e iconos, `client-kit.svg` (el kit de ilustración), `client-tag.svg`, `client-menu.webp` |
| `mockup_playjumppark_v2/` | `mockup_playjumppark_v2/` | **La copia BUENA del canvas** (artboards `*.dc.html`). ⚠️ Caduca: la fuente de verdad es el canvas (abajo) |
| `mockup_playjumppark/` | `mockup_playjumppark/` | **El ARCHIVO** del canvas: paleta antigua, **no copiar colores** de aquí (`rediseno-desde-canvas.md` §1.2) |
| `entorno/cliente.env` | tu `.env` (a mano) | `THEME_FONTS` — las fuentes de la instalación. No es secreto |
| `datos/catalogo-playjump.sql` | tu base MySQL (opcional) | Catálogo y configuración: zonas, atracciones, productos, precios, tramos, complementos, horarios, franjas, normas, dudas, páginas, textos legales y los ajustes **sin secretos** |
| `fuentes/hero-playjump-original.mp4` | — (no se copia) | El vídeo ORIGINAL del hero (1080p, con audio). El optimizado ya está en `main` (`public/videos/header_hero.mp4`, `#529`) |

**Lo que NO está, a propósito**: usuarios, pedidos, pagos, reembolsos, firmas, consentimientos,
menores, carnés, sesiones, registros de auditoría, y los ajustes `auth.*` (Google) y `redsys*` (pasarela).
Cada ordenador pone sus propias credenciales de desarrollo.

## Cómo aplicarlo

Desde la **raíz de tu clon de `main`**, con Docker levantado:

```bash
git fetch origin cliente/playjump
git show origin/cliente/playjump:aplicar.sh | bash              # solo los ficheros
git show origin/cliente/playjump:aplicar.sh | bash -s -- --datos # ficheros + datos
```

⚠️ **Los datos BORRAN y rellenan** esas tablas de catálogo en tu base (tus usuarios y pedidos no se
tocan). Tu base tiene que estar migrada a la versión de `main` antes (`php artisan migrate`).

## El canvas de verdad

El diseño del cliente vive en **Claude Design** y se lee con el MCP **`DesignSync`**
(`projectId = 8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad`), con la misma cuenta del owner. La copia
`mockup_playjumppark_v2/` es una foto: **antes de construir una pieza, compárala con el canvas**
(`docs/specs/tema-por-instalacion.md` §1). Trampas conocidas: `Portada PJP` se baja truncado a 256 KiB
y los binarios a 192 KiB, sin avisar.

## Cómo se actualiza esta rama

Quien cambie algo del cliente (un color del paquete, un logotipo, una copia nueva del canvas) lo hace
en **los dos sitios a la vez**: en su `main` local (donde está ignorado) y aquí, con un commit en esta
rama y `git push origin cliente/playjump`. Receta con un árbol de trabajo aparte, para no tocar el tuyo:

```bash
git worktree add ../JumpWeb-cliente-playjump cliente/playjump
cp public/css/client.css ../JumpWeb-cliente-playjump/public/css/   # lo que haya cambiado
git -C ../JumpWeb-cliente-playjump commit -am "cliente: …" && git -C ../JumpWeb-cliente-playjump push
```

Antes de empujar aquí, `git pull` en esta rama: el otro ordenador también puede haberla movido.
